<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

/**
 * 库存管理抽象异常基类
 * 所有库存相关的异常都应该继承此抽象类，以符合静态分析规则.
 */
abstract class AbstractStockException extends \RuntimeException
{
}
