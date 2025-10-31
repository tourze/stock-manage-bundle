<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockReservation;

/**
 * 库存预留事件
 * 当库存被预留时触发.
 */
class StockReservedEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected StockReservation $reservation,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.reserved';
    }

    public function getReservation(): StockReservation
    {
        return $this->reservation;
    }

    public function getReservationId(): ?int
    {
        return $this->reservation->getId();
    }

    public function getReservedQuantity(): int
    {
        return $this->reservation->getQuantity();
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'reservation_id' => $this->reservation->getId(),
            'reserved_quantity' => $this->reservation->getQuantity(),
            'reservation_type' => $this->reservation->getType()->value,
            'business_id' => $this->reservation->getBusinessId(),
            'expires_time' => $this->reservation->getExpiresTime()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
