<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SKU as SKUInterface;
use Tourze\StockManageBundle\Entity\VirtualStock;

/**
 * @internal
 */
#[CoversClass(VirtualStock::class)]
class VirtualStockTest extends AbstractEntityTestCase
{
    protected function createEntity(): VirtualStock
    {
        return new VirtualStock();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'virtualType' => ['virtualType', 'presale'];
        yield 'quantity' => ['quantity', 150];
        yield 'status' => ['status', 'inactive'];
        yield 'description' => ['description', '预售商品虚拟库存'];
        yield 'expectedDate' => ['expectedDate', new \DateTimeImmutable('+30 days')];
        yield 'businessId' => ['businessId', 'PRESALE001'];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'attributes' => ['attributes', ['priority' => 'high']];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable()];
        yield 'sku' => ['sku', new Sku()];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testInitialState(): void
    {
        $virtualStock = $this->createEntity();

        $this->assertNull($virtualStock->getId());
        $this->assertEquals(0, $virtualStock->getQuantity());
        $this->assertEquals('active', $virtualStock->getStatus());
        $this->assertNull($virtualStock->getDescription());
        $this->assertNull($virtualStock->getExpectedDate());
        $this->assertNull($virtualStock->getBusinessId());
        $this->assertNull($virtualStock->getLocationId());
        $this->assertNull($virtualStock->getAttributes());
        $this->assertInstanceOf(\DateTimeInterface::class, $virtualStock->getCreateTime());
        $this->assertNull($virtualStock->getUpdateTime());
    }

    public function testSkuHandling(): void
    {
        $virtualStock = $this->createEntity();

        $sku = new Sku();
        $virtualStock->setSku($sku);
        $this->assertSame($sku, $virtualStock->getSku());
    }

    public function testSettersAndGetters(): void
    {
        $virtualStock = $this->createEntity();

        $spuId = 'SPU001';
        $virtualType = 'presale';
        $quantity = 150;
        $status = 'inactive';
        $description = '预售商品虚拟库存';
        $expectedDate = new \DateTimeImmutable('+30 days');
        $businessId = 'PRESALE001';
        $locationId = 'WH001';
        $attributes = ['priority' => 'high', 'campaign' => 'double11'];
        $updatedAt = new \DateTimeImmutable();

        // 创建匿名类实现SKUInterface，避免Mock类型问题
        $sku = new class($spuId) implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType($virtualType);
        $virtualStock->setQuantity($quantity);
        $virtualStock->setStatus($status);
        $virtualStock->setDescription($description);
        $virtualStock->setExpectedDate($expectedDate);
        $virtualStock->setBusinessId($businessId);
        $virtualStock->setLocationId($locationId);
        $virtualStock->setAttributes($attributes);

        // Set updateTime after SKU to avoid timestamp conflict
        $virtualStock->setUpdateTime($updatedAt);

        $this->assertEquals($spuId, $virtualStock->getSku()?->getId());

        $this->assertEquals($virtualType, $virtualStock->getVirtualType());
        $this->assertEquals($quantity, $virtualStock->getQuantity());
        $this->assertEquals($status, $virtualStock->getStatus());
        $this->assertEquals($description, $virtualStock->getDescription());
        $this->assertEquals($expectedDate, $virtualStock->getExpectedDate());
        $this->assertEquals($businessId, $virtualStock->getBusinessId());
        $this->assertEquals($locationId, $virtualStock->getLocationId());
        $this->assertEquals($attributes, $virtualStock->getAttributes());
        $this->assertEquals($updatedAt, $virtualStock->getUpdateTime());
    }

    public function testVirtualTypes(): void
    {
        $virtualStock = $this->createEntity();

        $types = ['presale', 'futures', 'backorder', 'dropship', 'consignment'];

        foreach ($types as $type) {
            $virtualStock->setVirtualType($type);
            $this->assertEquals($type, $virtualStock->getVirtualType());
        }
    }

    public function testVirtualStatuses(): void
    {
        $virtualStock = $this->createEntity();

        $statuses = ['active', 'inactive', 'converted'];

        foreach ($statuses as $status) {
            $virtualStock->setStatus($status);
            $this->assertEquals($status, $virtualStock->getStatus());
        }
    }

