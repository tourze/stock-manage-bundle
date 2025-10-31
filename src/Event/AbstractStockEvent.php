<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 抽象库存事件类
 * 提供所有库存事件的通用功能实现.
 */
abstract class AbstractStockEvent extends Event implements StockEventInterface
{
    private \DateTimeInterface $occurredTime;

    public function __construct(
        protected StockBatch $stockBatch,
        protected ?string $operator = null,
        /** @var array<string, mixed> */
        protected array $metadata = [],
    ) {
        $this->occurredTime = new \DateTimeImmutable();
    }

    public function getStockBatch(): StockBatch
    {
        return $this->stockBatch;
    }

    public function getSpuId(): string
    {
        return $this->stockBatch->getSku()?->getGtin() ?? '';
    }

    public function getQuantity(): int
    {
        return $this->stockBatch->getQuantity();
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function getOccurredTime(): \DateTimeInterface
    {
        return $this->occurredTime;
    }

    public function getOccurredAt(): \DateTimeInterface
    {
        return $this->occurredTime;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getEventType(),
            'spu_id' => $this->getSpuId(),
            'batch_no' => $this->stockBatch->getBatchNo(),
            'quantity' => $this->getQuantity(),
            'operator' => $this->operator,
            'occurred_at' => $this->occurredTime->format(\DateTimeInterface::ATOM),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * 获取事件类型
     * 必须由具体事件类实现.
     */
    abstract public function getEventType(): string;
}
