<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockOutboundEvent;

/**
 * @internal
 */
#[CoversClass(StockOutboundEvent::class)]
class StockOutboundEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        return new StockOutboundEvent($batch, 'sale', 10, 'SO001', 'user_123', ['channel' => 'online', 'customer_id' => 'CUST001']);
    }

    public function testEventCreation(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $outboundType = 'sale';
        $outboundQuantity = 10;
        $orderNo = 'SO001';
        $operator = 'user_123';
        $metadata = ['channel' => 'online', 'customer_id' => 'CUST001'];

        $event = new StockOutboundEvent($batch, $outboundType, $outboundQuantity, $orderNo, $operator, $metadata);

        $this->assertSame($batch, $event->getStockBatch());
        $this->assertEquals('stock.outbound', $event->getEventType());
        $this->assertEquals($outboundType, $event->getOutboundType());
        $this->assertEquals($outboundQuantity, $event->getOutboundQuantity());
        $this->assertEquals($orderNo, $event->getOrderNo());
        $this->assertEquals($operator, $event->getOperator());
        $this->assertEquals($metadata, $event->getMetadata());
    }

    public function testOptionalParameters(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU002');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH002');
        $batch->setQuantity(50);

        $event = new StockOutboundEvent($batch, 'sale', 5);

        $this->assertNull($event->getOrderNo());
        $this->assertNull($event->getOperator());
        $this->assertEmpty($event->getMetadata());
    }

    public function testToArray(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU003');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH003');
        $batch->setQuantity(200);

        $event = new StockOutboundEvent($batch, 'transfer', 20, 'TF001', 'admin');

        $array = $event->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('stock.outbound', $array['type']);
        $this->assertArrayHasKey('outbound_type', $array);
        $this->assertEquals('transfer', $array['outbound_type']);
        $this->assertArrayHasKey('outbound_quantity', $array);
        $this->assertEquals(20, $array['outbound_quantity']);
        $this->assertArrayHasKey('order_no', $array);
        $this->assertEquals('TF001', $array['order_no']);
        $this->assertArrayHasKey('spu_id', $array);
        $this->assertEquals('SPU003', $array['spu_id']);
        $this->assertArrayHasKey('occurred_at', $array);
        $this->assertIsString($array['occurred_at']);
        $occurredAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $array['occurred_at']);
        $this->assertInstanceOf(\DateTimeInterface::class, $occurredAt);
    }
}
