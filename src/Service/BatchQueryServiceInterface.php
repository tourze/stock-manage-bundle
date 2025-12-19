<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 批次查询服务接口.
 *
 * 为跨模块访问批次数据提供统一接口
 */
interface BatchQueryServiceInterface
{
    /**
     * 获取所有批次
     *
     * @return StockBatch[]
     */
    public function getAllBatches(): array;

    /**
     * 根据批次号查找批次
     */
    public function findBatchByNo(string $batchNo): ?StockBatch;

    /**
     * 根据SKU ID查找批次
     *
     * @return StockBatch[]
     */
    public function findBatchesBySkuId(string $skuId): array;

    /**
     * 根据位置ID查找批次
     *
     * @return StockBatch[]
     */
    public function findBatchesByLocationId(string $locationId): array;

    /**
     * 获取指定SKU ID的总可用数量.
     */
    public function getTotalAvailableQuantity(string $skuId): int;

    /**
     * 查找即将过期的批次（默认30天内）.
     *
     * @return StockBatch[]
     */
    public function findBatchesExpiringSoon(int $days = 30): array;

    /**
     * 根据批次ID查找批次.
     *
     * @param int[] $batchIds
     *
     * @return StockBatch[]
     */
    public function findByBatchIds(array $batchIds): array;

    /**
     * 根据SKU查找可用批次.
     *
     * @param SKU $sku
     *
     * @return StockBatch[]
     */
    public function findAvailableBySku(SKU $sku): array;
}
