<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockAllocatedEvent;
use Tourze\StockManageBundle\Event\StockCreatedEvent;
use Tourze\StockManageBundle\Event\StockEventInterface;
use Tourze\StockManageBundle\Event\StockInboundEvent;
use Tourze\StockManageBundle\Event\StockLockedEvent;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\Event\StockReservedEvent;
use Tourze\StockManageBundle\Service\SnapshotServiceInterface;

/**
 * 库存快照监听器
 * 为重要的库存事件创建快照.
 */
class StockSnapshotListener
{
    public function __construct(
        private SnapshotServiceInterface $snapshotService,
    ) {
    }

    #[AsEventListener(event: StockCreatedEvent::class)]
    public function onStockCreated(StockCreatedEvent $event): void
    {
        $this->createSnapshot($event, 'stock_created');
    }

    #[AsEventListener(event: StockInboundEvent::class)]
    public function onStockInbound(StockInboundEvent $event): void
    {
        $this->createSnapshot($event, 'stock_inbound');
    }

    #[AsEventListener(event: StockOutboundEvent::class)]
    public function onStockOutbound(StockOutboundEvent $event): void
    {
        $this->createSnapshot($event, 'stock_outbound');
    }

    #[AsEventListener(event: StockAdjustedEvent::class)]
    public function onStockAdjusted(StockAdjustedEvent $event): void
    {
        $this->createSnapshot($event, 'stock_adjusted');
    }

    #[AsEventListener(event: StockAllocatedEvent::class)]
    public function onStockAllocated(StockAllocatedEvent $event): void
    {
        $this->createSnapshot($event, 'stock_allocated');
    }

    #[AsEventListener(event: StockReservedEvent::class)]
    public function onStockReserved(StockReservedEvent $event): void
    {
        $this->createSnapshot($event, 'stock_reserved');
    }

    #[AsEventListener(event: StockLockedEvent::class)]
    public function onStockLocked(StockLockedEvent $event): void
    {
        $this->createSnapshot($event, 'stock_locked');
    }

    public function handleStockEvent(StockEventInterface $event): void
    {
        $eventType = $event->getEventType();

        switch ($eventType) {
            case 'stock.created':
                if ($event instanceof StockCreatedEvent) {
                    $this->onStockCreated($event);
                }
                break;
            case 'stock.inbound':
                if ($event instanceof StockInboundEvent) {
                    $this->onStockInbound($event);
                }
                break;
            case 'stock.outbound':
                if ($event instanceof StockOutboundEvent) {
                    $this->onStockOutbound($event);
                }
                break;
            case 'stock.adjusted':
                if ($event instanceof StockAdjustedEvent) {
                    $this->onStockAdjusted($event);
                }
                break;
            case 'stock.allocated':
                if ($event instanceof StockAllocatedEvent) {
                    $this->onStockAllocated($event);
                }
                break;
            case 'stock.reserved':
                if ($event instanceof StockReservedEvent) {
                    $this->onStockReserved($event);
                }
                break;
            case 'stock.locked':
                if ($event instanceof StockLockedEvent) {
                    $this->onStockLocked($event);
                }
                break;
        }
    }

    private function createSnapshot(StockEventInterface $event, string $snapshotType): void
    {
        $batch = $event->getStockBatch();
        $sku = $batch->getSku();

        // 检查 SKU 是否存在
        if (null === $sku) {
            return;
        }

        // 构建快照元数据
        $metadata = [
            'event_type' => $event->getEventType(),
            'batch_no' => $batch->getBatchNo(),
            'operator' => $event->getOperator(),
            'occurred_at' => $event->getOccurredTime()->format(\DateTimeInterface::ATOM),
            'original_metadata' => $event->getMetadata(),
        ];

        // 添加事件特有的元数据
        $eventArray = $event->toArray();
        foreach ($eventArray as $key => $value) {
            if (!in_array($key, ['type', 'spu_id', 'batch_no', 'quantity', 'operator', 'occurred_at', 'metadata'], true)) {
                $metadata[$key] = $value;
            }
        }

        $this->snapshotService->createSnapshot(
            $sku,
            $batch->getQuantity(),
            $snapshotType,
            $metadata
        );
    }
}
