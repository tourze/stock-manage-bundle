<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockAdjustmentStatus: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::COMPLETED => '已完成',
            self::CANCELLED => '已取消',
        };
    }
}
