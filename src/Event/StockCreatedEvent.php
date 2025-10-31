<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

/**
 * 库存创建事件
 * 当新的库存批次被创建时触发.
 */
class StockCreatedEvent extends AbstractStockEvent
{
    public function getEventType(): string
    {
        return 'stock.created';
    }
}
