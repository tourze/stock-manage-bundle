<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockTransferStatus: string implements Itemable, Labelable, Selectable, BadgeInterface
{
    use SelectTrait;
    use ItemTrait;

    case PENDING = 'pending';
    case IN_TRANSIT = 'in_transit';
    case RECEIVED = 'received';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待发货',
            self::IN_TRANSIT => '运输中',
            self::RECEIVED => '已接收',
            self::CANCELLED => '已取消',
        };
    }

    /**
     * 获取Badge样式.
     */
    public function getBadge(): string
    {
        return match ($this) {
            self::PENDING => self::WARNING,
            self::IN_TRANSIT => self::INFO,
            self::RECEIVED => self::SUCCESS,
            self::CANCELLED => self::DANGER,
        };
    }
}
