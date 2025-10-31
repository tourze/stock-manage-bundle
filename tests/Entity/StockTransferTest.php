<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;

/**
 * @internal
 */
#[CoversClass(StockTransfer::class)]
class StockTransferTest extends AbstractEntityTestCase
{
    protected function createEntity(): StockTransfer
    {
        return new StockTransfer();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'transferNo' => ['transferNo', 'TRF20231201001'];
        yield 'fromLocation' => ['fromLocation', 'WH001'];
        yield 'toLocation' => ['toLocation', 'WH002'];
        yield 'status' => ['status', StockTransferStatus::IN_TRANSIT];
        yield 'initiator' => ['initiator', 'user_001'];
        yield 'receiver' => ['receiver', 'user_002'];
        yield 'reason' => ['reason', '库存调拨平衡各仓库存量'];
        yield 'shippedTime' => ['shippedTime', new \DateTimeImmutable('2023-12-01 10:00:00')];
        yield 'receivedTime' => ['receivedTime', new \DateTimeImmutable('2023-12-01 15:00:00')];
        yield 'metadata' => ['metadata', ['carrier' => 'internal_transport']];
        yield 'items' => ['items', [['spu_id' => 'SPU001', 'quantity' => 50]]];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $transfer = $this->createEntity();

        $this->assertNull($transfer->getId());
        $this->assertEquals([], $transfer->getItems());
        $this->assertEquals(0, $transfer->getTotalQuantity());
        $this->assertEquals(StockTransferStatus::PENDING, $transfer->getStatus());
        $this->assertNull($transfer->getInitiator());
        $this->assertNull($transfer->getReceiver());
        $this->assertNull($transfer->getReason());
        $this->assertNull($transfer->getShippedTime());
        $this->assertNull($transfer->getReceivedTime());
        $this->assertNull($transfer->getMetadata());
        $this->assertInstanceOf(\DateTimeInterface::class, $transfer->getCreateTime());
        $this->assertNull($transfer->getUpdateTime());
    }

    public function testSkuHandling(): void
    {
        $transfer = $this->createEntity();

        $this->assertNull($transfer->getSku());
        $sku = new Sku();
        $transfer->setSku($sku);
        $this->assertSame($sku, $transfer->getSku());
    }

    public function testSettersAndGetters(): void
    {
        $transfer = $this->createEntity();

        $transferNo = 'TRF20231201001';
        $fromLocation = 'WH001';
        $toLocation = 'WH002';
        $status = StockTransferStatus::IN_TRANSIT;
        $initiator = 'user_001';
        $receiver = 'user_002';
        $reason = '库存调拨平衡各仓库存量';
        $shippedAt = new \DateTimeImmutable('2023-12-01 10:00:00');
        $receivedAt = new \DateTimeImmutable('2023-12-01 15:00:00');
        $metadata = ['carrier' => 'internal_transport', 'vehicle' => 'VEH001'];

        $transfer->setTransferNo($transferNo);
        $transfer->setFromLocation($fromLocation);
        $transfer->setToLocation($toLocation);
        $transfer->setStatus($status);
        $transfer->setInitiator($initiator);
        $transfer->setReceiver($receiver);
        $transfer->setReason($reason);
        $transfer->setShippedTime($shippedAt);
        $transfer->setReceivedTime($receivedAt);
        $transfer->setMetadata($metadata);

        $this->assertEquals($transferNo, $transfer->getTransferNo());
        $this->assertEquals($fromLocation, $transfer->getFromLocation());
        $this->assertEquals($toLocation, $transfer->getToLocation());
        $this->assertEquals($status, $transfer->getStatus());
        $this->assertEquals($initiator, $transfer->getInitiator());
        $this->assertEquals($receiver, $transfer->getReceiver());
        $this->assertEquals($reason, $transfer->getReason());
        $this->assertEquals($shippedAt, $transfer->getShippedTime());
        $this->assertEquals($receivedAt, $transfer->getReceivedTime());
        $this->assertEquals($metadata, $transfer->getMetadata());
    }

    public function testItemsManagement(): void
    {
        $transfer = $this->createEntity();

        $items = [
            ['spu_id' => 'SPU001', 'batch_id' => 'BATCH001', 'quantity' => 50],
            ['spu_id' => 'SPU002', 'batch_id' => 'BATCH002', 'quantity' => 30],
            ['spu_id' => 'SPU003', 'batch_id' => 'BATCH003', 'quantity' => 20],
        ];

        $transfer->setItems($items);

        $this->assertEquals($items, $transfer->getItems());
        $this->assertEquals(100, $transfer->getTotalQuantity()); // 50 + 30 + 20
    }

