<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockCreatedEvent;

/**
 * @internal
 */
#[CoversClass(StockCreatedEvent::class)]
class StockCreatedEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        return new StockCreatedEvent($batch, 'user_123', ['source' => 'purchase', 'order_id' => 'PO001']);
    }

    public function testEventCreation(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $operator = 'user_123';
        $metadata = ['source' => 'purchase', 'order_id' => 'PO001'];

        $event = new StockCreatedEvent($batch, $operator, $metadata);

        $this->assertSame($batch, $event->getStockBatch());
        $this->assertEquals($operator, $event->getOperator());
        $this->assertEquals($metadata, $event->getMetadata());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getOccurredAt());
        $this->assertEquals('SPU001', $event->getSpuId());
        $this->assertEquals(100, $event->getQuantity());
    }

    public function testEventWithoutOptionalParams(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(50);

        $event = new StockCreatedEvent($batch);

        $this->assertSame($batch, $event->getStockBatch());
        $this->assertNull($event->getOperator());
        $this->assertEmpty($event->getMetadata());
        $this->assertInstanceOf(\DateTimeInterface::class, $event->getOccurredAt());
        $this->assertEquals('SPU001', $event->getSpuId());
        $this->assertEquals(50, $event->getQuantity());
    }

    public function testEventSerialization(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $event = new StockCreatedEvent($batch, 'user_123', ['test' => 'data']);

        $eventData = $event->toArray();

        $this->assertIsArray($eventData);
        $this->assertEquals('stock.created', $eventData['type']);
        $this->assertEquals('SPU001', $eventData['spu_id']);
        $this->assertEquals(100, $eventData['quantity']);
        $this->assertEquals('user_123', $eventData['operator']);
        $this->assertArrayHasKey('occurred_at', $eventData);
        $this->assertArrayHasKey('metadata', $eventData);
    }
}
