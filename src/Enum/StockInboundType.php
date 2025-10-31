<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockInboundType: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case PURCHASE = 'purchase';
    case RETURN = 'return';
    case TRANSFER = 'transfer';
    case PRODUCTION = 'production';
    case ADJUSTMENT = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::PURCHASE => '采购入库',
            self::RETURN => '退货入库',
            self::TRANSFER => '调拨入库',
            self::PRODUCTION => '生产入库',
            self::ADJUSTMENT => '调整入库',
        };
    }
}