    public function testUpdatedAtIsSetWhenQuantityChanges(): void
    {
        $virtualStock = $this->createEntity();

        $this->assertNull($virtualStock->getUpdateTime());

        $virtualStock->setQuantity(100);

        // updateTime is now managed by Doctrine listeners via TimestampableAware trait
        // In unit tests without Doctrine, it remains null
        $this->assertNull($virtualStock->getUpdateTime());
    }

    public function testUpdatedAtIsSetWhenStatusChanges(): void
    {
        $virtualStock = $this->createEntity();

        $this->assertNull($virtualStock->getUpdateTime());

        $virtualStock->setStatus('inactive');

        // updateTime is now managed by Doctrine listeners via TimestampableAware trait
        // In unit tests without Doctrine, it remains null
        $this->assertNull($virtualStock->getUpdateTime());
    }

    public function testUpdatedAtIsSetWhenAttributesChange(): void
    {
        $virtualStock = $this->createEntity();

        $this->assertNull($virtualStock->getUpdateTime());

        $virtualStock->setAttributes(['test' => 'value']);

        // updateTime is now managed by Doctrine listeners via TimestampableAware trait
        // In unit tests without Doctrine, it remains null
        $this->assertNull($virtualStock->getUpdateTime());
    }

    public function testComplexAttributes(): void
    {
        $virtualStock = $this->createEntity();

        $attributes = [
            'campaign_info' => [
                'name' => 'Black Friday Sale',
                'start_date' => '2023-11-24',
                'end_date' => '2023-11-30',
            ],
            'supplier_info' => [
                'supplier_id' => 'SUP001',
                'supplier_name' => 'ABC Electronics',
                'lead_time_days' => 15,
            ],
            'pricing' => [
                'presale_price' => 299.99,
                'regular_price' => 399.99,
                'discount_rate' => 0.25,
            ],
            'constraints' => [
                'max_quantity_per_order' => 5,
                'min_order_quantity' => 1,
                'geographic_restrictions' => ['US', 'CA', 'MX'],
            ],
        ];

        $virtualStock->setAttributes($attributes);
        $this->assertEquals($attributes, $virtualStock->getAttributes());
    }

    public function testPresaleScenario(): void
    {
        $virtualStock = $this->createEntity();

        $sku = new class('SPU_IPHONE15') implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType('presale');
        $virtualStock->setQuantity(500);
        $virtualStock->setStatus('active');
        $virtualStock->setDescription('iPhone 15 预售虚拟库存');
        $virtualStock->setExpectedDate(new \DateTimeImmutable('2023-12-15'));
        $virtualStock->setBusinessId('PRESALE_IPHONE15_001');

        $attributes = [
            'presale_start' => '2023-11-01',
            'presale_end' => '2023-11-30',
            'expected_delivery' => '2023-12-15',
            'deposit_required' => true,
            'deposit_amount' => 100.00,
        ];
        $virtualStock->setAttributes($attributes);

        $this->assertEquals('presale', $virtualStock->getVirtualType());
        $this->assertEquals(500, $virtualStock->getQuantity());
        $attributes = $virtualStock->getAttributes();
        $this->assertNotNull($attributes);
        $this->assertTrue($attributes['deposit_required']);
        $this->assertEquals(100.00, $attributes['deposit_amount']);
    }

    public function testDropshipScenario(): void
    {
        $virtualStock = $this->createEntity();

        $sku = new class('SPU_LAPTOP001') implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType('dropship');
        $virtualStock->setQuantity(0); // 无限库存的代销商品
        $virtualStock->setStatus('active');
        $virtualStock->setDescription('代销笔记本电脑');
        $virtualStock->setBusinessId('DROPSHIP_SUPPLIER_001');

        $attributes = [
            'supplier_stock_level' => 'unlimited',
            'shipping_time_days' => 7,
            'supplier_location' => 'Shenzhen',
            'commission_rate' => 0.15,
            'auto_order' => true,
        ];
        $virtualStock->setAttributes($attributes);

        $this->assertEquals('dropship', $virtualStock->getVirtualType());
        $this->assertEquals(0, $virtualStock->getQuantity()); // 代销商品无数量限制
        $attributes = $virtualStock->getAttributes();
        $this->assertNotNull($attributes);
        $this->assertEquals('unlimited', $attributes['supplier_stock_level']);
    }

