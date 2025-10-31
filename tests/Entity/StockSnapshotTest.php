<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockSnapshot;

/**
 * @internal
 */
#[CoversClass(StockSnapshot::class)]
class StockSnapshotTest extends AbstractEntityTestCase
{
    protected function createEntity(): StockSnapshot
    {
        return new StockSnapshot();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'snapshotNo' => ['snapshotNo', 'SNAP20231201001'];
        yield 'type' => ['type', 'monthly'];
        yield 'triggerMethod' => ['triggerMethod', 'auto'];
        yield 'totalQuantity' => ['totalQuantity', 1500];
        yield 'totalValue' => ['totalValue', 75000.50];
        yield 'productCount' => ['productCount', 25];
        yield 'batchCount' => ['batchCount', 150];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'operator' => ['operator', 'system_auto'];
        yield 'notes' => ['notes', '月末自动快照'];
        yield 'metadata' => ['metadata', ['trigger_time' => '2023-12-01 00:00:00']];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2023-12-01 00:05:00')];
        yield 'validUntil' => ['validUntil', new \DateTimeImmutable('2024-01-01 00:00:00')];
        yield 'summary' => ['summary', ['warehouse_locations' => ['WH001', 'WH002']]];
        yield 'details' => ['details', ['SPU001' => ['name' => 'iPhone 15']]];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $snapshot = $this->createEntity();

        $this->assertNull($snapshot->getId());
        $this->assertEquals([], $snapshot->getSummary());
        $this->assertNull($snapshot->getDetails());
        $this->assertEquals(0, $snapshot->getTotalQuantity());
        $this->assertEquals(0.0, $snapshot->getTotalValue());
        $this->assertEquals(0, $snapshot->getProductCount());
        $this->assertEquals(0, $snapshot->getBatchCount());
        $this->assertNull($snapshot->getLocationId());
        $this->assertNull($snapshot->getOperator());
        $this->assertNull($snapshot->getNotes());
        $this->assertNull($snapshot->getMetadata());
        $this->assertInstanceOf(\DateTimeInterface::class, $snapshot->getCreateTime());
        $this->assertNull($snapshot->getValidUntil());
    }

    public function testSkuHandling(): void
    {
        $snapshot = $this->createEntity();

        $this->assertNull($snapshot->getSku());
        $sku = new Sku();
        $snapshot->setSku($sku);
        $this->assertSame($sku, $snapshot->getSku());
    }

    public function testSettersAndGetters(): void
    {
        $snapshot = $this->createEntity();

        $snapshotNo = 'SNAP20231201001';
        $type = 'monthly';
        $triggerMethod = 'auto';
        $totalQuantity = 1500;
        $totalValue = 75000.50;
        $productCount = 25;
        $batchCount = 150;
        $locationId = 'WH001';
        $operator = 'system_auto';
        $notes = '月末自动快照';
        $metadata = ['trigger_time' => '2023-12-01 00:00:00', 'duration' => 300];
        $createdAt = new \DateTimeImmutable('2023-12-01 00:05:00');
        $validUntil = new \DateTimeImmutable('2024-01-01 00:00:00');

        $snapshot->setSnapshotNo($snapshotNo);
        $snapshot->setType($type);
        $snapshot->setTriggerMethod($triggerMethod);
        $snapshot->setTotalQuantity($totalQuantity);
        $snapshot->setTotalValue($totalValue);
        $snapshot->setProductCount($productCount);
        $snapshot->setBatchCount($batchCount);
        $snapshot->setLocationId($locationId);
        $snapshot->setOperator($operator);
        $snapshot->setNotes($notes);
        $snapshot->setMetadata($metadata);
        $snapshot->setCreateTime($createdAt);
        $snapshot->setValidUntil($validUntil);

        $this->assertEquals($snapshotNo, $snapshot->getSnapshotNo());
        $this->assertEquals($type, $snapshot->getType());
        $this->assertEquals($triggerMethod, $snapshot->getTriggerMethod());
        $this->assertEquals($totalQuantity, $snapshot->getTotalQuantity());
        $this->assertEquals($totalValue, $snapshot->getTotalValue());
        $this->assertEquals($productCount, $snapshot->getProductCount());
        $this->assertEquals($batchCount, $snapshot->getBatchCount());
        $this->assertEquals($locationId, $snapshot->getLocationId());
        $this->assertEquals($operator, $snapshot->getOperator());
        $this->assertEquals($notes, $snapshot->getNotes());
        $this->assertEquals($metadata, $snapshot->getMetadata());
        $this->assertEquals($createdAt, $snapshot->getCreateTime());
        $this->assertEquals($validUntil, $snapshot->getValidUntil());
    }

