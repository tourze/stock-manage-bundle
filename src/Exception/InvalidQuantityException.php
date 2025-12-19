<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 无效数量异常
 * 当传入的数量不符合要求时抛出（如负数、零等）.
 */
final class InvalidQuantityException extends AbstractStockException
{
    public function __construct(int $quantity)
    {
        parent::__construct(sprintf('无效的数量: %d', $quantity));
    }

    public static function create(int $quantity): self
    {
        return new self($quantity);
    }
}
