<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockAllocatedEvent;

/**
 * @internal
 */
#[CoversClass(StockAllocatedEvent::class)]
class StockAllocatedEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-id');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);

        return new StockAllocatedEvent($stockBatch, 10, 'order_allocation', 'test_user');
    }

    public function testEventCreation(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-001');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);
        $strategy = 'order_allocation';
        $allocatedQuantity = 10;
        $operator = 'test_user';

        $event = new StockAllocatedEvent(
            $stockBatch,
            $allocatedQuantity,
            $strategy,
            $operator
        );

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($allocatedQuantity, $event->getAllocatedQuantity());
        $this->assertSame($strategy, $event->getStrategy());
        $this->assertSame($operator, $event->getOperator());
        $this->assertSame('stock.allocated', $event->getEventType());
    }

    public function testEventCreationWithOptionalParameters(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-002');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH002');
        $stockBatch->setQuantity(50);
        $strategy = 'reservation';
        $allocatedQuantity = 5;

        $event = new StockAllocatedEvent($stockBatch, $allocatedQuantity, $strategy);

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($allocatedQuantity, $event->getAllocatedQuantity());
        $this->assertSame($strategy, $event->getStrategy());
        $this->assertNull($event->getOperator());
        $this->assertSame('stock.allocated', $event->getEventType());
    }

    public function testEventType(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-003');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH003');
        $stockBatch->setQuantity(25);
        $event = new StockAllocatedEvent($stockBatch, 1, 'test');

        $this->assertSame('stock.allocated', $event->getEventType());
    }

    public function testToArray(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-004');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH004');
        $stockBatch->setQuantity(200);
        $strategy = 'order_allocation';
        $allocatedQuantity = 10;
        $operator = 'test_user';

        $event = new StockAllocatedEvent(
            $stockBatch,
            $allocatedQuantity,
            $strategy,
            $operator
        );

        $result = $event->toArray();

        $this->assertIsArray($result);
        $this->assertEquals($allocatedQuantity, $result['allocated_quantity']);
        $this->assertEquals($strategy, $result['strategy']);
    }
}
