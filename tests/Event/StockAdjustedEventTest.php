<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;

/**
 * @internal
 */
#[CoversClass(StockAdjustedEvent::class)]
class StockAdjustedEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        return new StockAdjustedEvent($batch, 'damage', -5, 'Test reason');
    }

    public function testEventCreation(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $adjustmentType = 'damage';
        $adjustmentQuantity = -5;
        $reason = 'Damaged during transport';
        $operator = 'warehouse_manager';
        $metadata = ['location' => 'A-001', 'inspector' => 'INSP001'];

        $event = new StockAdjustedEvent($batch, $adjustmentType, $adjustmentQuantity, $reason, $operator, $metadata);

        $this->assertSame($batch, $event->getStockBatch());
        $this->assertEquals('stock.adjusted', $event->getEventType());
        $this->assertEquals($adjustmentType, $event->getAdjustmentType());
        $this->assertEquals($adjustmentQuantity, $event->getAdjustmentQuantity());
        $this->assertEquals($reason, $event->getReason());
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

        $event = new StockAdjustedEvent($batch, 'count', 3, 'Inventory count adjustment');

        $this->assertNull($event->getOperator());
        $this->assertEmpty($event->getMetadata());
    }

    public function testPositiveAdjustment(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU003');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH003');
        $batch->setQuantity(100);

        $event = new StockAdjustedEvent($batch, 'found', 10, 'Found additional stock');

        $this->assertEquals(10, $event->getAdjustmentQuantity());
        $this->assertTrue($event->getAdjustmentQuantity() > 0);
    }

    public function testNegativeAdjustment(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU004');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH004');
        $batch->setQuantity(100);

        $event = new StockAdjustedEvent($batch, 'shrinkage', -8, 'Stock shrinkage');

        $this->assertEquals(-8, $event->getAdjustmentQuantity());
        $this->assertTrue($event->getAdjustmentQuantity() < 0);
    }

    public function testToArray(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU005');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH005');
        $batch->setQuantity(200);

        $event = new StockAdjustedEvent($batch, 'recount', 2, 'Recount adjustment', 'admin');

        $array = $event->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('stock.adjusted', $array['type']);
        $this->assertArrayHasKey('adjustment_type', $array);
        $this->assertEquals('recount', $array['adjustment_type']);
        $this->assertArrayHasKey('adjustment_quantity', $array);
        $this->assertEquals(2, $array['adjustment_quantity']);
        $this->assertArrayHasKey('reason', $array);
        $this->assertEquals('Recount adjustment', $array['reason']);
        $this->assertArrayHasKey('spu_id', $array);
        $this->assertEquals('SPU005', $array['spu_id']);
        $this->assertArrayHasKey('occurred_at', $array);
        $this->assertIsString($array['occurred_at']);
        $occurredAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $array['occurred_at']);
        $this->assertInstanceOf(\DateTimeInterface::class, $occurredAt);
    }
}
