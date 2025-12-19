<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存入库事件
 * 当库存入库时触发.
 */
final class StockInboundEvent extends AbstractStockEvent
{
    public function __construct(
        protected StockBatch $stockBatch,
        protected string $inboundType,
        protected int $inboundQuantity,
        protected ?string $orderNo = null,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        parent::__construct($stockBatch, $operator, $metadata);
    }

    public function getEventType(): string
    {
        return 'stock.inbound';
    }

    public function getInboundType(): string
    {
        return $this->inboundType;
    }

    public function getInboundQuantity(): int
    {
        return $this->inboundQuantity;
    }

    public function getOrderNo(): ?string
    {
        return $this->orderNo;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'inbound_type' => $this->inboundType,
            'inbound_quantity' => $this->inboundQuantity,
            'order_no' => $this->orderNo,
        ]);
    }
}
