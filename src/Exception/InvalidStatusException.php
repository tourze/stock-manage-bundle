<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 无效状态异常
 * 当对象的状态不允许执行特定操作时抛出.
 */
final class InvalidStatusException extends AbstractStockException
{
    public static function create(string $status): self
    {
        return new self(sprintf('无效的状态: %s', $status));
    }
}
