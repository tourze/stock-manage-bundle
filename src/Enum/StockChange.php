<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 库存变动类型.
 */
enum StockChange: string implements Itemable, Labelable, Selectable, BadgeInterface
{
    use SelectTrait;
    use ItemTrait;

    /** 锁定库存 */
    case LOCK = 'lock';

    /** 解锁库存 */
    case UNLOCK = 'unlock';

    /** 扣除库存 */
    case DEDUCT = 'deduct';

    /** 返还库存 */
    case RETURN = 'return';

    /** 入库 */
    case INBOUND = 'inbound';

    /** 出库 */
    case OUTBOUND = 'outbound';

    /** 调整库存 */
    case ADJUSTMENT = 'adjustment';

    /** 入库操作 */
    case PUT = 'put';

    /** 预留库存 */
    case RESERVED = 'reserved';

    /** 释放预留 */
    case RESERVED_RELEASE = 'reserved_release';

    public function getLabel(): string
    {
        return $this->getDescription();
    }

    /**
     * 获取描述.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::LOCK => '锁定库存',
            self::UNLOCK => '解锁库存',
            self::DEDUCT => '扣除库存',
            self::RETURN => '返还库存',
            self::INBOUND => '入库',
            self::OUTBOUND => '出库',
            self::ADJUSTMENT => '调整库存',
            self::PUT => '入库操作',
            self::RESERVED => '预留库存',
            self::RESERVED_RELEASE => '释放预留',
        };
    }

    /**
     * 是否为减少库存的操作.
     */
    public function isDecrease(): bool
    {
        return in_array($this, [self::LOCK, self::DEDUCT, self::OUTBOUND, self::RESERVED], true);
    }

    /**
     * 是否为增加库存的操作.
     */
    public function isIncrease(): bool
    {
        return in_array($this, [self::UNLOCK, self::RETURN, self::INBOUND, self::PUT, self::RESERVED_RELEASE], true);
    }

    /**
     * 获取Badge样式.
     */
    public function getBadge(): string
    {
        return match ($this) {
            self::LOCK => self::WARNING,
            self::UNLOCK => self::SUCCESS,
            self::DEDUCT => self::DANGER,
            self::RETURN => self::SUCCESS,
            self::INBOUND => self::SUCCESS,
            self::OUTBOUND => self::PRIMARY,
            self::ADJUSTMENT => self::INFO,
            self::PUT => self::SUCCESS,
            self::RESERVED => self::WARNING,
            self::RESERVED_RELEASE => self::SUCCESS,
        };
    }
}
