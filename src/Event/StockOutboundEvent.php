<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存出库事件
 * 当库存出库时触发.
 */
class StockOutboundEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected string $outboundType,
        protected int $outboundQuantity,
        protected ?string $orderNo = null,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.outbound';
    }

    public function getOutboundType(): string
    {
        return $this->outboundType;
    }

    public function getOutboundQuantity(): int
    {
        return $this->outboundQuantity;
    }

    public function getOrderNo(): ?string
    {
        return $this->orderNo;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'outbound_type' => $this->outboundType,
            'outbound_quantity' => $this->outboundQuantity,
            'order_no' => $this->orderNo,
        ]);
    }
}