    public function testSnapshotTypes(): void
    {
        $snapshot = $this->createEntity();

        $types = ['daily', 'monthly', 'inventory_count', 'temporary', 'emergency'];

        foreach ($types as $type) {
            $snapshot->setType($type);
            $this->assertEquals($type, $snapshot->getType());
        }
    }

    public function testTriggerMethods(): void
    {
        $snapshot = $this->createEntity();

        $methods = ['auto', 'manual', 'event', 'system', 'emergency'];

        foreach ($methods as $method) {
            $snapshot->setTriggerMethod($method);
            $this->assertEquals($method, $snapshot->getTriggerMethod());
        }
    }

    public function testSummaryData(): void
    {
        $snapshot = $this->createEntity();

        $summary = [
            'warehouse_locations' => ['WH001', 'WH002', 'WH003'],
            'categories' => [
                'electronics' => ['count' => 50, 'value' => 25000.00],
                'clothing' => ['count' => 100, 'value' => 15000.00],
                'books' => ['count' => 200, 'value' => 8000.00],
            ],
            'status_distribution' => [
                'available' => 300,
                'reserved' => 30,
                'locked' => 20,
            ],
            'alerts' => [
                'low_stock' => 5,
                'expired_soon' => 3,
                'quality_issues' => 2,
            ],
        ];

        $snapshot->setSummary($summary);
        $this->assertEquals($summary, $snapshot->getSummary());
    }

    public function testDetailedData(): void
    {
        $snapshot = $this->createEntity();

        $details = [
            'SPU001' => [
                'name' => 'iPhone 15',
                'batches' => [
                    'BATCH001' => ['quantity' => 50, 'cost' => 15000.00, 'location' => 'WH001'],
                    'BATCH002' => ['quantity' => 30, 'cost' => 9000.00, 'location' => 'WH002'],
                ],
                'total_quantity' => 80,
                'total_value' => 24000.00,
            ],
            'SPU002' => [
                'name' => 'MacBook Pro',
                'batches' => [
                    'BATCH003' => ['quantity' => 20, 'cost' => 40000.00, 'location' => 'WH001'],
                ],
                'total_quantity' => 20,
                'total_value' => 40000.00,
            ],
        ];

        $snapshot->setDetails($details);
        $this->assertEquals($details, $snapshot->getDetails());
    }

    public function testComplexMetadata(): void
    {
        $snapshot = $this->createEntity();

        $metadata = [
            'system_info' => [
                'version' => '2.1.0',
                'server' => 'stock-server-01',
                'database' => 'stock_db_prod',
            ],
            'performance_metrics' => [
                'generation_time_seconds' => 125.5,
                'memory_usage_mb' => 512,
                'records_processed' => 50000,
            ],
            'data_integrity' => [
                'checksum' => 'abc123def456',
                'record_count' => 50000,
                'verification_status' => 'passed',
            ],
            'triggers' => [
                'scheduled_task' => 'monthly_snapshot',
                'initiated_by' => 'cron_job',
                'backup_created' => true,
            ],
        ];

        $snapshot->setMetadata($metadata);
        $this->assertEquals($metadata, $snapshot->getMetadata());
    }

