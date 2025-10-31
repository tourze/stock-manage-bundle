<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Exception\BatchNotFoundException;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Model\StockSummary;

interface StockServiceInterface
{
    /**
     * 创建库存批次
     *
     * @param array{
     *     sku: SKU,
     *     batch_no?: string,
     *     quantity: int,
     *     unit_cost?: float,
     *     quality_level?: string,
     *     production_date?: \DateTimeInterface,
     *     expiry_date?: \DateTimeInterface,
     *     location_id?: string,
     *     attributes?: array<string, mixed>
     * } $data
     *
     * @throws \InvalidArgumentException
     * @throws DuplicateBatchException
     */
    public function createBatch(array $data): StockBatch;

    /**
     * 获取可用库存.
     *
     * @param array{
     *     location_id?: string,
     *     quality_level?: string,
     *     exclude_expired?: bool,
     *     include_reserved?: bool
     * } $criteria
     */
    public function getAvailableStock(SKU $sku, array $criteria = []): StockSummary;

    /**
     * 批次合并.
     *
     * @param StockBatch[] $batches
     *
     * @throws BatchNotFoundException
     * @throws \InvalidArgumentException
     */
    public function mergeBatches(array $batches, string $targetBatchNo): StockBatch;

    /**
     * 批次拆分.
     *
     * @throws BatchNotFoundException
     * @throws InsufficientStockException
     */
    public function splitBatch(StockBatch $batch, int $splitQuantity, string $newBatchNo): StockBatch;

    /**
     * 检查库存可用性.
     *
     * @param array<string, mixed> $criteria
     */
    public function checkStockAvailability(SKU $sku, int $quantity, array $criteria = []): bool;

    /**
     * 获取批次详情.
     *
     * @return array<array<string, mixed>>
     */
    public function getBatchDetails(SKU $sku): array;

    /**
     * 获取库存统计
     *
     * @return array<string, mixed>
     */
    public function getStockStats(): array;

    /**
     * 处理库存变化日志.
     *
     * @throws \InvalidArgumentException
     */
    public function process(StockLog $log): void;
}
