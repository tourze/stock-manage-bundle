<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum StockAlertStatus: string implements Itemable, Labelable, Selectable
{
    use SelectTrait;
    use ItemTrait;

    case ACTIVE = 'active';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '激活',
            self::RESOLVED => '已解决',
            self::DISMISSED => '已忽略',
        };
    }
}
