<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockReservationBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Event\StockReservedEvent;

/**
 * @internal
 */
#[CoversClass(StockReservedEvent::class)]
class StockReservedEventTest extends AbstractEventTestCase
{
    protected function createEvent(): object
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-id');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);

        $reservation = new StockReservation();
        $reservation->setQuantity(20);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('RESERVATION_001');
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        return new StockReservedEvent($stockBatch, $reservation, 'customer_service');
    }

    public function testEventCreation(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-001');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH001');
        $stockBatch->setQuantity(100);

        $reservation = new StockReservation();
        $reservation->setQuantity(20);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('RESERVATION_001');
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));
        $operator = 'customer_service';

        $event = new StockReservedEvent(
            $stockBatch,
            $reservation,
            $operator
        );

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($reservation, $event->getReservation());
        $this->assertSame($operator, $event->getOperator());
        $this->assertSame('stock.reserved', $event->getEventType());
    }

    public function testEventCreationWithOptionalParameters(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-002');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH002');
        $stockBatch->setQuantity(50);

        $reservation = new StockReservation();
        $reservation->setQuantity(10);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('RESERVATION_002');
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $event = new StockReservedEvent($stockBatch, $reservation);

        $this->assertSame($stockBatch, $event->getStockBatch());
        $this->assertSame($reservation, $event->getReservation());
        $this->assertNull($event->getOperator());
        $this->assertSame('stock.reserved', $event->getEventType());
    }

    public function testEventType(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-003');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH003');
        $stockBatch->setQuantity(25);

        $reservation = new StockReservation();
        $reservation->setQuantity(5);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('RESERVATION_003');
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));
        $event = new StockReservedEvent($stockBatch, $reservation);

        $this->assertSame('stock.reserved', $event->getEventType());
    }

    public function testToArray(): void
    {
        $stockBatch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('sku-004');
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('BATCH004');
        $stockBatch->setQuantity(100);

        $reservation = new StockReservation();
        $reservation->setQuantity(20);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('RESERVATION_004');
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $event = new StockReservedEvent($stockBatch, $reservation, 'customer_service');

        $result = $event->toArray();

        $this->assertIsArray($result);
        $this->assertEquals('stock.reserved', $result['type']);
        $this->assertEquals('RESERVATION_004', $result['business_id']);
        $this->assertEquals(20, $result['reserved_quantity']);
        $this->assertEquals('order', $result['reservation_type']);
        $this->assertArrayHasKey('expires_time', $result);
    }
}
