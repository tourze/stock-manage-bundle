<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存分配事件
 * 当库存被分配时触发.
 */
final class StockAllocatedEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected int $allocatedQuantity,
        protected string $strategy,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.allocated';
    }

    public function getAllocatedQuantity(): int
    {
        return $this->allocatedQuantity;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'allocated_quantity' => $this->allocatedQuantity,
            'strategy' => $this->strategy,
        ]);
    }
}