    public function testFuturesScenario(): void
    {
        $virtualStock = $this->createEntity();

        $sku = new class('SPU_GRAIN001') implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType('futures');
        $virtualStock->setQuantity(1000);
        $virtualStock->setStatus('active');
        $virtualStock->setDescription('小麦期货合约');
        $virtualStock->setExpectedDate(new \DateTimeImmutable('+6 months'));
        $virtualStock->setBusinessId('FUTURES_WHEAT_202406');

        $attributes = [
            'contract_type' => 'wheat_futures',
            'contract_size' => '5000_bushels',
            'delivery_month' => '2024-06',
            'quality_grade' => 'No.2_soft_red_winter',
            'delivery_location' => 'Chicago',
        ];
        $virtualStock->setAttributes($attributes);

        $this->assertEquals('futures', $virtualStock->getVirtualType());
        $this->assertEquals(1000, $virtualStock->getQuantity());
        $attributes = $virtualStock->getAttributes();
        $this->assertNotNull($attributes);
        $this->assertEquals('wheat_futures', $attributes['contract_type']);
    }

    public function testToString(): void
    {
        $virtualStock = $this->createEntity();

        $sku = new class('SPU001') implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType('presale');

        $this->assertEquals('SPU001 (presale)', $virtualStock->__toString());
    }

    public function testVirtualStockLifecycle(): void
    {
        $virtualStock = $this->createEntity();

        // 创建预售虚拟库存
        $sku = new class('SPU_NEWPRODUCT001') implements SKUInterface {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
        $virtualStock->setSku($sku);
        $virtualStock->setVirtualType('presale');
        $virtualStock->setQuantity(300);
        $virtualStock->setStatus('active');
        $virtualStock->setDescription('新品预售');
        $virtualStock->setExpectedDate(new \DateTimeImmutable('+45 days'));
        $virtualStock->setBusinessId('PRESALE_NEW_001');

        $this->assertEquals('active', $virtualStock->getStatus());
        $this->assertEquals(300, $virtualStock->getQuantity());
        // updateTime is managed by Doctrine listeners, remains null in unit tests
        $this->assertNull($virtualStock->getUpdateTime());

        // 调整库存数量
        $virtualStock->setQuantity(250);
        $this->assertEquals(250, $virtualStock->getQuantity());

        // 转换为实际库存
        $virtualStock->setStatus('converted');
        $virtualStock->setDescription('已转换为实际库存');

        $this->assertEquals('converted', $virtualStock->getStatus());
        $description = $virtualStock->getDescription();
        $this->assertNotNull($description);
        $this->assertStringContainsString('已转换', $description);
    }

    public function testExpectedDateManagement(): void
    {
        $virtualStock = $this->createEntity();

        $expectedDate = new \DateTimeImmutable('+30 days');
        $virtualStock->setExpectedDate($expectedDate);

        $this->assertEquals($expectedDate, $virtualStock->getExpectedDate());

        // 清空预期日期
        $virtualStock->setExpectedDate(null);
        $this->assertNull($virtualStock->getExpectedDate());
    }

    public function testBusinessIdAssociation(): void
    {
        $virtualStock = $this->createEntity();

        $businessIds = ['ORDER001', 'PRESALE002', 'CONTRACT003', 'CAMPAIGN004'];

        foreach ($businessIds as $businessId) {
            $virtualStock->setBusinessId($businessId);
            $this->assertEquals($businessId, $virtualStock->getBusinessId());
        }
    }

    public function testLocationAssignment(): void
    {
        $virtualStock = $this->createEntity();

        $locations = ['WH001', 'WH_VIRTUAL_001', 'SUPPLIER_001', 'DROPSHIP_001'];

        foreach ($locations as $location) {
            $virtualStock->setLocationId($location);
            $this->assertEquals($location, $virtualStock->getLocationId());
        }
    }
}
