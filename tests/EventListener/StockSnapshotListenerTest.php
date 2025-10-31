<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Event\AbstractStockEvent;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockAllocatedEvent;
use Tourze\StockManageBundle\Event\StockCreatedEvent;
use Tourze\StockManageBundle\Event\StockInboundEvent;
use Tourze\StockManageBundle\Event\StockLockedEvent;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\Event\StockReservedEvent;
use Tourze\StockManageBundle\EventListener\StockSnapshotListener;
use Tourze\StockManageBundle\Service\SnapshotServiceInterface;

/**
 * @internal
 */
#[CoversClass(StockSnapshotListener::class)]
#[RunTestsInSeparateProcesses]
class StockSnapshotListenerTest extends AbstractEventSubscriberTestCase
{
    private StockSnapshotListener $listener;

    private SnapshotServiceInterface&MockObject $snapshotService;

    protected function onSetUp(): void
    {
        $this->snapshotService = $this->createMock(SnapshotServiceInterface::class);
        self::getContainer()->set(SnapshotServiceInterface::class, $this->snapshotService);
        $this->listener = self::getService(StockSnapshotListener::class);
    }

    public function testHandleStockCreatedEvent(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);

        $event = new StockCreatedEvent($batch, 'admin');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                100,
                'stock_created',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'BATCH001' === $metadata['batch_no']
                        && 'admin' === $metadata['operator'];
                })
            )
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testHandleStockInboundEvent(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU002');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH002');
        $batch->setQuantity(150);

        $event = new StockInboundEvent($batch, 'purchase', 50, 'PO001', 'warehouse_admin');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                150,
                'stock_inbound',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'purchase' === $metadata['inbound_type']
                        && 50 === $metadata['inbound_quantity']
                        && 'PO001' === $metadata['order_no'];
                })
            )
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testHandleStockAdjustedEvent(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU003');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH003');
        $batch->setQuantity(95);

        $event = new StockAdjustedEvent($batch, 'damage', -5, 'Damaged during transport', 'inspector');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                95,
                'stock_adjusted',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'damage' === $metadata['adjustment_type']
                        && -5 === $metadata['adjustment_quantity']
                        && 'Damaged during transport' === $metadata['reason'];
                })
            )
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testHandleEventWithoutOperator(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU004');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH004');
        $batch->setQuantity(75);

        $event = new StockCreatedEvent($batch);

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                75,
                'stock_created',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return null === $metadata['operator'];
                })
            )
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testOnStockAllocated(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU006');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH006');
        $batch->setQuantity(100);

        $event = new StockAllocatedEvent($batch, 20, 'FIFO', 'allocation_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                100,
                'stock_allocated',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 20 === $metadata['allocated_quantity']
                        && 'FIFO' === $metadata['strategy']
                        && 'allocation_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockAllocated($event);
    }

    public function testOnStockOutbound(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU007');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH007');
        $batch->setQuantity(80);

        $event = new StockOutboundEvent($batch, 'sale', 25, 'ORDER001', 'warehouse_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                80,
                'stock_outbound',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'sale' === $metadata['outbound_type']
                        && 25 === $metadata['outbound_quantity']
                        && 'ORDER001' === $metadata['order_no']
                        && 'warehouse_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockOutbound($event);
    }

    public function testOnStockReserved(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU008');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH008');
        $batch->setQuantity(120);

        $reservation = new StockReservation();
        $reservation->setSku($sku);
        $reservation->setQuantity(15);
        $reservation->setBusinessId('ORDER002');
        $reservation->setType(StockReservationType::ORDER);
        $reservation->setStatus(StockReservationStatus::PENDING);
        $reservation->setExpiresTime(new \DateTimeImmutable('+1 hour'));

        $event = new StockReservedEvent($batch, $reservation, 'reservation_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                120,
                'stock_reserved',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 15 === $metadata['reserved_quantity']
                        && 'order' === $metadata['reservation_type']
                        && 'ORDER002' === $metadata['business_id']
                        && 'reservation_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockReserved($event);
    }

    public function testOnStockLocked(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU009');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH009');
        $batch->setQuantity(90);

        $event = new StockLockedEvent($batch, 'quality_issue', 30, 'Quality inspection failed', 'inspector');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                90,
                'stock_locked',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'quality_issue' === $metadata['lock_type']
                        && 30 === $metadata['locked_quantity']
                        && 'Quality inspection failed' === $metadata['reason']
                        && 'inspector' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockLocked($event);
    }

    public function testHandleStockEventWithInvalidEventType(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU010');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH010');
        $batch->setQuantity(60);

        // 创建一个自定义事件来测试默认行为
        $event = new class($batch, 'test_operator') extends AbstractStockEvent {
            public function getEventType(): string
            {
                return 'unknown.event';
            }
        };

        // 对于未知事件类型，不应该创建快照
        $this->snapshotService->expects($this->never())
            ->method('createSnapshot')
        ;

        $this->listener->handleStockEvent($event);
    }

    public function testHandleStockEventDispatchesToCorrectMethod(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU011');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH011');
        $batch->setQuantity(110);

        $event = new StockAllocatedEvent($batch, 35, 'LIFO', 'dispatcher_test');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                110,
                'stock_allocated',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 35 === $metadata['allocated_quantity']
                        && 'LIFO' === $metadata['strategy'];
                })
            )
        ;

        // 测试 handleStockEvent 方法正确分发到 onStockAllocated
        $this->listener->handleStockEvent($event);
    }

    public function testOnStockCreated(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU012');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH012');
        $batch->setQuantity(200);

        $event = new StockCreatedEvent($batch, 'creator_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                200,
                'stock_created',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'creator_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockCreated($event);
    }

    public function testOnStockInbound(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU013');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH013');
        $batch->setQuantity(300);

        $event = new StockInboundEvent($batch, 'return', 100, 'RETURN001', 'inbound_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                300,
                'stock_inbound',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'return' === $metadata['inbound_type']
                        && 100 === $metadata['inbound_quantity']
                        && 'RETURN001' === $metadata['order_no']
                        && 'inbound_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockInbound($event);
    }

    public function testOnStockAdjusted(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU014');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH014');
        $batch->setQuantity(85);

        $event = new StockAdjustedEvent($batch, 'inventory', 15, 'Stock count adjustment', 'adjuster_operator');

        $this->snapshotService->expects($this->once())
            ->method('createSnapshot')
            ->with(
                self::callback(function ($skuParam) { return $skuParam instanceof Sku; }),
                85,
                'stock_adjusted',
                self::callback(function ($metadata): bool {
                    self::assertIsArray($metadata);

                    return 'inventory' === $metadata['adjustment_type']
                        && 15 === $metadata['adjustment_quantity']
                        && 'Stock count adjustment' === $metadata['reason']
                        && 'adjuster_operator' === $metadata['operator'];
                })
            )
        ;

        $this->listener->onStockAdjusted($event);
    }
}
