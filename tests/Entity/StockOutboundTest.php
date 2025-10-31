<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;

/**
 * @internal
 */
#[CoversClass(StockOutbound::class)]
class StockOutboundTest extends AbstractEntityTestCase
{
    protected function createEntity(): StockOutbound
    {
        return new StockOutbound();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'type' => ['type', StockOutboundType::SALES];
        yield 'referenceNo' => ['referenceNo', 'OUT20231201001'];
        yield 'operator' => ['operator', 'warehouse_001'];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'remark' => ['remark', '销售出库备注'];
        yield 'metadata' => ['metadata', ['customer' => 'CUST001']];
        yield 'items' => ['items', [['spu_id' => 'SPU001', 'quantity' => 50]]];
        yield 'allocations' => ['allocations', [['batch_id' => 'BATCH001', 'quantity' => 30]]];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $outbound = $this->createEntity();

        $this->assertNull($outbound->getId());
        $this->assertEquals([], $outbound->getItems());
        $this->assertEquals([], $outbound->getAllocations());
        $this->assertEquals('0.00', $outbound->getTotalCost());
        $this->assertEquals(0, $outbound->getTotalQuantity());
        $this->assertNull($outbound->getOperator());
        $this->assertNull($outbound->getLocationId());
        $this->assertNull($outbound->getRemark());
        $this->assertNull($outbound->getMetadata());
        $this->assertNull($outbound->getCreateTime()); // In unit tests without Doctrine, createTime remains null
    }

    public function testSkuHandling(): void
    {
        $outbound = $this->createEntity();

        $this->assertNull($outbound->getSku());
        $sku = new Sku();
        $outbound->setSku($sku);
        $this->assertSame($sku, $outbound->getSku());
    }

    public function testSettersAndGetters(): void
    {
        $outbound = $this->createEntity();

        $type = StockOutboundType::SALES;
        $referenceNo = 'OUT20231201001';
        $operator = 'warehouse_001';
        $locationId = 'WH001';
        $remark = '销售出库备注';
        $metadata = ['customer' => 'CUST001', 'order_number' => 'ORDER001'];

        $outbound->setType($type);
        $outbound->setReferenceNo($referenceNo);
        $outbound->setOperator($operator);
        $outbound->setLocationId($locationId);
        $outbound->setRemark($remark);
        $outbound->setMetadata($metadata);

        $this->assertEquals($type, $outbound->getType());
        $this->assertEquals($referenceNo, $outbound->getReferenceNo());
        $this->assertEquals($operator, $outbound->getOperator());
        $this->assertEquals($locationId, $outbound->getLocationId());
        $this->assertEquals($remark, $outbound->getRemark());
        $this->assertEquals($metadata, $outbound->getMetadata());
    }

    public function testItemsManagement(): void
    {
        $outbound = $this->createEntity();

        $items = [
            'item1' => ['spu_id' => 'SPU001', 'quantity' => 50, 'unit_price' => 25.00],
            'item2' => ['spu_id' => 'SPU002', 'quantity' => 30, 'unit_price' => 40.00],
        ];

        $outbound->setItems($items);

        $this->assertEquals($items, $outbound->getItems());
        $this->assertEquals(80, $outbound->getTotalQuantity()); // 50 + 30
    }

    public function testAllocationsManagement(): void
    {
        $outbound = $this->createEntity();

        $allocations = [
            'alloc1' => ['batch_id' => 'BATCH001', 'quantity' => 30, 'unit_cost' => 15.00],
            'alloc2' => ['batch_id' => 'BATCH002', 'quantity' => 50, 'unit_cost' => 20.00],
        ];

        $outbound->setAllocations($allocations);

        $this->assertEquals($allocations, $outbound->getAllocations());
        $this->assertEquals('1450.00', $outbound->getTotalCost()); // 30*15 + 50*20 = 450 + 1000 = 1450
    }

    public function testOutboundTypes(): void
    {
        $outbound = $this->createEntity();

        $types = [
            StockOutboundType::SALES,
            StockOutboundType::TRANSFER,
            StockOutboundType::DAMAGE,
            StockOutboundType::ADJUSTMENT,
            StockOutboundType::SAMPLE,
        ];

        foreach ($types as $type) {
            $outbound->setType($type);
            $this->assertEquals($type, $outbound->getType());
        }
    }

    public function testCalculationWithEmptyData(): void
    {
        $outbound = $this->createEntity();

        $outbound->setItems([]);
        $outbound->setAllocations([]);

        $this->assertEquals(0, $outbound->getTotalQuantity());
        $this->assertEquals('0.00', $outbound->getTotalCost());
    }

    public function testCalculationWithIncompleteData(): void
    {
        $outbound = $this->createEntity();

        $items = [
            'item1' => ['spu_id' => 'SPU001'],
            'item2' => ['spu_id' => 'SPU002', 'quantity' => 25],
            'item3' => ['spu_id' => 'SPU003', 'quantity' => 35, 'unit_price' => 15.00],
        ];

        $allocations = [
            'alloc1' => ['batch_id' => 'BATCH001'],
            'alloc2' => ['batch_id' => 'BATCH002', 'quantity' => 20],
            'alloc3' => ['batch_id' => 'BATCH003', 'quantity' => 40, 'unit_cost' => 12.50],
        ];

        $outbound->setItems($items);
        $outbound->setAllocations($allocations);

        $this->assertEquals(60, $outbound->getTotalQuantity()); // 0 + 25 + 35
        $this->assertEquals('500.00', $outbound->getTotalCost()); // 0 + 0 + 500
    }

