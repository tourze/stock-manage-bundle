<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 库存不足异常
 * 当请求的数量超过可用库存时抛出.
 */
final class InsufficientStockException extends AbstractStockException
{
    public static function create(string $spuId, int $required, int $available): self
    {
        return new self(sprintf(
            'Insufficient stock for SPU %s: required %d, available %d',
            $spuId,
            $required,
            $available
        ));
    }

    public static function createBySku(string $sku, int $required, int $available): self
    {
        return new self(sprintf(
            'Insufficient stock for SKU %s: required %d, available %d',
            $sku,
            $required,
            $available
        ));
    }
}
