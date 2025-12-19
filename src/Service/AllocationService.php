<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\AllocationStrategy\AllocationStrategyInterface;
use Tourze\StockManageBundle\Service\AllocationStrategy\FefoStrategy;
use Tourze\StockManageBundle\Service\AllocationStrategy\FifoStrategy;
use Tourze\StockManageBundle\Service\AllocationStrategy\LifoStrategy;

final class AllocationService
{
    /** @var AllocationStrategyInterface[] */
    private array $strategies = [];

    public function __construct(
        private StockBatchRepository $repository,
    ) {
        // 注册默认策略
        $this->registerStrategy(new FifoStrategy());
        $this->registerStrategy(new LifoStrategy());
        $this->registerStrategy(new FefoStrategy());
    }

    /**
     * 注册分配策略.
     */
    public function registerStrategy(AllocationStrategyInterface $strategy): void
    {
        $this->strategies[$strategy->getName()] = $strategy;
    }

    /**
     * 分配库存.
     *
     * @return array{
     *   sku: SKU,
     *   totalQuantity: int,
     *   strategy: string,
     *   batches: array<array{
     *     batchNo: string,
     *     quantity: int,
     *     unitCost: float,
     *     qualityLevel: string,
     *     locationId: ?int,
     *     batchId?: int,
     *     expiryDate?: string|null
     *   }>
     * }
     */
    public function allocate(SKU $sku, int $quantity, string $strategy = 'fifo'): array
    {
        $this->validateAllocationRequest($sku, $quantity, $strategy);
        $sortedBatches = $this->getAndValidateBatches($sku, $quantity, $strategy);
        $allocatedBatches = $this->performAllocation($sortedBatches, $quantity);

        return [
            'sku' => $sku,
            'totalQuantity' => $quantity,
            'strategy' => $strategy,
            'batches' => $allocatedBatches,
        ];
    }

    /**
     * 验证分配请求.
     */
    private function validateAllocationRequest(SKU $sku, int $quantity, string $strategy): void
    {
        if (!isset($this->strategies[$strategy])) {
            throw new InvalidArgumentException(sprintf('不支持的分配策略: %s', $strategy));
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('分配数量必须大于0');
        }
    }

    /**
     * 获取并验证批次.
     *
     * @return array<StockBatch>
     */
    private function getAndValidateBatches(SKU $sku, int $quantity, string $strategy): array
    {
        $batches = $this->repository->findAvailableBySku($sku);

        if ([] === $batches) {
            throw new InsufficientStockException('没有可用的库存批次');
        }

        $strategyInstance = $this->strategies[$strategy];
        $sortedBatches = $strategyInstance->sortBatches($batches);

        $this->validateStockSufficiency($sortedBatches, $sku, $quantity);

        return $sortedBatches;
    }

    /**
     * 验证库存是否充足.
     *
     * @param array<StockBatch> $sortedBatches
     */
    private function validateStockSufficiency(array $sortedBatches, SKU $sku, int $quantity): void
    {
        $totalAvailable = 0;
        foreach ($sortedBatches as $batch) {
            $totalAvailable += $batch->getAvailableQuantity();
        }

        if ($totalAvailable < $quantity) {
            throw InsufficientStockException::createBySku($sku->getId(), $quantity, $totalAvailable);
        }
    }

    /**
     * 执行分配.
     *
     * @param array<StockBatch> $sortedBatches
     * @return array<array{batchId: int, batchNo: string, quantity: int, unitCost: float, qualityLevel: string, locationId: int|null, expiryDate: string|null}>
     */
    private function performAllocation(array $sortedBatches, int $quantity): array
    {
        $remaining = $quantity;
        $allocatedBatches = [];

        foreach ($sortedBatches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->getAvailableQuantity();
            if ($available <= 0) {
                continue;
            }

            $allocQuantity = min($remaining, $available);

            $allocatedBatches[] = [
                'batchId' => $batch->getId() ?? 0,
                'batchNo' => $batch->getBatchNo(),
                'quantity' => $allocQuantity,
                'unitCost' => $batch->getUnitCost(),
                'qualityLevel' => $batch->getQualityLevel(),
                'locationId' => null !== $batch->getLocationId() ? (int) $batch->getLocationId() : null,
                'expiryDate' => null !== $batch->getExpiryDate() ? $batch->getExpiryDate()->format('Y-m-d H:i:s') : null,
            ];

            $remaining -= $allocQuantity;
        }

        return $allocatedBatches;
    }

    /**
     * 预留库存（不实际扣减，返回分配方案）.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array{
     *   sku: SKU,
     *   requestedQuantity: int,
     *   allocatedQuantity: int,
     *   strategy: string,
     *   batches: array<array{
     *     batchId: int,
     *     batchNo: string,
     *     quantity: int,
     *     unitCost: float,
     *     qualityLevel: string,
     *     locationId: int|null,
     *     expiryDate: string|null
     *   }>
     * }
     */
    public function calculateAllocation(SKU $sku, int $quantity, string $strategy = 'fifo', array $criteria = []): array
    {
        if (!isset($this->strategies[$strategy])) {
            throw new InvalidArgumentException(sprintf('不支持的分配策略: %s', $strategy));
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('分配数量必须大于0');
        }

        // 获取可用批次，支持额外条件
        $batches = $this->repository->findAvailableBySku($sku);

        if ([] === $batches) {
            throw new InsufficientStockException('没有可用的库存批次');
        }

        // 根据策略排序
        $strategyInstance = $this->strategies[$strategy];
        $sortedBatches = $strategyInstance->sortBatches($batches);

        // 计算分配方案
        $remaining = $quantity;
        $allocation = [];
        $allocatedBatches = [];

        foreach ($sortedBatches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = $batch->getAvailableQuantity();
            if ($available <= 0) {
                continue;
            }

            $allocQuantity = min($remaining, $available);

            $allocatedBatches[] = [
                'batchId' => $batch->getId() ?? 0,
                'batchNo' => $batch->getBatchNo(),
                'quantity' => $allocQuantity,
                'unitCost' => $batch->getUnitCost(),
                'qualityLevel' => $batch->getQualityLevel(),
                'locationId' => null !== $batch->getLocationId() ? (int) $batch->getLocationId() : null,
                'expiryDate' => null !== $batch->getExpiryDate() ? $batch->getExpiryDate()->format('Y-m-d H:i:s') : null,
            ];

            $remaining -= $allocQuantity;
        }

        // 检查是否有足够的库存
        if ($remaining > 0) {
            $allocatedQuantity = $quantity - $remaining;
            throw InsufficientStockException::createBySku($sku->getId(), $quantity, $allocatedQuantity);
        }

        return [
            'sku' => $sku,
            'requestedQuantity' => $quantity,
            'allocatedQuantity' => $quantity,
            'strategy' => $strategy,
            'batches' => $allocatedBatches,
        ];
    }

    /**
     * 获取所有可用策略.
     *
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
