<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockAlertType: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case LOW_STOCK = 'low_stock';
    case OUT_OF_STOCK = 'out_of_stock';
    case HIGH_STOCK = 'high_stock';
    case EXPIRY_WARNING = 'expiry_warning';
    case QUALITY_ISSUE = 'quality_issue';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW_STOCK => '库存不足',
            self::OUT_OF_STOCK => '缺货',
            self::HIGH_STOCK => '库存过多',
            self::EXPIRY_WARNING => '过期预警',
            self::QUALITY_ISSUE => '质量问题',
        };
    }
}
