<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存调整事件
 * 当库存被调整时触发（盘点、损耗、找到等）.
 */
class StockAdjustedEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected string $adjustmentType,
        protected int $adjustmentQuantity,
        protected string $reason,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.adjusted';
    }

    public function getAdjustmentType(): string
    {
        return $this->adjustmentType;
    }

    public function getAdjustmentQuantity(): int
    {
        return $this->adjustmentQuantity;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'adjustment_type' => $this->adjustmentType,
            'adjustment_quantity' => $this->adjustmentQuantity,
            'reason' => $this->reason,
        ]);
    }
}
