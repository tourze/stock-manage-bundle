<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockEventInterface;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\Service\AlertServiceInterface;

/**
 * 库存警报监听器
 * 监控库存水平并发送低库存警报.
 */
class StockAlertListener
{
    public function __construct(
        private AlertServiceInterface $alertService,
        private int $lowStockThreshold = 10,
    ) {
    }

    #[AsEventListener(event: StockOutboundEvent::class)]
    public function onStockOutbound(StockOutboundEvent $event): void
    {
        $this->checkForLowStock($event);
    }

    #[AsEventListener(event: StockAdjustedEvent::class)]
    public function onStockAdjusted(StockAdjustedEvent $event): void
    {
        // 只在负数调整时检查低库存
        if ($event->getAdjustmentQuantity() < 0) {
            $this->checkForLowStock($event);
        }
    }

    public function handleStockEvent(StockEventInterface $event): void
    {
        $eventType = $event->getEventType();

        switch ($eventType) {
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
        }
    }

    private function checkForLowStock(StockEventInterface $event): void
    {
        $batch = $event->getStockBatch();
        $sku = $batch->getSku();

        // 检查 SKU 是否存在
        if (null === $sku) {
            return;
        }

        $currentQuantity = $batch->getAvailableQuantity();

        // 检查是否低于警报阈值
        if ($currentQuantity < $this->lowStockThreshold) {
            $this->alertService->sendLowStockAlert(
                $sku,
                $currentQuantity,
                $this->lowStockThreshold
            );
        }
    }
}