    public function testStatusCheckMethods(): void
    {
        $transfer = $this->createEntity();

        $transfer->setStatus(StockTransferStatus::PENDING);
        $this->assertTrue($transfer->isPending());
        $this->assertFalse($transfer->isInTransit());
        $this->assertFalse($transfer->isReceived());
        $this->assertFalse($transfer->isCancelled());

        $transfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $this->assertFalse($transfer->isPending());
        $this->assertTrue($transfer->isInTransit());
        $this->assertFalse($transfer->isReceived());
        $this->assertFalse($transfer->isCancelled());

        $transfer->setStatus(StockTransferStatus::RECEIVED);
        $this->assertFalse($transfer->isPending());
        $this->assertFalse($transfer->isInTransit());
        $this->assertTrue($transfer->isReceived());
        $this->assertFalse($transfer->isCancelled());

        $transfer->setStatus(StockTransferStatus::CANCELLED);
        $this->assertFalse($transfer->isPending());
        $this->assertFalse($transfer->isInTransit());
        $this->assertFalse($transfer->isReceived());
        $this->assertTrue($transfer->isCancelled());
    }

    public function testUpdatedAtIsSetWhenStatusChanges(): void
    {
        $transfer = $this->createEntity();

        $this->assertNull($transfer->getUpdateTime());

        $transfer->setStatus(StockTransferStatus::IN_TRANSIT);

        // updateTime is now managed by Doctrine listeners via TimestampableAware trait
        // In unit tests without Doctrine, it remains null
        $this->assertNull($transfer->getUpdateTime());
    }

    public function testTotalQuantityCalculation(): void
    {
        $transfer = $this->createEntity();

        $this->assertEquals(0, $transfer->getTotalQuantity());

        $items = [
            ['spu_id' => 'SPU001', 'quantity' => 25],
            ['spu_id' => 'SPU002', 'quantity' => 35],
            ['spu_id' => 'SPU003', 'quantity' => 40],
        ];

        $transfer->setItems($items);
        $this->assertEquals(100, $transfer->getTotalQuantity());
    }

    public function testTotalQuantityCalculationWithIncompleteItems(): void
    {
        $transfer = $this->createEntity();

        $items = [
            ['spu_id' => 'SPU001'],
            ['spu_id' => 'SPU002', 'quantity' => 15],
            ['spu_id' => 'SPU003', 'quantity' => 25, 'notes' => 'fragile'],
        ];

        $transfer->setItems($items);
        $this->assertEquals(40, $transfer->getTotalQuantity()); // 0 + 15 + 25
    }

    public function testComplexItems(): void
    {
        $transfer = $this->createEntity();

        $items = [
            [
                'spu_id' => 'SPU001',
                'batch_id' => 'BATCH001',
                'quantity' => 30,
                'unit_cost' => 25.50,
                'expiry_date' => '2024-12-01',
                'notes' => 'Handle with care',
            ],
            [
                'spu_id' => 'SPU002',
                'batch_id' => 'BATCH002',
                'quantity' => 20,
                'unit_cost' => 45.00,
                'expiry_date' => '2024-06-15',
                'notes' => 'Refrigerated transport required',
            ],
        ];

        $transfer->setItems($items);

        $this->assertEquals($items, $transfer->getItems());
        $this->assertEquals(50, $transfer->getTotalQuantity());
    }

    public function testComplexMetadata(): void
    {
        $transfer = $this->createEntity();

        $metadata = [
            'transport_info' => [
                'carrier' => 'internal_logistics',
                'vehicle_id' => 'VEH001',
                'driver' => 'driver_001',
                'route' => 'WH001-WH002-direct',
            ],
            'packaging_info' => [
                'container_type' => 'standard_box',
                'container_count' => 5,
                'special_handling' => ['fragile', 'keep_upright'],
            ],
            'authorization' => [
                'approved_by' => 'manager_001',
                'approval_date' => '2023-12-01 09:00:00',
                'priority_level' => 'standard',
            ],
            'tracking' => [
                'tracking_number' => 'TRK20231201001',
                'estimated_arrival' => '2023-12-01 16:00:00',
                'checkpoints' => [],
            ],
        ];

        $transfer->setMetadata($metadata);
        $this->assertEquals($metadata, $transfer->getMetadata());
    }

