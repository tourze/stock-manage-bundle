<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockInboundEvent;

/**
 * @internal
 */
#[CoversClass(StockInboundEvent::class)]
class StockInboundEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-id');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);

        return new StockInboundEvent($stockBatch, 'purchase', 50, 'PO123', 'warehouse_user');
    }

    public function testEventCreation(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-001');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);
        $inboundType = 'purchase';
        $inboundQuantity = 50;
        $orderNo = 'PO123';
        $operator = 'warehouse_user';

        $event = new StockInboundEvent(
            $stockBatch,
            $inboundType,
            $inboundQuantity,
            $orderNo,
            $operator
        );

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($inboundType, $event->getInboundType());
        $this->assertSame($inboundQuantity, $event->getInboundQuantity());
        $this->assertSame($orderNo, $event->getOrderNo());
        $this->assertSame($operator, $event->getOperator());
        $this->assertSame('stock.inbound', $event->getEventType());
    }

    public function testEventCreationWithOptionalParameters(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-002');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH002');
        $stockBatch->setQuantity(50);
        $inboundType = 'return';
        $inboundQuantity = 25;

        $event = new StockInboundEvent($stockBatch, $inboundType, $inboundQuantity);

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($inboundType, $event->getInboundType());
        $this->assertSame($inboundQuantity, $event->getInboundQuantity());
        $this->assertNull($event->getOrderNo());
        $this->assertNull($event->getOperator());
        $this->assertSame('stock.inbound', $event->getEventType());
    }

    public function testEventType(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-003');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH003');
        $stockBatch->setQuantity(25);
        $event = new StockInboundEvent($stockBatch, 'test', 1);

        $this->assertSame('stock.inbound', $event->getEventType());
    }

    public function testToArray(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-004');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH004');
        $stockBatch->setQuantity(200);
        $inboundType = 'purchase';
        $inboundQuantity = 50;
        $orderNo = 'PO123';
        $operator = 'warehouse_user';

        $event = new StockInboundEvent(
            $stockBatch,
            $inboundType,
            $inboundQuantity,
            $orderNo,
            $operator
        );

        $result = $event->toArray();

        $this->assertIsArray($result);
        $this->assertEquals($inboundType, $result['inbound_type']);
        $this->assertEquals($inboundQuantity, $result['inbound_quantity']);
        $this->assertEquals($orderNo, $result['order_no']);
    }
}