    public function testToString(): void
    {
        $snapshot = $this->createEntity();

        $snapshotNo = 'SNAP20231201001';
        $snapshot->setSnapshotNo($snapshotNo);

        $this->assertEquals($snapshotNo, $snapshot->__toString());
    }

    public function testSnapshotLifecycle(): void
    {
        $snapshot = $this->createEntity();

        // 创建快照
        $snapshot->setSnapshotNo('SNAP20231201001');
        $snapshot->setType('monthly');
        $snapshot->setTriggerMethod('auto');
        $snapshot->setOperator('system_scheduler');

        // 设置汇总数据
        $summary = [
            'total_products' => 250,
            'total_batches' => 1200,
            'warehouse_count' => 5,
            'category_count' => 8,
        ];
        $snapshot->setSummary($summary);

        // 设置统计信息
        $snapshot->setTotalQuantity(15000);
        $snapshot->setTotalValue(750000.00);
        $snapshot->setProductCount(250);
        $snapshot->setBatchCount(1200);

        // 设置有效期
        $validUntil = new \DateTimeImmutable('+30 days');
        $snapshot->setValidUntil($validUntil);

        $this->assertEquals('SNAP20231201001', $snapshot->getSnapshotNo());
        $this->assertEquals('monthly', $snapshot->getType());
        $this->assertEquals('auto', $snapshot->getTriggerMethod());
        $this->assertEquals($summary, $snapshot->getSummary());
        $this->assertEquals(15000, $snapshot->getTotalQuantity());
        $this->assertEquals(750000.00, $snapshot->getTotalValue());
        $this->assertEquals(250, $snapshot->getProductCount());
        $this->assertEquals(1200, $snapshot->getBatchCount());
        $this->assertEquals($validUntil, $snapshot->getValidUntil());
    }

    public function testEmergencySnapshot(): void
    {
        $snapshot = $this->createEntity();

        $snapshot->setSnapshotNo('SNAP_EMRG_20231201001');
        $snapshot->setType('emergency');
        $snapshot->setTriggerMethod('manual');
        $snapshot->setOperator('admin_001');
        $snapshot->setNotes('紧急盘点：发现系统异常，需要立即生成快照');

        $metadata = [
            'emergency_reason' => '系统数据异常',
            'initiated_by_user' => 'admin_001',
            'priority' => 'critical',
            'alert_level' => 'high',
        ];
        $snapshot->setMetadata($metadata);

        $this->assertEquals('emergency', $snapshot->getType());
        $this->assertEquals('manual', $snapshot->getTriggerMethod());
        $notes = $snapshot->getNotes();
        $this->assertNotNull($notes);
        $this->assertStringContainsString('紧急盘点', $notes);
        $metadata = $snapshot->getMetadata();
        $this->assertNotNull($metadata);
        $this->assertEquals('critical', $metadata['priority']);
    }

    public function testLocationSpecificSnapshot(): void
    {
        $snapshot = $this->createEntity();

        $snapshot->setSnapshotNo('SNAP_WH001_20231201001');
        $snapshot->setType('temporary');
        $snapshot->setTriggerMethod('manual');
        $snapshot->setLocationId('WH001');
        $snapshot->setOperator('warehouse_manager_001');

        // 单个仓库的快照数据
        $snapshot->setTotalQuantity(3500);
        $snapshot->setTotalValue(175000.00);
        $snapshot->setProductCount(85);
        $snapshot->setBatchCount(420);

        $this->assertEquals('WH001', $snapshot->getLocationId());
        $this->assertEquals(3500, $snapshot->getTotalQuantity());
        $this->assertEquals(85, $snapshot->getProductCount());
    }

    public function testFloatValuePrecision(): void
    {
        $snapshot = $this->createEntity();

        $preciseValue = 123456.789;
        $snapshot->setTotalValue($preciseValue);

        $this->assertEquals($preciseValue, $snapshot->getTotalValue());
    }
}
