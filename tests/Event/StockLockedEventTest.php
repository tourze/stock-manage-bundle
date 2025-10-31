<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockLockedEvent;

/**
 * @internal
 */
#[CoversClass(StockLockedEvent::class)]
class StockLockedEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-id');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);

        return new StockLockedEvent($stockBatch, 'order_lock', 15, 'ORDER456', 'system');
    }

    public function testEventCreation(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-001');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);
        $lockType = 'order_lock';
        $lockedQuantity = 15;
        $reason = 'ORDER456';
        $operator = 'system';

        $event = new StockLockedEvent(
            $stockBatch,
            $lockType,
            $lockedQuantity,
            $reason,
            $operator
        );

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($lockType, $event->getLockType());
        $this->assertSame($lockedQuantity, $event->getLockedQuantity());
        $this->assertSame($reason, $event->getReason());
        $this->assertSame($operator, $event->getOperator());
        $this->assertSame('stock.locked', $event->getEventType());
    }

    public function testEventCreationWithOptionalParameters(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-002');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH002');
        $stockBatch->setQuantity(50);
        $lockType = 'reservation_lock';
        $lockedQuantity = 8;
        $reason = 'test_reason';

        $event = new StockLockedEvent($stockBatch, $lockType, $lockedQuantity, $reason);

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($lockType, $event->getLockType());
        $this->assertSame($lockedQuantity, $event->getLockedQuantity());
        $this->assertSame($reason, $event->getReason());
        $this->assertNull($event->getOperator());
        $this->assertSame('stock.locked', $event->getEventType());
    }

    public function testEventType(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-003');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH003');
        $stockBatch->setQuantity(25);
        $event = new StockLockedEvent($stockBatch, 'test', 1, 'test_reason');

        $this->assertSame('stock.locked', $event->getEventType());
    }

    public function testToArray(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-004');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH004');
        $stockBatch->setQuantity(200);

        $event = new StockLockedEvent($stockBatch, 'order_lock', 15, 'ORDER456', 'system');

        $result = $event->toArray();

        $this->assertIsArray($result);
        $this->assertEquals('order_lock', $result['lock_type']);
        $this->assertEquals(15, $result['locked_quantity']);
        $this->assertEquals('ORDER456', $result['reason']);
    }
}
