<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;

/**
 * 预警服务接口
 * 定义库存预警功能的基本契约.
 */
interface AlertServiceInterface
{
    /**
     * 发送低库存预警.
     */
    public function sendLowStockAlert(SKU $sku, int $currentQuantity, int $threshold): void;
}