    public function testToString(): void
    {
        $transfer = $this->createEntity();

        $transferNo = 'TRF20231201001';
        $transfer->setTransferNo($transferNo);

        $this->assertEquals($transferNo, $transfer->__toString());
    }

    public function testTransferLifecycle(): void
    {
        $transfer = $this->createEntity();

        // 创建调拨单
        $transfer->setTransferNo('TRF20231201001');
        $transfer->setFromLocation('WH001');
        $transfer->setToLocation('WH002');
        $transfer->setInitiator('warehouse_manager_001');
        $transfer->setReason('WH001库存过多，WH002库存不足，需要平衡库存');

        $items = [
            ['spu_id' => 'SPU001', 'batch_id' => 'BATCH001', 'quantity' => 50],
            ['spu_id' => 'SPU002', 'batch_id' => 'BATCH002', 'quantity' => 30],
        ];
        $transfer->setItems($items);

        $this->assertTrue($transfer->isPending());
        $this->assertEquals(80, $transfer->getTotalQuantity());

        // 发货
        $shippedAt = new \DateTimeImmutable();
        $transfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $transfer->setShippedTime($shippedAt);

        $this->assertTrue($transfer->isInTransit());
        $this->assertEquals($shippedAt, $transfer->getShippedTime());
        // updateTime is managed by Doctrine listeners, remains null in unit tests
        $this->assertNull($transfer->getUpdateTime());

        // 收货
        $receivedAt = new \DateTimeImmutable();
        $transfer->setStatus(StockTransferStatus::RECEIVED);
        $transfer->setReceivedTime($receivedAt);
        $transfer->setReceiver('warehouse_staff_002');

        $this->assertTrue($transfer->isReceived());
        $this->assertEquals($receivedAt, $transfer->getReceivedTime());
        $this->assertEquals('warehouse_staff_002', $transfer->getReceiver());
    }

    public function testCancelledTransfer(): void
    {
        $transfer = $this->createEntity();

        $transfer->setTransferNo('TRF20231201002');
        $transfer->setFromLocation('WH001');
        $transfer->setToLocation('WH003');
        $transfer->setStatus(StockTransferStatus::PENDING);

        $items = [
            ['spu_id' => 'SPU001', 'quantity' => 25],
        ];
        $transfer->setItems($items);

        $this->assertTrue($transfer->isPending());
        $this->assertEquals(25, $transfer->getTotalQuantity());

        // 取消调拨
        $transfer->setStatus(StockTransferStatus::CANCELLED);
        $transfer->setReason('目标仓库暂时关闭维护，取消调拨');

        $this->assertTrue($transfer->isCancelled());
        $reason = $transfer->getReason();
        $this->assertNotNull($reason);
        $this->assertStringContainsString('取消调拨', $reason);
    }

    public function testEmptyItemsCalculation(): void
    {
        $transfer = $this->createEntity();

        $transfer->setItems([]);
        $this->assertEquals(0, $transfer->getTotalQuantity());
    }

    public function testLocationTransfer(): void
    {
        $transfer = $this->createEntity();

        $fromLocation = 'WH_MAIN_001';
        $toLocation = 'WH_BRANCH_002';

        $transfer->setFromLocation($fromLocation);
        $transfer->setToLocation($toLocation);

        $this->assertEquals($fromLocation, $transfer->getFromLocation());
        $this->assertEquals($toLocation, $transfer->getToLocation());
    }

    public function testTransferTiming(): void
    {
        $transfer = $this->createEntity();

        $createdAt = new \DateTimeImmutable('2023-12-01 09:00:00');
        $shippedAt = new \DateTimeImmutable('2023-12-01 10:30:00');
        $receivedAt = new \DateTimeImmutable('2023-12-01 14:15:00');

        $transfer->setShippedTime($shippedAt);
        $transfer->setReceivedTime($receivedAt);

        $this->assertEquals($shippedAt, $transfer->getShippedTime());
        $this->assertEquals($receivedAt, $transfer->getReceivedTime());

        // 验证运输时长（示例计算）
        $transitTime = $receivedAt->getTimestamp() - $shippedAt->getTimestamp();
        $this->assertEquals(13500, $transitTime); // 3小时45分钟 = 13500秒
    }
}
