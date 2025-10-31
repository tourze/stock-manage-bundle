<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockOutboundType: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case SALES = 'sales';
    case DAMAGE = 'damage';
    case TRANSFER = 'transfer';
    case PICK = 'pick';
    case ADJUSTMENT = 'adjustment';
    case SAMPLE = 'sample';

    public function getLabel(): string
    {
        return match ($this) {
            self::SALES => '销售出库',
            self::DAMAGE => '损坏出库',
            self::TRANSFER => '调拨出库',
            self::PICK => '拣货出库',
            self::ADJUSTMENT => '调整出库',
            self::SAMPLE => '样品出库',
        };
    }
}
