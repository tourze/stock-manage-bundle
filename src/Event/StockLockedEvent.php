<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存锁定事件
 * 当库存被锁定时触发.
 */
final class StockLockedEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected string $lockType,
        protected int $lockedQuantity,
        protected string $reason,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.locked';
    }

    public function getLockType(): string
    {
        return $this->lockType;
    }

    public function getLockedQuantity(): int
    {
        return $this->lockedQuantity;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'lock_type' => $this->lockType,
            'locked_quantity' => $this->lockedQuantity,
            'reason' => $this->reason,
        ]);
    }
}
