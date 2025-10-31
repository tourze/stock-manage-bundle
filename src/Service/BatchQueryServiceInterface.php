<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

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
}
