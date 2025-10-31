<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Exception\ReservationExpiredException;
use Tourze\StockManageBundle\Exception\ReservationNotFoundException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Repository\StockReservationRepository;

class ReservationService implements ReservationServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockReservationRepository $reservationRepository,
        private StockBatchRepository $batchRepository,
        private AllocationService $allocationService,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function reserve(array $data): StockReservation
    {
        $sku = $data['sku'];
        $quantity = $data['quantity'];
        $type = $data['type'] instanceof StockReservationType ? $data['type'] : StockReservationType::from($data['type']);
        $businessId = $data['business_id'];
        $expiresTime = isset($data['expires_time']) ? \DateTimeImmutable::createFromInterface($data['expires_time']) : new \DateTimeImmutable('+1 hour');
        $batchIds = $data['batch_ids'] ?? null;

        $batches = $this->findBatchesForReservation($sku, $batchIds);
        $this->validateAvailableQuantity($batches, $sku, $quantity);

        $allocations = $this->calculateBatchAllocations($sku, $quantity);
        $reservation = $this->createReservationEntity($sku, $quantity, $type, $businessId, $expiresTime, $allocations, $data);

        $this->updateBatchQuantities($allocations);
        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(
            new GenericEvent($reservation, ['event_name' => 'stock.reserved'])
        );

        return $reservation;
    }

    /**
     * @param array<mixed>|null $batchIds
     *
     * @return array<StockBatch>
     */
    private function findBatchesForReservation(SKU $sku, ?array $batchIds): array
    {
        if (null !== $batchIds) {
            return $this->batchRepository->findBy(['id' => $batchIds]);
        }

        return $this->batchRepository->findAvailableBySku($sku);
    }

    /**
     * @param array<StockBatch> $batches
     */
    private function validateAvailableQuantity(array $batches, SKU $sku, int $quantity): void
    {
        $totalAvailable = array_reduce($batches, function ($sum, StockBatch $batch) {
            return $sum + $batch->getAvailableQuantity();
        }, 0);

        if ($totalAvailable < $quantity) {
            throw InsufficientStockException::createBySku($sku->getId(), $quantity, $totalAvailable);
        }
    }

    /**
     * @return array<string, int>
     */
    private function calculateBatchAllocations(SKU $sku, int $quantity): array
    {
        $allocationResult = $this->allocationService->calculateAllocation($sku, $quantity, 'fifo');
        assert(isset($allocationResult['batches']) && is_array($allocationResult['batches']));
        /** @var array<string, int> $allocations */
        $allocations = [];
        foreach ($allocationResult['batches'] as $batch) {
            assert(is_array($batch));
            assert(isset($batch['batchNo']) && (is_string($batch['batchNo']) || is_int($batch['batchNo'])));
            assert(isset($batch['quantity']) && is_int($batch['quantity']));
            $allocations[(string) $batch['batchNo']] = $batch['quantity'];
        }

        return $allocations;
    }

    /**
     * @param array<string, int>   $allocations
     * @param array<string, mixed> $data
     */
    private function createReservationEntity(
        SKU $sku,
        int $quantity,
        StockReservationType $type,
        string $businessId,
        \DateTimeImmutable $expiresTime,
        array $allocations,
        array $data,
    ): StockReservation {
        $reservation = new StockReservation();
        $reservation->setSku($sku);
        $reservation->setQuantity($quantity);
        $reservation->setType($type);
        $reservation->setBusinessId($businessId);
        $reservation->setExpiresTime($expiresTime);
        $reservation->setBatchAllocations($allocations);

        $operator = (isset($data['operator']) && is_string($data['operator'])) ? $data['operator'] : null;
        $reservation->setOperator($operator);

        $notes = (isset($data['notes']) && is_string($data['notes'])) ? $data['notes'] : null;
        $reservation->setNotes($notes);

        return $reservation;
    }

    /**
     * @param array<string, int> $allocations
     */
    private function updateBatchQuantities(array $allocations): void
    {
        foreach ($allocations as $batchNo => $allocatedQty) {
            $batch = $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
            if ($batch instanceof StockBatch) {
                $batch->setReservedQuantity($batch->getReservedQuantity() + (int) $allocatedQty);
                $batch->setAvailableQuantity($batch->getAvailableQuantity() - (int) $allocatedQty);
            }
        }
    }

    public function confirm(string $reservationId): void
    {
        $reservation = $this->reservationRepository->find($reservationId);

        if (null === $reservation) {
            throw ReservationNotFoundException::withId($reservationId);
        }

        if ($reservation->getExpiresTime() < new \DateTime()) {
            throw ReservationExpiredException::withId($reservationId);
        }

        if (StockReservationStatus::PENDING !== $reservation->getStatus()) {
            throw new InvalidOperationException(sprintf('Cannot confirm reservation %s with status %s', $reservationId, $reservation->getStatus()->value));
        }

        // Confirm the reservation
        $reservation->setStatus(StockReservationStatus::CONFIRMED);
        $reservation->setConfirmedTime(new \DateTimeImmutable());

        // Convert reserved to actual consumption
        $allocations = $reservation->getBatchAllocations() ?? [];
        foreach ($allocations as $batchNo => $quantity) {
            $batch = $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
            if ($batch instanceof StockBatch) {
                $batch->setQuantity($batch->getQuantity() - (int) $quantity);
                $batch->setReservedQuantity($batch->getReservedQuantity() - (int) $quantity);
            }
        }

        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(
            new GenericEvent($reservation, ['event_name' => 'stock.reservation.confirmed'])
        );
    }

    public function release(string $reservationId, string $reason = ''): void
    {
        $reservation = $this->reservationRepository->find($reservationId);

        if (null === $reservation) {
            throw ReservationNotFoundException::withId($reservationId);
        }

        if (StockReservationStatus::RELEASED === $reservation->getStatus()) {
            return; // Already released
        }

        // Restore batch quantities before changing status
        $wasPending = StockReservationStatus::PENDING === $reservation->getStatus();
        if ($wasPending) {
            $allocations = $reservation->getBatchAllocations() ?? [];
            foreach ($allocations as $batchNo => $quantity) {
                $batch = $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
                if (null !== $batch) {
                    $batch->setReservedQuantity($batch->getReservedQuantity() - $quantity);
                    $batch->setAvailableQuantity($batch->getAvailableQuantity() + $quantity);
                }
            }
        }

        // Release the reservation
        $reservation->setStatus(StockReservationStatus::RELEASED);
        $reservation->setReleasedTime(new \DateTimeImmutable());
        $reservation->setReleaseReason($reason);

        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(
            new GenericEvent($reservation, ['event_name' => 'stock.reservation.released'])
        );
    }

    public function extend(string $reservationId, \DateTimeInterface $newExpiryDate): void
    {
        $reservation = $this->reservationRepository->find($reservationId);

        if (null === $reservation) {
            throw ReservationNotFoundException::withId($reservationId);
        }

        if (StockReservationStatus::PENDING !== $reservation->getStatus()) {
            throw new InvalidOperationException(sprintf('Cannot extend reservation %s with status %s', $reservationId, $reservation->getStatus()->value));
        }

        $reservation->setExpiresTime(\DateTimeImmutable::createFromInterface($newExpiryDate));
        $this->entityManager->flush();

        // Dispatch event
        $this->eventDispatcher->dispatch(
            new GenericEvent($reservation, ['event_name' => 'stock.reservation.extended'])
        );
    }

    public function releaseExpiredReservations(): int
    {
        $expiredReservations = $this->reservationRepository->findExpiredReservations();
        $count = 0;

        foreach ($expiredReservations as $reservation) {
            $reservation->setStatus(StockReservationStatus::EXPIRED);
            $reservation->setReleasedTime(new \DateTimeImmutable());
            $reservation->setReleaseReason('Automatic expiration');

            // Restore batch quantities
            $allocations = $reservation->getBatchAllocations() ?? [];
            foreach ($allocations as $batchNo => $quantity) {
                $batch = $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
                if (null !== $batch) {
                    $batch->setReservedQuantity($batch->getReservedQuantity() - $quantity);
                    $batch->setAvailableQuantity($batch->getAvailableQuantity() + $quantity);
                }
            }

            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    public function getActiveReservations(SKU $sku): array
    {
        return $this->reservationRepository->findActiveBySpuId($sku->getId());
    }

    public function getReservedQuantity(SKU $sku): int
    {
        return $this->reservationRepository->getTotalReservedQuantity($sku->getId());
    }

    /**
     * @return StockReservation[]
     */
    public function getReservationsByBusiness(string $businessId): array
    {
        return $this->reservationRepository->findByBusinessId($businessId);
    }

    /**
     * @return StockReservation[]
     */
    public function getExpiringSoonReservations(int $hoursAhead = 24): array
    {
        return $this->reservationRepository->findExpiringSoon($hoursAhead);
    }

    /**
     * @return array{pending: array{count: int, quantity: int}, confirmed: array{count: int, quantity: int}, released: array{count: int, quantity: int}, expired: array{count: int, quantity: int}}
     */
    public function getStatistics(): array
    {
        return $this->reservationRepository->getStatistics();
    }
}
