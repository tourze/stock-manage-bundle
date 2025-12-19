<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 无效操作异常
 * 当执行的操作不被允许或不符合业务规则时抛出.
 */
final class InvalidOperationException extends AbstractStockException
{
}
