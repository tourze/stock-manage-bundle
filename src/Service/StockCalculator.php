<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Model\StockSummary;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 库存计算器 - 负责库存统计和计算相关操作.
 */
readonly class StockCalculator
{
    public function __construct(
        private StockBatchRepository $repository,
        private SkuLoaderInterface $skuLoader,
    ) {
    }

    /**
     * 获取可用库存.
     */
    public function getAvailableStock(SKU $sku): StockSummary
    {
        $batches = $this->repository->findAvailableBySku($sku);
        $summary = new StockSummary($sku->getId());
        $totalValue = 0;

        foreach ($batches as $batch) {
            $this->aggregateBatchToSummary($summary, $batch);
            $totalValue += $batch->getQuantity() * $batch->getUnitCost();
            $this->addBatchToSummary($summary, $batch);
        }

        $summary->setTotalValue($totalValue);

        return $summary;
    }

    /**
     * 获取库存汇总.
     *
     * @param array<string> $spuIds
     *
     * @return array<StockSummary>
     */
    public function getStockSummary(array $spuIds = []): array
    {
        $summaries = [];
        $batchSummaries = $this->repository->getBatchSummary($spuIds);

        foreach ($batchSummaries as $spuId => $data) {
            $summary = $this->createSummaryFromData($spuId, $data);
            $summaries[$spuId] = $summary;
        }

        return $summaries;
    }

    /**
     * 检查库存可用性.
     */
    public function checkStockAvailability(SKU $sku, int $quantity): bool
    {
        $batches = $this->repository->findAvailableBySku($sku);
        $totalAvailable = 0;

        foreach ($batches as $batch) {
            $totalAvailable += $batch->getAvailableQuantity();
            if ($totalAvailable >= $quantity) {
                return true;
            }
        }

        return $totalAvailable >= $quantity;
    }

    /**
     * 获取批次详情.
     *
     * @return array<array<string, mixed>>
     */
    public function getBatchDetails(SKU $sku): array
    {
        $batches = $this->repository->findBySku($sku);
        $details = [];

        foreach ($batches as $batch) {
            $details[] = $this->formatBatchDetails($batch);
        }

        return $details;
    }

    /**
     * 获取库存统计
     *
     * @return array<string, mixed>
     */
    public function getStockStats(): array
    {
        $stats = $this->repository->getTotalStockStats();

        return [
            'totalSkus' => $stats['total_skus'],
            'totalQuantity' => $stats['total_quantity'],
            'totalAvailable' => $stats['total_available'],
            'totalReserved' => $stats['total_reserved'],
            'totalLocked' => $stats['total_locked'],
            'expiredBatches' => $stats['expired_batches'],
            'utilizationRate' => $this->calculateUtilizationRate($stats),
        ];
    }

    /**
     * 获取SKU的有效库存数量.
     */
    public function getValidStock(SKU $sku): int
    {
        $summary = $this->getAvailableStock($sku);

        return $summary->getAvailableQuantity();
    }

    /**
     * 聚合批次数据到摘要
     */
    private function aggregateBatchToSummary(StockSummary $summary, StockBatch $batch): void
    {
        $summary->setTotalQuantity($summary->getTotalQuantity() + $batch->getQuantity());
        $summary->setAvailableQuantity($summary->getAvailableQuantity() + $batch->getAvailableQuantity());
        $summary->setReservedQuantity($summary->getReservedQuantity() + $batch->getReservedQuantity());
        $summary->setLockedQuantity($summary->getLockedQuantity() + $batch->getLockedQuantity());
        $summary->setTotalBatches($summary->getTotalBatches() + 1);
    }

    /**
     * 添加批次到摘要
     */
    private function addBatchToSummary(StockSummary $summary, StockBatch $batch): void
    {
        $summary->addBatch([
            'batchNo' => $batch->getBatchNo(),
            'quantity' => $batch->getQuantity(),
            'availableQuantity' => $batch->getAvailableQuantity(),
            'unitCost' => $batch->getUnitCost(),
            'qualityLevel' => $batch->getQualityLevel(),
            'locationId' => $batch->getLocationId(),
            'status' => $batch->getStatus(),
        ]);
    }

    /**
     * 从数据创建摘要
     *
     * @param array<string, mixed> $data
     */
    private function createSummaryFromData(string $spuId, array $data): StockSummary
    {
        $summary = new StockSummary($spuId);

        $this->validateSummaryData($data);
        $this->populateSummaryFromData($summary, $data);
        $this->enrichSummaryWithBatchDetails($summary, $spuId);

        return $summary;
    }

    /**
     * 验证摘要数据.
     *
     * @param array<string, mixed> $data
     */
    private function validateSummaryData(array $data): void
    {
        $requiredFields = ['total_quantity', 'total_available', 'total_reserved', 'total_locked', 'total_batches'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (!is_int($data[$field]) && !is_numeric($data[$field]))) {
                throw new \InvalidArgumentException(sprintf('Invalid %s in data', $field));
            }
        }
    }

    /**
     * 从数据填充摘要.
     *
     * @param array<string, mixed> $data
     */
    private function populateSummaryFromData(StockSummary $summary, array $data): void
    {
        $summary->setTotalQuantity($this->ensureInt($data['total_quantity'] ?? 0));
        $summary->setAvailableQuantity($this->ensureInt($data['total_available'] ?? 0));
        $summary->setReservedQuantity($this->ensureInt($data['total_reserved'] ?? 0));
        $summary->setLockedQuantity($this->ensureInt($data['total_locked'] ?? 0));
        $summary->setTotalBatches($this->ensureInt($data['total_batches'] ?? 0));
    }

    /**
     * 用批次详情丰富摘要.
     */
    private function enrichSummaryWithBatchDetails(StockSummary $summary, string $spuId): void
    {
        $sku = $this->skuLoader->loadSkuByIdentifier($spuId);
        if (null === $sku) {
            return;
        }

        $batchData = $this->processBatchDataForSummary($sku);
        $summary->setTotalValue($batchData['totalValue']);

        foreach ($batchData['batchDetails'] as $batchDetail) {
            $summary->addBatch($batchDetail);
        }
    }

    /**
     * 处理摘要的批次数据.
     *
     * @return array{totalValue: float, batchDetails: array<array<string, mixed>>}
     */
    private function processBatchDataForSummary(SKU $sku): array
    {
        $batches = $this->repository->findBySku($sku);
        $totalValue = 0;
        $batchDetails = [];

        foreach ($batches as $batch) {
            $batchValue = $batch->getQuantity() * $batch->getUnitCost();
            $totalValue += $batchValue;

            $batchDetails[] = [
                'batchNo' => $batch->getBatchNo(),
                'quantity' => $batch->getQuantity(),
                'availableQuantity' => $batch->getAvailableQuantity(),
                'unitCost' => $batch->getUnitCost(),
            ];
        }

        return [
            'totalValue' => $totalValue,
            'batchDetails' => $batchDetails,
        ];
    }

    /**
     * 格式化批次详情.
     *
     * @return array<string, mixed>
     */
    private function formatBatchDetails(StockBatch $batch): array
    {
        return [
            'id' => $batch->getId(),
            'batchNo' => $batch->getBatchNo(),
            'quantity' => $batch->getQuantity(),
            'availableQuantity' => $batch->getAvailableQuantity(),
            'reservedQuantity' => $batch->getReservedQuantity(),
            'lockedQuantity' => $batch->getLockedQuantity(),
            'unitCost' => $batch->getUnitCost(),
            'qualityLevel' => $batch->getQualityLevel(),
            'status' => $batch->getStatus(),
            'locationId' => $batch->getLocationId(),
            'productionDate' => $batch->getProductionDate()?->format('Y-m-d'),
            'expiryDate' => $batch->getExpiryDate()?->format('Y-m-d'),
            'createTime' => $batch->getCreateTime()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * 计算利用率.
     *
     * @param array<string, mixed> $stats
     */
    private function calculateUtilizationRate(array $stats): float
    {
        if (!isset($stats['total_quantity']) || (!is_numeric($stats['total_quantity']))) {
            throw new \InvalidArgumentException('Invalid total_quantity in stats');
        }
        if (!isset($stats['total_available']) || (!is_numeric($stats['total_available']))) {
            throw new \InvalidArgumentException('Invalid total_available in stats');
        }

        $totalQuantity = (float) $stats['total_quantity'];
        $totalAvailable = (float) $stats['total_available'];

        return $totalQuantity > 0
            ? ($totalQuantity - $totalAvailable) / $totalQuantity * 100
            : 0;
    }

    /**
     * 确保值为整数类型.
     */
    private function ensureInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        throw new \InvalidArgumentException(sprintf('Cannot convert value "%s" to integer', gettype($value)));
    }
}
