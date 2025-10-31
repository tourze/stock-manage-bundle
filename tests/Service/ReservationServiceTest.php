<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\ReservationExpiredException;
use Tourze\StockManageBundle\Exception\ReservationNotFoundException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Repository\StockReservationRepository;
use Tourze\StockManageBundle\Service\AllocationService;
use Tourze\StockManageBundle\Service\ReservationService;

/**
 * @internal
 */
#[CoversClass(ReservationService::class)]
class ReservationServiceTest extends TestCase
{
    private ReservationService $service;

    private MockObject $entityManager;

    private MockObject $reservationRepository;

    private MockObject $batchRepository;

    private MockObject $allocationService;

    private MockObject $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->reservationRepository = $this->createMock(StockReservationRepository::class);
        $this->batchRepository = $this->createMock(StockBatchRepository::class);
        $this->allocationService = $this->createMock(AllocationService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->service = new ReservationService(
            $this->entityManager,
            $this->reservationRepository,
            $this->batchRepository,
            $this->allocationService,
            $this->eventDispatcher
        );
    }

    /**
     * @return MockObject&SKU
     */
    private function createSku(string $spuId): MockObject
    {
        $numericId = (string) (crc32($spuId) & 0x7FFFFFFF);
        /** @var MockObject&SKU $sku */
        $sku = $this->createMock(SKU::class);
        $sku->method('getId')->willReturn($numericId);
        $sku->method('getGtin')->willReturn($spuId);
        $sku->method('getMpn')->willReturn(null);
        $sku->method('getRemark')->willReturn(null);
        $sku->method('isValid')->willReturn(true);

        return $sku;
    }

