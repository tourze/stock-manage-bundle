<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;

/**
 * @internal
 */
#[CoversClass(StockReservation::class)]
class StockReservationTest extends AbstractEntityTestCase
{
    protected function createEntity(): StockReservation
    {
        return new StockReservation();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'quantity' => ['quantity', 100];
        yield 'type' => ['type', StockReservationType::ORDER];
        yield 'businessId' => ['businessId', 'ORDER001'];
        yield 'status' => ['status', StockReservationStatus::CONFIRMED];
        yield 'expiresTime' => ['expiresTime', new \DateTimeImmutable('+1 hour')];
        yield 'createTime' => ['createTime', new \DateTimeImmutable()];
        yield 'batchAllocations' => ['batchAllocations', ['BATCH001' => 50]];
        yield 'confirmedTime' => ['confirmedTime', new \DateTimeImmutable()];
        yield 'releasedTime' => ['releasedTime', new \DateTimeImmutable()];
        yield 'releaseReason' => ['releaseReason', 'Customer cancelled'];
        yield 'operator' => ['operator', 'user_123'];
        yield 'notes' => ['notes', 'VIP customer reservation'];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $reservation = $this->createEntity();

        $this->assertNull($reservation->getId());
        $this->assertEquals(StockReservationStatus::PENDING, $reservation->getStatus());
        $this->assertNull($reservation->getBatchAllocations());
        $this->assertNull($reservation->getConfirmedTime());
        $this->assertNull($reservation->getReleasedTime());
    }

    public function testSkuHandling(): void
    {
        $reservation = $this->createEntity();

        $sku = new Sku();
        $sku->setGtin('SPU001');
        $reservation->setSku($sku);
        $this->assertSame($sku, $reservation->getSku());
        $this->assertEquals('SPU001', $reservation->getSku()->getGtin());
    }

    public function testSettersAndGetters(): void
    {
        $reservation = $this->createEntity();

        $now = new \DateTimeImmutable();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $sku = new Sku();
        $sku->setGtin('SPU001');
        $reservation->setSku($sku);
        $reservation->setQuantity(100);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('ORDER001');
        $reservation->setStatus(StockReservationStatus::CONFIRMED);
        $reservation->setExpiresTime($expiresAt);
        $reservation->setCreateTime($now);

        $this->assertSame($sku, $reservation->getSku());
        $this->assertEquals('SPU001', $reservation->getSku()->getGtin());

        $this->assertEquals(100, $reservation->getQuantity());
        $this->assertEquals(StockReservationType::ORDER, $reservation->getType());
        $this->assertEquals('ORDER001', $reservation->getBusinessId());
        $this->assertEquals(StockReservationStatus::CONFIRMED, $reservation->getStatus());
        $this->assertEquals($expiresAt, $reservation->getExpiresTime());
        $this->assertEquals($now, $reservation->getCreateTime());
    }

    public function testBatchAllocations(): void
    {
        $reservation = $this->createEntity();

        $allocations = [
            'BATCH001' => 50,
            'BATCH002' => 30,
            'BATCH003' => 20,
        ];

        $reservation->setBatchAllocations($allocations);
        $this->assertEquals($allocations, $reservation->getBatchAllocations());
    }

    public function testReservationTypes(): void
    {
        $reservation = $this->createEntity();

        $types = [
            StockReservationType::ORDER,
            StockReservationType::PROMOTION,
            StockReservationType::VIP,
            StockReservationType::SYSTEM,
        ];

        foreach ($types as $type) {
            $reservation->setType($type);
            $this->assertEquals($type, $reservation->getType());
        }
    }

    public function testReservationStatuses(): void
    {
        $reservation = $this->createEntity();

        $statuses = [
            StockReservationStatus::PENDING,
            StockReservationStatus::CONFIRMED,
            StockReservationStatus::RELEASED,
            StockReservationStatus::EXPIRED,
        ];

        foreach ($statuses as $status) {
            $reservation->setStatus($status);
            $this->assertEquals($status, $reservation->getStatus());
        }
    }

    public function testOptionalFields(): void
    {
        $reservation = $this->createEntity();

        $confirmedAt = new \DateTimeImmutable();
        $releasedAt = new \DateTimeImmutable('+1 hour');

        $reservation->setConfirmedTime($confirmedAt);
        $reservation->setReleasedTime($releasedAt);
        $reservation->setReleaseReason('Customer cancelled');
        $reservation->setOperator('user_123');
        $reservation->setNotes('VIP customer reservation');

        $this->assertEquals($confirmedAt, $reservation->getConfirmedTime());
        $this->assertEquals($releasedAt, $reservation->getReleasedTime());
        $this->assertEquals('Customer cancelled', $reservation->getReleaseReason());
        $this->assertEquals('user_123', $reservation->getOperator());
        $this->assertEquals('VIP customer reservation', $reservation->getNotes());
    }

    public function testIsExpired(): void
    {
        $reservation = $this->createEntity();

        $past = new \DateTimeImmutable('-1 hour');
        $future = new \DateTimeImmutable('+1 hour');

        $reservation->setExpiresTime($past);
        $this->assertTrue($reservation->isExpired());

        $reservation->setExpiresTime($future);
        $this->assertFalse($reservation->isExpired());
    }

    public function testIsActive(): void
    {
        $reservation = $this->createEntity();

        $future = new \DateTimeImmutable('+1 hour');
        $reservation->setExpiresTime($future);

        $reservation->setStatus(StockReservationStatus::PENDING);
        $this->assertTrue($reservation->isActive());

        $reservation->setStatus(StockReservationStatus::CONFIRMED);
        $this->assertFalse($reservation->isActive());

        $reservation->setStatus(StockReservationStatus::RELEASED);
        $this->assertFalse($reservation->isActive());

        $reservation->setStatus(StockReservationStatus::EXPIRED);
        $this->assertFalse($reservation->isActive());
    }

    public function testGetAllocatedQuantity(): void
    {
        $reservation = $this->createEntity();

        $this->assertEquals(0, $reservation->getAllocatedQuantity());

        $allocations = [
            'BATCH001' => 50,
            'BATCH002' => 30,
            'BATCH003' => 20,
        ];

        $reservation->setBatchAllocations($allocations);
        $this->assertEquals(100, $reservation->getAllocatedQuantity());
    }

    public function testReservationLifecycle(): void
    {
        $reservation = $this->createEntity();

        $now = new \DateTimeImmutable();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        // Create reservation
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $reservation->setSku($sku);
        $reservation->setQuantity(100);
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setBusinessId('ORDER001');
        $reservation->setExpiresTime($expiresAt);
        $reservation->setCreateTime($now);
        $reservation->setStatus(StockReservationStatus::PENDING);

        $this->assertTrue($reservation->isActive());

        // Confirm reservation
        $reservation->setStatus(StockReservationStatus::CONFIRMED);
        $reservation->setConfirmedTime(new \DateTimeImmutable());

        $this->assertFalse($reservation->isActive());
        $this->assertNotNull($reservation->getConfirmedTime());

        // Release reservation
        $reservation->setStatus(StockReservationStatus::RELEASED);
        $reservation->setReleasedTime(new \DateTimeImmutable());
        $reservation->setReleaseReason('Order cancelled');

        $this->assertNotNull($reservation->getReleasedTime());
        $this->assertEquals('Order cancelled', $reservation->getReleaseReason());
    }
}
