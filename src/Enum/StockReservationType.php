<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockReservationType: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case ORDER = 'order';
    case PROMOTION = 'promotion';
    case VIP = 'vip';
    case SYSTEM = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::ORDER => '订单预定',
            self::PROMOTION => '促销预定',
            self::VIP => 'VIP预定',
            self::SYSTEM => '系统预定',
        };
    }
}
