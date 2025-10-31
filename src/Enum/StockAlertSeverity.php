<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockAlertSeverity: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case CRITICAL = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::LOW => '低',
            self::MEDIUM => '中',
            self::HIGH => '高',
            self::CRITICAL => '严重',
        };
    }
}
