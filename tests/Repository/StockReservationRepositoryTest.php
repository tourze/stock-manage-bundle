<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Repository\StockReservationRepository;

/**
 * @internal
 */
#[CoversClass(StockReservationRepository::class)]
#[RunTestsInSeparateProcesses]
class StockReservationRepositoryTest extends AbstractRepositoryTestCase
{
    private StockReservationRepository $repository;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(StockReservationRepository::class);
    }

    protected function getRepository(): StockReservationRepository
    {
        return $this->repository;
    }

    protected function createNewEntity(): StockReservation
    {
        $spu = new Spu();
        $spu->setTitle('Test SPU ' . uniqid());
        self::getEntityManager()->persist($spu);

        $sku = new Sku();
        $sku->setGtin('SKU_' . uniqid());
        $sku->setUnit('个');
        $sku->setSpu($spu);
        self::getEntityManager()->persist($sku);

        $reservation = new StockReservation();
        $reservation->setSku($sku);
        $reservation->setQuantity(10);
        $reservation->setBusinessId('TEST_' . uniqid());
        $reservation->setType(StockReservationType::SYSTEM);
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        return $reservation;
    }

    public function testSaveAndRemove(): void
    {
        $spu = new Spu();
        $spu->setTitle('Test SPU for ORDER123');
        self::getEntityManager()->persist($spu);

        $sku = new Sku();
        $sku->setGtin('SKU001');
        $sku->setUnit('个');
        $sku->setSpu($spu);
        self::getEntityManager()->persist($sku);

        $reservation = new StockReservation();
        $reservation->setSku($sku);
        $reservation->setQuantity(10);
        $reservation->setBusinessId('ORDER123');
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $this->repository->save($reservation);
        $id = $reservation->getId();
        $this->assertNotNull($id);

        $this->repository->remove($reservation);
        $this->assertNull($this->repository->find($id));
    }

    public function testFindActiveBySpuId(): void
    {
        $reservation1 = $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour');
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '-1 hour'); // 过期的
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+1 hour');

        $activeReservations = $this->repository->findActiveBySpuId('SKU001');

        $this->assertCount(1, $activeReservations);
        $sku = $activeReservations[0]->getSku();
        $this->assertNotNull($sku, 'SKU should not be null');
        $this->assertEquals('SKU001', $sku->getGtin());
    }

    public function testFindActiveBySku(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour');
        $this->createTestReservation('SKU001', StockReservationStatus::CONFIRMED, '+1 hour');

        $activeReservations = $this->repository->findActiveBySku('SKU001');

        $this->assertCount(1, $activeReservations);
        $this->assertEquals(StockReservationStatus::PENDING, $activeReservations[0]->getStatus());
    }

    public function testFindExpiredReservations(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '-1 hour');
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+1 hour');

        $expiredReservations = $this->repository->findExpiredReservations();

        $this->assertCount(1, $expiredReservations);
        $sku = $expiredReservations[0]->getSku();
        $this->assertNotNull($sku, 'SKU should not be null');
        $this->assertEquals('SKU001', $sku->getGtin());
    }

    public function testGetTotalReservedQuantity(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10);
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 5);
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '-1 hour', 3); // 过期的不计算
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+1 hour', 20);

        $total = $this->repository->getTotalReservedQuantity('SKU001');

        $this->assertEquals(15, $total);
    }

    public function testGetTotalReservedQuantityBySku(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10);
        $this->createTestReservation('SKU001', StockReservationStatus::CONFIRMED, '+1 hour', 5); // 状态不是pending不计算

        $total = $this->repository->getTotalReservedQuantityBySku('SKU001');

        $this->assertEquals(10, $total);
    }

    public function testFindByBusinessId(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10, 'ORDER123');
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+1 hour', 5, 'ORDER123');
        $this->createTestReservation('SKU003', StockReservationStatus::PENDING, '+1 hour', 3, 'ORDER456');

        $reservations = $this->repository->findByBusinessId('ORDER123');

        $this->assertCount(2, $reservations);
        foreach ($reservations as $reservation) {
            $this->assertEquals('ORDER123', $reservation->getBusinessId());
        }
    }

    public function testFindByType(): void
    {
        // Clear any existing data first
        self::getEntityManager()->createQuery('DELETE FROM Tourze\StockManageBundle\Entity\StockReservation r')->execute();
        self::getEntityManager()->createQuery('DELETE FROM Tourze\ProductCoreBundle\Entity\Sku s')->execute();
        self::getEntityManager()->createQuery('DELETE FROM Tourze\ProductCoreBundle\Entity\Spu s')->execute();

        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10, 'ORDER123', StockReservationType::ORDER);
        $this->createTestReservation('SKU002', StockReservationStatus::CONFIRMED, '+1 hour', 5, 'ORDER456', StockReservationType::ORDER);
        $this->createTestReservation('SKU003', StockReservationStatus::PENDING, '+1 hour', 3, 'PROMO789', StockReservationType::PROMOTION);

        $orderReservations = $this->repository->findByType(StockReservationType::ORDER);
        $this->assertCount(2, $orderReservations);

        $pendingOrderReservations = $this->repository->findByType(StockReservationType::ORDER, StockReservationStatus::PENDING);
        $this->assertCount(1, $pendingOrderReservations);
        $this->assertEquals(StockReservationStatus::PENDING, $pendingOrderReservations[0]->getStatus());
    }

    public function testExistsForBusiness(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10, 'ORDER123');
        $this->createTestReservation('SKU002', StockReservationStatus::RELEASED, '+1 hour', 5, 'ORDER123');

        $this->assertTrue($this->repository->existsForBusiness('ORDER123', 'SKU001'));
        $this->assertFalse($this->repository->existsForBusiness('ORDER123', 'SKU002')); // released状态不算
        $this->assertFalse($this->repository->existsForBusiness('ORDER456', 'SKU001'));
    }

    public function testFindExpiringSoon(): void
    {
        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour');
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+25 hours');
        $this->createTestReservation('SKU003', StockReservationStatus::PENDING, '-1 hour');

        $expiringSoon = $this->repository->findExpiringSoon(24);

        $this->assertCount(1, $expiringSoon);
        $sku = $expiringSoon[0]->getSku();
        $this->assertNotNull($sku, 'SKU should not be null');
        $this->assertEquals('SKU001', $sku->getGtin());
    }

    public function testGetStatistics(): void
    {
        // Clear any existing data first
        self::getEntityManager()->createQuery('DELETE FROM Tourze\StockManageBundle\Entity\StockReservation r')->execute();
        self::getEntityManager()->createQuery('DELETE FROM Tourze\ProductCoreBundle\Entity\Sku s')->execute();
        self::getEntityManager()->createQuery('DELETE FROM Tourze\ProductCoreBundle\Entity\Spu s')->execute();

        $this->createTestReservation('SKU001', StockReservationStatus::PENDING, '+1 hour', 10);
        $this->createTestReservation('SKU002', StockReservationStatus::PENDING, '+1 hour', 5);
        $this->createTestReservation('SKU003', StockReservationStatus::CONFIRMED, '+1 hour', 3);
        $this->createTestReservation('SKU004', StockReservationStatus::RELEASED, '+1 hour', 2);

        $stats = $this->repository->getStatistics();

        $this->assertEquals(2, $stats['pending']['count']);
        $this->assertEquals(15, $stats['pending']['quantity']);
        $this->assertEquals(1, $stats['confirmed']['count']);
        $this->assertEquals(3, $stats['confirmed']['quantity']);
        $this->assertEquals(1, $stats['released']['count']);
        $this->assertEquals(2, $stats['released']['quantity']);
        $this->assertEquals(0, $stats['expired']['count']);
    }

    private function createTestReservation(
        string $spuId,
        StockReservationStatus $status,
        string $expiresTime,
        int $quantity = 10,
        string $businessId = 'TEST_BUSINESS',
        StockReservationType $type = StockReservationType::SYSTEM,
    ): StockReservation {
        $spu = new Spu();
        $spu->setTitle('Test SPU ' . $spuId);
        self::getEntityManager()->persist($spu);

        $sku = new Sku();
        $sku->setGtin($spuId);
        $sku->setUnit('个');
        $sku->setSpu($spu);
        self::getEntityManager()->persist($sku);

        $reservation = new StockReservation();
        $reservation->setSku($sku);
        $reservation->setQuantity($quantity);
        $reservation->setBusinessId($businessId);
        $reservation->setType($type);
        $reservation->setStatus($status);
        $reservation->setExpiresTime(new \DateTimeImmutable($expiresTime));

        $this->repository->save($reservation);

        return $reservation;
    }
}
