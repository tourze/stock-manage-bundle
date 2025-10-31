<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Procedure;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\StockManageBundle\DTO\StockCheckRequest;
use Tourze\StockManageBundle\DTO\StockCheckResponse;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Repository\StockReservationRepository;

#[MethodTag(name: '库存管理')]
#[MethodDoc(summary: '批量检查库存可用性')]
#[MethodExpose(method: 'CheckStockAvailability')]
#[WithMonologChannel(channel: 'stock_manage')]
final class CheckStockAvailability extends BaseProcedure
{
    /**
     * @var array<array{productId: int, skuId: int, quantity: int}>
     */
    #[MethodParam(description: '库存检查项目列表')]
    public array $items = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly StockReservationRepository $reservationRepository,
        private readonly LoggerInterface $procedureLogger,
    ) {
    }

    /**
     * @return array{
     *   success: bool,
     *   totalCount: int,
     *   successCount: int,
     *   failedCount: int,
     *   results: array<array{productId: int, skuId: int, available: bool, currentStock: int, requestedQuantity: int, message: ?string, shortage: int}>
     * }
     */
    public function execute(): array
    {
        $this->validateInput();

        $requests = $this->parseRequests();
        $responses = [];

        $this->procedureLogger->info('开始批量库存检查', [
            'total_items' => count($requests),
            'items' => array_map(fn (StockCheckRequest $req) => $req->toArray(), $requests),
        ]);

        $this->entityManager->beginTransaction();

        try {
            foreach ($requests as $request) {
                $response = $this->checkSingleStockAvailability($request);
                $responses[] = $response;

                $this->procedureLogger->info('库存检查结果', [
                    'productId' => $request->productId,
                    'skuId' => $request->skuId,
                    'quantity' => $request->quantity,
                    'available' => $response->available,
                    'currentStock' => $response->currentStock,
                ]);
            }

            $this->entityManager->commit();

            $successCount = count(array_filter($responses, fn (StockCheckResponse $r) => $r->available));
            $failedCount = count($responses) - $successCount;

            $this->procedureLogger->info('批量库存检查完成', [
                'total_count' => count($responses),
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);

            /**
             * @var array<array{productId: int, skuId: int, available: bool, currentStock: int, requestedQuantity: int, message: ?string, shortage: int}>
             */
            $resultArrays = array_map(fn (StockCheckResponse $r) => $r->toArray(), $responses);

            return [
                'success' => true,
                'totalCount' => count($responses),
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'results' => $resultArrays,
            ];
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $this->procedureLogger->error('批量库存检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidOperationException('批量库存检查失败: ' . $e->getMessage(), 0, $e);
        }
    }

    private function validateInput(): void
    {
        $this->validateItemsExist();
        $this->validateItemsCount();
        $this->validateItemsStructure();
        $this->validateUniqueSkuIds();
    }

    private function validateItemsExist(): void
    {
        if ([] === $this->items) {
            throw new InvalidArgumentException('检查项目列表不能为空');
        }
    }

    private function validateItemsCount(): void
    {
        if (count($this->items) > 1000) {
            throw new InvalidOperationException('单次批量检查不能超过1000个项目');
        }
    }

    private function validateItemsStructure(): void
    {
        foreach ($this->items as $index => $item) {
            $this->validateSingleItem($item, $index);
        }
    }

    private function validateSingleItem(mixed $item, int $index): void
    {
        if (!is_array($item)) {
            throw new InvalidArgumentException(sprintf('第%d个项目数据格式错误', $index + 1));
        }

        /** @var array<string, mixed> $typedItem */
        $typedItem = $item;
        $this->validateRequiredFields($typedItem, $index);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateRequiredFields(array $item, int $index): void
    {
        $requiredFields = ['productId', 'skuId', 'quantity'];
        foreach ($requiredFields as $field) {
            $this->validateField($item, $field, $index);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function validateField(array $item, string $field, int $index): void
    {
        if (!isset($item[$field])) {
            throw new InvalidArgumentException(sprintf('第%d个项目缺少必需字段: %s', $index + 1, $field));
        }

        if (!is_int($item[$field]) || $item[$field] <= 0) {
            throw new InvalidArgumentException(sprintf('第%d个项目的%s必须为正整数', $index + 1, $field));
        }
    }

    private function validateUniqueSkuIds(): void
    {
        $skuIds = array_column($this->items, 'skuId');
        if (count($skuIds) !== count(array_unique($skuIds))) {
            throw new InvalidArgumentException('检查项目列表包含重复的SKU ID');
        }
    }

    /**
     * @return array<StockCheckRequest>
     */
    private function parseRequests(): array
    {
        $requests = [];
        foreach ($this->items as $item) {
            $requests[] = new StockCheckRequest(
                $item['productId'],
                $item['skuId'],
                $item['quantity']
            );
        }

        return $requests;
    }

    private function checkSingleStockAvailability(StockCheckRequest $request): StockCheckResponse
    {
        try {
            // 直接计算可用库存
            $totalQuantity = $this->stockBatchRepository->getTotalQuantityBySkuId($request->skuId);
            $reservedQuantity = $this->reservationRepository->getTotalReservedQuantity((string) $request->skuId);
            $availableQuantity = $totalQuantity - $reservedQuantity;
            $isAvailable = $availableQuantity >= $request->quantity;

            return new StockCheckResponse(
                productId: $request->productId,
                skuId: $request->skuId,
                available: $isAvailable,
                currentStock: $availableQuantity,
                requestedQuantity: $request->quantity,
                message: $isAvailable ? '库存充足' : sprintf(
                    '库存不足，需要%d个，可用%d个',
                    $request->quantity,
                    $availableQuantity
                )
            );
        } catch (\Exception $e) {
            return new StockCheckResponse(
                productId: $request->productId,
                skuId: $request->skuId,
                available: false,
                currentStock: 0,
                requestedQuantity: $request->quantity,
                message: 'SKU库存查询失败: ' . $e->getMessage()
            );
        }
    }
}
