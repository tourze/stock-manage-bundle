<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\EventListener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockAllocatedEvent;
use Tourze\StockManageBundle\Event\StockCreatedEvent;
use Tourze\StockManageBundle\Event\StockEventInterface;
use Tourze\StockManageBundle\Event\StockInboundEvent;
use Tourze\StockManageBundle\Event\StockLockedEvent;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\Event\StockReservedEvent;

/**
 * 库存审计监听器
 * 负责记录所有库存事件的审计日志.
 */
#[WithMonologChannel(channel: 'stock_manage')]
class StockAuditListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: StockCreatedEvent::class)]
    public function onStockCreated(StockCreatedEvent $event): void
    {
        $this->logStockEvent($event, 'Stock created');
    }

    #[AsEventListener(event: StockAllocatedEvent::class)]
    public function onStockAllocated(StockAllocatedEvent $event): void
    {
        $this->logStockEvent($event, 'Stock allocated');
    }

    #[AsEventListener(event: StockReservedEvent::class)]
    public function onStockReserved(StockReservedEvent $event): void
    {
        $this->logStockEvent($event, 'Stock reserved');
    }

    #[AsEventListener(event: StockLockedEvent::class)]
    public function onStockLocked(StockLockedEvent $event): void
    {
        $this->logStockEvent($event, 'Stock locked');
    }

    #[AsEventListener(event: StockInboundEvent::class)]
    public function onStockInbound(StockInboundEvent $event): void
    {
        $this->logStockEvent($event, 'Stock inbound recorded');
    }

    #[AsEventListener(event: StockOutboundEvent::class)]
    public function onStockOutbound(StockOutboundEvent $event): void
    {
        $this->logStockEvent($event, 'Stock outbound recorded');
    }

    #[AsEventListener(event: StockAdjustedEvent::class)]
    public function onStockAdjusted(StockAdjustedEvent $event): void
    {
        // 对于负库存调整使用警告级别
        if ($event->getAdjustmentQuantity() < 0) {
            $this->logger->warning('Stock adjusted with negative quantity', $event->toArray());

            return;
        }

        $this->logStockEvent($event, 'Stock adjusted');
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
            case 'stock.adjusted':
                if ($event instanceof StockAdjustedEvent) {
                    $this->onStockAdjusted($event);
                }
                break;
            case 'stock.outbound':
                if ($event instanceof StockOutboundEvent) {
                    $this->onStockOutbound($event);
                }
                break;
            case 'stock.inbound':
                if ($event instanceof StockInboundEvent) {
                    $this->onStockInbound($event);
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
            default:
                $this->logStockEvent($event, 'Stock event processed');
        }
    }

    private function logStockEvent(StockEventInterface $event, string $message): void
    {
        $context = $event->toArray();
        $this->logger->info($message, $context);
    }
}