    public function testReserveSuccess(): void
    {
        $skuMock = $this->createSku('SPU001');
        $data = [
            'sku' => $skuMock,
            'quantity' => 100,
            'type' => StockReservationType::ORDER->value,
            'business_id' => 'ORDER001',
            'expires_time' => new \DateTimeImmutable('+1 hour'),
        ];

        $batch1 = $this->createBatch('BATCH001', 60, 40);
        $batch2 = $this->createBatch('BATCH002', 70, 60);

        $this->batchRepository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($skuMock)
            ->willReturn([$batch1, $batch2])
        ;

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with($skuMock, 100, 'fifo')
            ->willReturn([
                'spuId' => 'SPU001',
                'requestedQuantity' => 100,
                'allocatedQuantity' => 100,
                'strategy' => 'fifo',
                'batches' => [
                    [
                        'batchId' => 1,
                        'batchNo' => 'BATCH001',
                        'quantity' => 40,
                        'unitCost' => 10.0,
                    ],
                    [
                        'batchId' => 2,
                        'batchNo' => 'BATCH002',
                        'quantity' => 60,
                        'unitCost' => 15.0,
                    ],
                ],
            ])
        ;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(StockReservation::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) {
                return $event instanceof GenericEvent
                    && 'stock.reserved' === $event->getArgument('event_name');
            }))
        ;

        $reservation = $this->service->reserve($data);

        $this->assertInstanceOf(StockReservation::class, $reservation);
        $expectedId = (string) (crc32('SPU001') & 0x7FFFFFFF);
        $this->assertEquals($expectedId, $reservation->getSpuId());
        $this->assertEquals(100, $reservation->getQuantity());
        $this->assertEquals(StockReservationType::ORDER, $reservation->getType());
        $this->assertEquals('ORDER001', $reservation->getBusinessId());
        $this->assertEquals(StockReservationStatus::PENDING, $reservation->getStatus());
        $this->assertEquals(['BATCH001' => 40, 'BATCH002' => 60], $reservation->getBatchAllocations());
    }

    public function testReserveInsufficientStock(): void
    {
        $skuMock = $this->createSku('SPU001');
        $data = [
            'sku' => $skuMock,
            'quantity' => 1000,
            'type' => StockReservationType::ORDER->value,
            'business_id' => 'ORDER001',
        ];

        $batch = $this->createBatch('BATCH001', 100, 50);

        $this->batchRepository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($skuMock)
            ->willReturn([$batch])
        ;

        $this->expectException(InsufficientStockException::class);
        $expectedId = (string) (crc32('SPU001') & 0x7FFFFFFF);
        $this->expectExceptionMessage("Insufficient stock for SKU {$expectedId}: required 1000, available 50");

        $this->service->reserve($data);
    }

    public function testReserveWithSpecificBatches(): void
    {
        $data = [
            'sku' => $this->createSku('SPU001'),
            'quantity' => 80,
            'type' => StockReservationType::VIP->value,
            'business_id' => 'VIP001',
            'batch_ids' => ['1', '3'],
        ];

        $batch1 = $this->createBatch('BATCH001', 60, 40);
        $batch1->method('getId')->willReturn(1);

        $batch2 = $this->createBatch('BATCH002', 50, 50);
        $batch2->method('getId')->willReturn(3);

        $this->batchRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => ['1', '3']])
            ->willReturn([$batch1, $batch2])
        ;

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with(self::isInstanceOf(SKU::class), 80, 'fifo')
            ->willReturn([
                'spuId' => 'SPU001',
                'requestedQuantity' => 80,
                'allocatedQuantity' => 80,
                'strategy' => 'fifo',
                'batches' => [
                    [
                        'batchId' => 1,
                        'batchNo' => 'BATCH001',
                        'quantity' => 40,
                        'unitCost' => 10.0,
                    ],
                    [
                        'batchId' => 3,
                        'batchNo' => 'BATCH002',
                        'quantity' => 40,
                        'unitCost' => 15.0,
                    ],
                ],
            ])
        ;

        $reservation = $this->service->reserve($data);

        $this->assertEquals(80, $reservation->getQuantity());
        $this->assertEquals(StockReservationType::VIP, $reservation->getType());
    }

    public function testConfirmReservation(): void
    {
        $reservation = new StockReservation();
        $reservation->setSku($this->createSku('SPU001'));
        $reservation->setQuantity(100);
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setBatchAllocations([
            'BATCH001' => 40,
            'BATCH002' => 60,
        ]);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $this->reservationRepository->expects($this->once())
            ->method('find')
            ->with('123')
            ->willReturn($reservation)
        ;

        $batch1 = $this->createBatch('BATCH001', 100, 60);
        $batch2 = $this->createBatch('BATCH002', 100, 70);

        $this->batchRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['batchNo' => 'BATCH001'], $batch1],
                [['batchNo' => 'BATCH002'], $batch2],
            ])
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) {
                return $event instanceof GenericEvent
                    && 'stock.reservation.confirmed' === $event->getArgument('event_name');
            }))
        ;

        $this->service->confirm('123');

        $this->assertEquals(StockReservationStatus::CONFIRMED, $reservation->getStatus());
        $this->assertNotNull($reservation->getConfirmedAt());
    }

    public function testConfirmExpiredReservation(): void
    {
        $reservation = new StockReservation();
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setExpiresTime(new \DateTimeImmutable('-1 hour'));

        $this->reservationRepository->expects($this->once())
            ->method('find')
            ->with('123')
            ->willReturn($reservation)
        ;

        $this->expectException(ReservationExpiredException::class);
        $this->expectExceptionMessage('Reservation 123 has expired');

        $this->service->confirm('123');
    }

    public function testReleaseReservation(): void
    {
        $reservation = new StockReservation();
        $reservation->setSku($this->createSku('SPU001'));
        $reservation->setQuantity(100);
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setBatchAllocations([
            'BATCH001' => 40,
            'BATCH002' => 60,
        ]);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $this->reservationRepository->expects($this->once())
            ->method('find')
            ->with('456')
            ->willReturn($reservation)
        ;

        $batch1 = $this->createBatch('BATCH001', 60, 20);
        $batch2 = $this->createBatch('BATCH002', 40, 10);

        $this->batchRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['batchNo' => 'BATCH001'], $batch1],
                [['batchNo' => 'BATCH002'], $batch2],
            ])
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) {
                return $event instanceof GenericEvent
                    && 'stock.reservation.released' === $event->getArgument('event_name');
            }))
        ;

        $this->service->release('456', 'Customer cancelled order');

        $this->assertEquals(StockReservationStatus::RELEASED, $reservation->getStatus());
        $this->assertNotNull($reservation->getReleasedAt());
        $this->assertEquals('Customer cancelled order', $reservation->getReleaseReason());
    }

    public function testReleaseNonExistentReservation(): void
    {
        $this->reservationRepository->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null)
        ;

        $this->expectException(ReservationNotFoundException::class);
        $this->expectExceptionMessage('Reservation 999 not found');

        $this->service->release('999');
    }

    public function testExtendReservation(): void
    {
        $reservation = new StockReservation();
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $this->reservationRepository->expects($this->once())
            ->method('find')
            ->with('789')
            ->willReturn($reservation)
        ;

        $newExpiryDate = new \DateTimeImmutable('+3 hours');

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->service->extend('789', $newExpiryDate);

        $this->assertEquals($newExpiryDate, $reservation->getExpiresTime());
    }

    public function testReleaseExpiredReservations(): void
    {
        $reservation1 = new StockReservation();
        $reservation1->setStatus(StockReservationStatus::PENDING);
        $reservation1->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        $reservation1->setBatchAllocations(['BATCH001' => 50]);

        $reservation2 = new StockReservation();
        $reservation2->setStatus(StockReservationStatus::PENDING);
        $reservation2->setExpiresTime(new \DateTimeImmutable('-2 hours'));
        $reservation2->setBatchAllocations(['BATCH002' => 30]);

        $this->reservationRepository->expects($this->once())
            ->method('findExpiredReservations')
            ->willReturn([$reservation1, $reservation2])
        ;

        $batch1 = $this->createBatch('BATCH001', 50, 0);
        $batch2 = $this->createBatch('BATCH002', 70, 40);

        $this->batchRepository->expects($this->exactly(2))
            ->method('findOneBy')
            ->willReturnMap([
                [['batchNo' => 'BATCH001'], $batch1],
                [['batchNo' => 'BATCH002'], $batch2],
            ])
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $count = $this->service->releaseExpiredReservations();

        $this->assertEquals(2, $count);
        $this->assertEquals(StockReservationStatus::EXPIRED, $reservation1->getStatus());
        $this->assertEquals(StockReservationStatus::EXPIRED, $reservation2->getStatus());
    }

    public function testGetActiveReservations(): void
    {
        $reservations = [
            new StockReservation(),
            new StockReservation(),
        ];

        $expectedId = (string) (crc32('SPU001') & 0x7FFFFFFF);
        $this->reservationRepository->expects($this->once())
            ->method('findActiveBySpuId')
            ->with($expectedId)
            ->willReturn($reservations)
        ;

        $result = $this->service->getActiveReservations($this->createSku('SPU001'));

        $this->assertCount(2, $result);
    }

    public function testGetReservedQuantity(): void
    {
        $expectedId = (string) (crc32('SPU001') & 0x7FFFFFFF);
        $this->reservationRepository->expects($this->once())
            ->method('getTotalReservedQuantity')
            ->with($expectedId)
            ->willReturn(250)
        ;

        $quantity = $this->service->getReservedQuantity($this->createSku('SPU001'));

        $this->assertEquals(250, $quantity);
    }

    private function createBatch(string $batchNo, int $quantity, int $available): MockObject
    {
        $batch = $this->createMock(StockBatch::class);
        $batch->method('getBatchNo')->willReturn($batchNo);
        $batch->method('getQuantity')->willReturn($quantity);
        $batch->method('getAvailableQuantity')->willReturn($available);
        $batch->method('getReservedQuantity')->willReturn($quantity - $available);

        return $batch;
    }
}
