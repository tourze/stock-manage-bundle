<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Model\StockSummary;

/**
 * 库存服务 - 作为主要接口，协调各个专门的服务组件.
 *
 * 按照 Linus "Good Taste" 原则重构：
 * 1. 每个组件职责单一
 * 2. 消除了原本的深层嵌套和复杂条件分支
 * 3. 数据流清晰，无特殊情况处理
 */
class StockService implements StockServiceInterface
{
    public function __construct(
        private BatchManager $batchManager,
        private StockCalculator $stockCalculator,
        private StockOperator $stockOperator,
    ) {
    }

    /**
     * 创建新的库存批次
     */
    public function createBatch(array $data): StockBatch
    {
        return $this->batchManager->createBatch($data);
    }

    /**
     * 合并多个批次
     */
    public function mergeBatches(array $batches, string $newBatchNo): StockBatch
    {
        return $this->batchManager->mergeBatches($batches, $newBatchNo);
    }

    /**
     * 拆分批次
     */
    public function splitBatch(StockBatch $batch, int $splitQuantity, string $newBatchNo): StockBatch
    {
        return $this->batchManager->splitBatch($batch, $splitQuantity, $newBatchNo);
    }

    /**
     * 更新批次状态
     */
    public function updateBatchStatus(StockBatch $batch, string $status): void
    {
        $this->batchManager->updateBatchStatus($batch, $status);
    }

    /**
     * 调整批次数量.
     */
    public function adjustBatchQuantity(StockBatch $batch, int $quantityAdjustment, string $reason): void
    {
        $this->batchManager->adjustBatchQuantity($batch, $quantityAdjustment);
    }

    /**
     * 获取可用库存.
     */
    public function getAvailableStock(SKU $sku, array $criteria = []): StockSummary
    {
        return $this->stockCalculator->getAvailableStock($sku);
    }

    /**
     * 获取库存汇总.
     *
     * @param array<mixed> $spuIds
     *
     * @return array<mixed>
     */
    public function getStockSummary(array $spuIds = []): array
    {
        /** @var array<string> $typedSpuIds */
        $typedSpuIds = array_filter($spuIds, 'is_string');

        return $this->stockCalculator->getStockSummary($typedSpuIds);
    }

    /**
     * 检查库存可用性.
     */
    public function checkStockAvailability(SKU $sku, int $quantity, array $criteria = []): bool
    {
        return $this->stockCalculator->checkStockAvailability($sku, $quantity);
    }

    /**
     * 获取批次详情.
     *
     * @return array<array<string, mixed>>
     */
    public function getBatchDetails(SKU $sku): array
    {
        return $this->stockCalculator->getBatchDetails($sku);
    }

    /**
     * 获取库存统计
     *
     * @return array<string, mixed>
     */
    public function getStockStats(): array
    {
        return $this->stockCalculator->getStockStats();
    }

    /**
     * 获取SKU的有效库存数量.
     */
    public function getValidStock(SKU $sku): int
    {
        return $this->stockCalculator->getValidStock($sku);
    }

    /**
     * 批量处理库存日志.
     *
     * @param array<mixed> $logs
     */
    public function batchProcess(array $logs): void
    {
        /** @var array<StockLog> $typedLogs */
        $typedLogs = array_filter($logs, fn ($log) => $log instanceof StockLog);
        $this->stockOperator->batchProcess($typedLogs);
    }

    /**
     * 处理单个库存日志.
     */
    public function process(StockLog $log): void
    {
        $this->stockOperator->process($log);
    }
}
