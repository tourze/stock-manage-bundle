<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockReservationStatus: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case RELEASED = 'released';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待确认',
            self::CONFIRMED => '已确认',
            self::RELEASED => '已释放',
            self::EXPIRED => '已过期',
        };
    }
}