    public function testComplexAllocations(): void
    {
        $outbound = $this->createEntity();

        $allocations = [
            'alloc1' => [
                'batch_id' => 'BATCH001',
                'spu_id' => 'SPU001',
                'quantity' => 25,
                'unit_cost' => 18.75,
                'production_date' => '2023-11-01',
                'expiry_date' => '2024-11-01',
            ],
            'alloc2' => [
                'batch_id' => 'BATCH002',
                'spu_id' => 'SPU002',
                'quantity' => 15,
                'unit_cost' => 32.50,
                'production_date' => '2023-10-15',
                'expiry_date' => '2024-10-15',
            ],
        ];

        $outbound->setAllocations($allocations);

        $this->assertEquals($allocations, $outbound->getAllocations());
        $this->assertEquals('956.25', $outbound->getTotalCost()); // 25*18.75 + 15*32.5 = 468.75 + 487.5 = 956.25
    }

    public function testComplexMetadata(): void
    {
        $outbound = $this->createEntity();

        $metadata = [
            'customer_id' => 'CUST001',
            'order_id' => 'ORDER20231201001',
            'shipping_address' => [
                'name' => '张三',
                'phone' => '13800138000',
                'address' => '北京市朝阳区xxx街道xxx号',
                'zip_code' => '100000',
            ],
            'delivery_info' => [
                'carrier' => 'SF Express',
                'tracking_number' => 'SF1234567890',
                'delivery_time' => '2023-12-02 14:30:00',
            ],
            'payment_info' => [
                'method' => 'alipay',
                'transaction_id' => 'ALI20231201001',
                'amount' => '2500.00',
            ],
        ];

        $outbound->setMetadata($metadata);
        $this->assertEquals($metadata, $outbound->getMetadata());
    }

    public function testToString(): void
    {
        $outbound = $this->createEntity();

        $referenceNo = 'OUT20231201001';
        $outbound->setReferenceNo($referenceNo);

        $this->assertEquals($referenceNo, $outbound->__toString());
    }

    public function testOutboundLifecycle(): void
    {
        $outbound = $this->createEntity();

        // 创建出库记录
        $outbound->setType(StockOutboundType::SALES);
        $outbound->setReferenceNo('OUT20231201001');
        $outbound->setOperator('warehouse_staff_001');
        $outbound->setLocationId('WH001');

        $items = [
            'item1' => ['spu_id' => 'SPU001', 'quantity' => 20, 'unit_price' => 50.00],
            'item2' => ['spu_id' => 'SPU002', 'quantity' => 10, 'unit_price' => 80.00],
        ];

        $allocations = [
            'alloc1' => ['batch_id' => 'BATCH001', 'quantity' => 20, 'unit_cost' => 30.00],
            'alloc2' => ['batch_id' => 'BATCH002', 'quantity' => 10, 'unit_cost' => 45.00],
        ];

        $outbound->setItems($items);
        $outbound->setAllocations($allocations);

        $this->assertEquals(StockOutboundType::SALES, $outbound->getType());
        $this->assertEquals(30, $outbound->getTotalQuantity());
        $this->assertEquals('1050.00', $outbound->getTotalCost()); // 20*30 + 10*45 = 600 + 450 = 1050

        // 添加备注和元数据
        $outbound->setRemark('正常销售出库');
        $outbound->setMetadata([
            'customer_id' => 'CUST001',
            'sales_rep' => 'SALES001',
            'priority' => 'standard',
        ]);

        $this->assertNotNull($outbound->getRemark());
        $this->assertNotNull($outbound->getMetadata());
    }

    public function testPrecisionCalculation(): void
    {
        $outbound = $this->createEntity();

        $allocations = [
            'alloc1' => ['batch_id' => 'BATCH001', 'quantity' => 7, 'unit_cost' => 12.333],
            'alloc2' => ['batch_id' => 'BATCH002', 'quantity' => 5, 'unit_cost' => 18.666],
        ];

        $outbound->setAllocations($allocations);

        // 7*12.333 + 5*18.666 = 86.331 + 93.33 = 179.661 -> 179.66 (rounded to 2 decimals)
        $this->assertEquals('179.66', $outbound->getTotalCost());
    }

    public function testSeparateItemsAndAllocationsCalculation(): void
    {
        $outbound = $this->createEntity();

        // 测试商品数量和成本分别计算
        $items = [
            'item1' => ['spu_id' => 'SPU001', 'quantity' => 100],
            'item2' => ['spu_id' => 'SPU002', 'quantity' => 50],
        ];

        $allocations = [
            'alloc1' => ['batch_id' => 'BATCH001', 'quantity' => 60, 'unit_cost' => 10.00],
            'alloc2' => ['batch_id' => 'BATCH002', 'quantity' => 90, 'unit_cost' => 15.00],
        ];

        $outbound->setItems($items);
        $outbound->setAllocations($allocations);

        $this->assertEquals(150, $outbound->getTotalQuantity()); // from items: 100 + 50
        $this->assertEquals('1950.00', $outbound->getTotalCost()); // from allocations: 60*10 + 90*15 = 600 + 1350
    }
}
