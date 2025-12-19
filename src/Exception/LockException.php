<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 锁定相关异常
 * 当库存锁定、解锁操作失败时抛出.
 */
final class LockException extends AbstractStockException
{
}
