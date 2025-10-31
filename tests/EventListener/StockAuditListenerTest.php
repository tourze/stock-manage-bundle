<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockAllocatedEvent;
use Tourze\StockManageBundle\Event\StockCreatedEvent;
use Tourze\StockManageBundle\Event\StockInboundEvent;
use Tourze\StockManageBundle\Event\StockLockedEvent;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\Event\StockReservedEvent;
use Tourze\StockManageBundle\EventListener\StockAuditListener;

/**
 * @internal
 */
#[CoversClass(StockAuditListener::class)]
#[RunTestsInSeparateProcesses]
class StockAuditListenerTest extends AbstractEventSubscriberTestCase
{
    private StockAuditListener $listener;

    private LoggerInterface&MockObject $logger;

    protected function onSetUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        // 通过容器设置具体的monolog logger服务，然后从容器获取EventListener实例
        self::getContainer()->set('monolog.logger.stock_manage', $this->logger);
        $this->listener = self::getService(StockAuditListener::class);
    }

    private function createSku(string $spuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($spuId);

        return $sku;
    }

    public function testHandleStockEvent(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU001'));
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $event = new StockCreatedEvent($batch, 'admin', ['source' => 'purchase']);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock created', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH001' === $context['batch_no'];
            }))
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testOnStockCreated(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU002'));
        $batch->setBatchNo('BATCH002');
        $batch->setQuantity(100);

        $event = new StockCreatedEvent($batch, 'admin', ['source' => 'purchase']);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock created', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH002' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockCreated($event);
    }

    public function testOnStockAdjusted(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU003'));
        $batch->setBatchNo('BATCH003');
        $batch->setQuantity(50);

        $event = new StockAdjustedEvent($batch, 'damage', -5, 'Damaged goods', 'warehouse_admin');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Stock adjusted with negative quantity', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH003' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockAdjusted($event);
    }

    public function testOnStockOutbound(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU004'));
        $batch->setBatchNo('BATCH004');
        $batch->setQuantity(200);

        $event = new StockOutboundEvent($batch, 'sale', 10, 'SO001', 'sales_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock outbound recorded', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH004' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockOutbound($event);
    }

    public function testOnStockAllocated(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU005'));
        $batch->setBatchNo('BATCH005');
        $batch->setQuantity(150);

        $event = new StockAllocatedEvent($batch, 20, 'fifo', 'allocation_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock allocated', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH005' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockAllocated($event);
    }

    public function testOnStockInbound(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU006'));
        $batch->setBatchNo('BATCH006');
        $batch->setQuantity(100);

        $event = new StockInboundEvent($batch, 'purchase', 100, 'PO001', 'warehouse_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock inbound recorded', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH006' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockInbound($event);
    }

    public function testOnStockLocked(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU007'));
        $batch->setBatchNo('BATCH007');
        $batch->setQuantity(80);

        $event = new StockLockedEvent($batch, 'quality_check', 10, 'Quality inspection needed', 'qc_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock locked', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH007' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockLocked($event);
    }

    public function testOnStockReserved(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU008'));
        $batch->setBatchNo('BATCH008');
        $batch->setQuantity(120);

        // 创建一个简单的 reservation mock
        $reservation = $this->createMock(StockReservation::class);
        $reservation->method('getId')->willReturn(12345);
        $reservation->method('getQuantity')->willReturn(50);
        $reservation->method('getType')->willReturn(StockReservationType::ORDER);
        $reservation->method('getBusinessId')->willReturn('ORDER001');
        $reservation->method('getExpiresTime')->willReturn(new \DateTimeImmutable('+1 hour'));

        $event = new StockReservedEvent($batch, $reservation, 'order_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock reserved', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH008' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockReserved($event);
    }

    public function testOnStockAdjustedWithPositiveQuantity(): void
    {
        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU009'));
        $batch->setBatchNo('BATCH009');
        $batch->setQuantity(50);

        $event = new StockAdjustedEvent($batch, 'restock', 10, 'Additional inventory', 'warehouse_admin');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Stock adjusted', self::callback(function ($context) {
                return is_array($context) && isset($context['batch_no']) && 'BATCH009' === $context['batch_no'];
            }))
        ;

        $this->listener->onStockAdjusted($event);
    }
}
