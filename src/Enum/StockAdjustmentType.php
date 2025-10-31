<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockAdjustmentType: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case INVENTORY_COUNT = 'inventory_count';
    case DAMAGE = 'damage';
    case EXPIRY = 'expiry';
    case CORRECTION = 'correction';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::INVENTORY_COUNT => '盘点调整',
            self::DAMAGE => '损坏调整',
            self::EXPIRY => '过期调整',
            self::CORRECTION => '错误更正',
            self::OTHER => '其他调整',
        };
    }
}
