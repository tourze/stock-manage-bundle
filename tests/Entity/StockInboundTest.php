<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;

/**
 * @internal
 */
#[CoversClass(StockInbound::class)]
class StockInboundTest extends AbstractEntityTestCase
{
    protected function createEntity(): StockInbound
    {
        return new StockInbound();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'type' => ['type', StockInboundType::PURCHASE];
        yield 'referenceNo' => ['referenceNo', 'IN20231201001'];
        yield 'operator' => ['operator', 'warehouse_001'];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'remark' => ['remark', '采购入库备注'];
        yield 'metadata' => ['metadata', ['supplier' => 'SUP001']];
        yield 'items' => ['items', [['spu_id' => 'SPU001', 'quantity' => 100]]];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $inbound = $this->createEntity();

        $this->assertNull($inbound->getId());
        $this->assertEquals([], $inbound->getItems());
        $this->assertEquals('0.00', $inbound->getTotalAmount());
        $this->assertEquals(0, $inbound->getTotalQuantity());
        $this->assertNull($inbound->getOperator());
        $this->assertNull($inbound->getLocationId());
        $this->assertNull($inbound->getRemark());
        $this->assertNull($inbound->getMetadata());
        $this->assertNull($inbound->getCreateTime()); // In unit tests without Doctrine, createTime remains null
    }

    public function testSkuHandling(): void
    {
        $inbound = $this->createEntity();

        $this->assertNull($inbound->getSku());
        $sku = new Sku();
        $inbound->setSku($sku);
        $this->assertSame($sku, $inbound->getSku());
    }

    public function testItemsManagement(): void
    {
        $inbound = $this->createEntity();

        $items = [
            ['spu_id' => 'SPU001', 'batch_id' => 'BATCH001', 'quantity' => 100, 'unit_cost' => 10.50],
            ['spu_id' => 'SPU002', 'batch_id' => 'BATCH002', 'quantity' => 50, 'unit_cost' => 20.00],
        ];

        $inbound->setItems($items);

        $this->assertEquals($items, $inbound->getItems());
        $this->assertEquals(150, $inbound->getTotalQuantity());
        $this->assertEquals('2050.00', $inbound->getTotalAmount()); // 100*10.5 + 50*20 = 2050
    }

    public function testAddItem(): void
    {
        $inbound = $this->createEntity();

        $item1 = ['spu_id' => 'SPU001', 'batch_id' => 'BATCH001', 'quantity' => 50, 'unit_cost' => 15.00];
        $item2 = ['spu_id' => 'SPU002', 'batch_id' => 'BATCH002', 'quantity' => 30, 'unit_cost' => 25.00];

        $inbound->addItem($item1);
        $this->assertCount(1, $inbound->getItems());
        $this->assertEquals(50, $inbound->getTotalQuantity());
        $this->assertEquals('750.00', $inbound->getTotalAmount());

        $inbound->addItem($item2);
        $this->assertCount(2, $inbound->getItems());
        $this->assertEquals(80, $inbound->getTotalQuantity());
        $this->assertEquals('1500.00', $inbound->getTotalAmount()); // 750 + 750
    }

    public function testInboundTypes(): void
    {
        $inbound = $this->createEntity();

        $types = [
            StockInboundType::PURCHASE,
            StockInboundType::TRANSFER,
            StockInboundType::RETURN,
            StockInboundType::ADJUSTMENT,
            StockInboundType::PRODUCTION,
        ];

        foreach ($types as $type) {
            $inbound->setType($type);
            $this->assertEquals($type, $inbound->getType());
        }
    }

    public function testCalculationWithEmptyItems(): void
    {
        $inbound = $this->createEntity();

        $inbound->setItems([]);

        $this->assertEquals(0, $inbound->getTotalQuantity());
        $this->assertEquals('0.00', $inbound->getTotalAmount());
    }

    public function testCalculationWithIncompleteItems(): void
    {
        $inbound = $this->createEntity();

        $items = [
            ['spu_id' => 'SPU001'],
            ['spu_id' => 'SPU002', 'quantity' => 50],
            ['spu_id' => 'SPU003', 'quantity' => 30, 'unit_cost' => 10.00],
        ];

        $inbound->setItems($items);

        $this->assertEquals(80, $inbound->getTotalQuantity()); // 0 + 50 + 30
        $this->assertEquals('300.00', $inbound->getTotalAmount()); // 0 + 0 + 300
    }

    public function testComplexMetadata(): void
    {
        $inbound = $this->createEntity();

        $metadata = [
            'supplier_id' => 'SUP001',
            'purchase_order' => 'PO20231201001',
            'delivery_date' => '2023-12-01',
            'shipping_info' => [
                'carrier' => 'DHL',
                'tracking_number' => 'DHL123456789',
                'delivery_address' => 'Warehouse A',
            ],
            'quality_check' => [
                'inspector' => 'QC001',
                'checked_at' => '2023-12-01 10:00:00',
                'passed' => true,
            ],
        ];

        $inbound->setMetadata($metadata);
        $this->assertEquals($metadata, $inbound->getMetadata());
    }

    public function testToString(): void
    {
        $inbound = $this->createEntity();

        $referenceNo = 'IN20231201001';
        $inbound->setReferenceNo($referenceNo);

        $this->assertEquals($referenceNo, $inbound->__toString());
    }

    public function testInboundLifecycle(): void
    {
        $inbound = $this->createEntity();

        // 创建入库记录
        $inbound->setType(StockInboundType::PURCHASE);
        $inbound->setReferenceNo('IN20231201001');
        $inbound->setOperator('warehouse_staff_001');
        $inbound->setLocationId('WH001');

        $items = [
            ['spu_id' => 'SPU001', 'batch_id' => 'BATCH001', 'quantity' => 100, 'unit_cost' => 15.50],
            ['spu_id' => 'SPU002', 'batch_id' => 'BATCH002', 'quantity' => 50, 'unit_cost' => 32.00],
        ];

        $inbound->setItems($items);

        $this->assertEquals(StockInboundType::PURCHASE, $inbound->getType());
        $this->assertEquals(150, $inbound->getTotalQuantity());
        $this->assertEquals('3150.00', $inbound->getTotalAmount()); // 100*15.5 + 50*32

        // 添加备注和元数据
        $inbound->setRemark('质量良好，按时到货');
        $inbound->setMetadata([
            'supplier_id' => 'SUP001',
            'inspector' => 'QC001',
            'quality_rating' => 'A',
        ]);

        $this->assertNotNull($inbound->getRemark());
        $this->assertNotNull($inbound->getMetadata());
    }

    public function testPrecisionCalculation(): void
    {
        $inbound = $this->createEntity();

        $items = [
            ['spu_id' => 'SPU001', 'quantity' => 3, 'unit_cost' => 10.333],
            ['spu_id' => 'SPU002', 'quantity' => 7, 'unit_cost' => 15.666],
        ];

        $inbound->setItems($items);

        $this->assertEquals(10, $inbound->getTotalQuantity());
        // 3*10.333 + 7*15.666 = 30.999 + 109.662 = 140.661 -> 140.66 (rounded to 2 decimals)
        $this->assertEquals('140.66', $inbound->getTotalAmount());
    }
}
