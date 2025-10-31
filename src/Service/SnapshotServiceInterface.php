<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockSnapshot;

/**
 * 快照服务接口
 * 定义库存快照功能的基本契约.
 */
interface SnapshotServiceInterface
{
    /**
     * 创建库存快照.
     *
     * @param array<string, mixed> $metadata
     */
    public function createSnapshot(SKU $sku, int $quantity, string $type, array $metadata = []): StockSnapshot;
}
