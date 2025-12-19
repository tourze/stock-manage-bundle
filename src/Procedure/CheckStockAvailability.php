<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Procedure;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\StockManageBundle\DTO\StockCheckRequest;
use Tourze\StockManageBundle\DTO\StockCheckResponse;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Param\CheckStockAvailabilityParam;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

#[MethodTag(name: '库存管理')]
#[MethodDoc(summary: '批量检查库存可用性')]
#[MethodExpose(method: 'CheckStockAvailability')]
#[WithMonologChannel(channel: 'stock_manage')]
final class CheckStockAvailability extends BaseProcedure
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockBatchRepository $stockBatchRepository,
        private readonly LoggerInterface $procedureLogger,
    ) {
    }

    /**
     * @phpstan-param CheckStockAvailabilityParam $param
     */
    public function execute(CheckStockAvailabilityParam|RpcParamInterface $param): ArrayResult
    {
        $this->validateInput($param->items);

        $requests = $this->parseRequests($param->items);
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

            return new ArrayResult([
                'success' => true,
                'totalCount' => count($responses),
                'successCount' => $successCount,
                'failedCount' => $failedCount,
                'results' => $resultArrays,
            ]);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            $this->procedureLogger->error('批量库存检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidOperationException('批量库存检查失败: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    private function validateInput(array $items): void
    {
        $this->validateItemsExist($items);
        $this->validateItemsCount($items);
        $this->validateItemsStructure($items);
        $this->validateUniqueSkuIds($items);
    }

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    private function validateItemsExist(array $items): void
    {
        if ([] === $items) {
            throw new InvalidArgumentException('检查项目列表不能为空');
        }
    }

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    private function validateItemsCount(array $items): void
    {
        if (count($items) > 1000) {
            throw new InvalidOperationException('单次批量检查不能超过1000个项目');
        }
    }

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    private function validateItemsStructure(array $items): void
    {
        foreach ($items as $index => $item) {
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

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    private function validateUniqueSkuIds(array $items): void
    {
        $skuIds = array_column($items, 'skuId');
        if (count($skuIds) !== count(array_unique($skuIds))) {
            throw new InvalidArgumentException('检查项目列表包含重复的SKU ID');
        }
    }

    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     * @return array<StockCheckRequest>
     */
    private function parseRequests(array $items): array
    {
        $requests = [];
        foreach ($items as $item) {
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
            // stock_batches.available_quantity 是可用库存的权威值（已扣除预留/锁定等占用）
            $availableQuantity = $this->stockBatchRepository->getTotalAvailableQuantityBySkuId($request->skuId);
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
