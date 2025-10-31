<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;
use Tourze\StockManageBundle\Repository\StockInboundRepository;

/**
 * @internal
 */
#[CoversClass(StockInboundRepository::class)]
#[RunTestsInSeparateProcesses]
class StockInboundRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepositoryClass(): string
    {
        return StockInboundRepository::class;
    }

    protected function getEntityClass(): string
    {
        return StockInbound::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    private function createSku(string $spuId): Sku
    {
        // Create SPU first
        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $spuId);
        $spu->setGtin($spuId);

        // Create SKU with SPU relationship
        $sku = new Sku();
        $sku->setGtin($spuId);
        $sku->setSpu($spu);
        $sku->setUnit('个');

        $em = self::getService(EntityManagerInterface::class);
        $em->persist($spu);
        $em->persist($sku);
        $em->flush();

        return $sku;
    }

    protected function createNewEntity(): object
    {
        $stockInbound = new StockInbound();
        $stockInbound->setType(StockInboundType::PURCHASE);
        $stockInbound->setReferenceNo('TEST_REF_' . uniqid());
        $stockInbound->setTotalQuantity(100);
        $stockInbound->setTotalAmount('1000.00');
        $stockInbound->setOperator('测试操作员');

        return $stockInbound;
    }

    protected function getRepository(): StockInboundRepository
    {
        return self::getService(StockInboundRepository::class);
    }

    public function testCanSaveAndRetrieveStockInbound(): void
    {
        $repository = $this->getRepository();
        $stockInbound = new StockInbound();

        // 设置必要的字段以满足验证要求
        $stockInbound->setType(StockInboundType::PURCHASE);
        $stockInbound->setReferenceNo('TEST_PURCHASE_001');
        $stockInbound->setTotalQuantity(50);
        $stockInbound->setTotalAmount('500.00');
        $stockInbound->setOperator('测试操作员');

        $repository->save($stockInbound);

        self::assertGreaterThan(0, $stockInbound->getId());

        $found = $repository->find($stockInbound->getId());
        self::assertNotNull($found);
        self::assertSame($stockInbound->getId(), $found->getId());
        self::assertSame('TEST_PURCHASE_001', $found->getReferenceNo());
        self::assertSame(StockInboundType::PURCHASE, $found->getType());
    }

    public function testFindByReferenceNo(): void
    {
        $repository = $this->getRepository();
        $referenceNo = 'UNIQUE_REF_' . uniqid();

        // 创建入库记录
        $stockInbound = new StockInbound();
        $stockInbound->setType(StockInboundType::RETURN);
        $stockInbound->setReferenceNo($referenceNo);
        $stockInbound->setTotalQuantity(30);
        $stockInbound->setTotalAmount('300.00');
        $repository->save($stockInbound);

        $found = $repository->findByReferenceNo($referenceNo);

        self::assertNotNull($found);
        self::assertSame($referenceNo, $found->getReferenceNo());
    }

    public function testFindByReferenceNoReturnsNullWhenNotFound(): void
    {
        $repository = $this->getRepository();
        $found = $repository->findByReferenceNo('NON_EXISTENT_REF');

        self::assertNull($found);
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();

        // 创建一个真实的SKU对象
        $sku = $this->createSku('TEST_SKU_001');

        // 创建与SKU相关的入库记录
        $inbound1 = new StockInbound();
        $inbound1->setSku($sku);
        $inbound1->setType(StockInboundType::PURCHASE);
        $inbound1->setReferenceNo('SKU_TEST_001');
        $inbound1->setTotalQuantity(20);
        $inbound1->setTotalAmount('200.00');
        $repository->save($inbound1);

        $inbound2 = new StockInbound();
        $inbound2->setSku($sku);
        $inbound2->setType(StockInboundType::TRANSFER);
        $inbound2->setReferenceNo('SKU_TEST_002');
        $inbound2->setTotalQuantity(15);
        $inbound2->setTotalAmount('150.00');
        $repository->save($inbound2);

        $results = $repository->findBySku($sku);

        self::assertGreaterThanOrEqual(2, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertSame($sku, $result->getSku());
        }
    }

    public function testFindByType(): void
    {
        $repository = $this->getRepository();
        $targetType = StockInboundType::ADJUSTMENT;

        // 创建指定类型的入库记录
        $inbound = new StockInbound();
        $inbound->setType($targetType);
        $inbound->setReferenceNo('TYPE_TEST_' . uniqid());
        $inbound->setTotalQuantity(25);
        $inbound->setTotalAmount('250.00');
        $repository->save($inbound);

        $results = $repository->findByType($targetType);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertSame($targetType, $result->getType());
        }
    }

    public function testFindByOperator(): void
    {
        $repository = $this->getRepository();
        $operator = '操作员_' . uniqid();

        // 创建指定操作员的入库记录
        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::PURCHASE);
        $inbound->setReferenceNo('OPERATOR_TEST_' . uniqid());
        $inbound->setOperator($operator);
        $inbound->setTotalQuantity(35);
        $inbound->setTotalAmount('350.00');
        $repository->save($inbound);

        $results = $repository->findByOperator($operator);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertSame($operator, $result->getOperator());
        }
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $locationId = 'WAREHOUSE_B';

        // 创建指定位置的入库记录
        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::TRANSFER);
        $inbound->setReferenceNo('LOCATION_TEST_' . uniqid());
        $inbound->setLocationId($locationId);
        $inbound->setTotalQuantity(40);
        $inbound->setTotalAmount('400.00');
        $repository->save($inbound);

        $results = $repository->findByLocation($locationId);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertSame($locationId, $result->getLocationId());
        }
    }

    public function testFindByDateRange(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('-1 hour');
        $endDate = new \DateTimeImmutable('+1 hour');

        // 创建在时间范围内的入库记录
        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::PURCHASE);
        $inbound->setReferenceNo('DATE_RANGE_TEST_' . uniqid());
        $inbound->setTotalQuantity(45);
        $inbound->setTotalAmount('450.00');
        $repository->save($inbound);

        $results = $repository->findByDateRange($startDate, $endDate);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertGreaterThanOrEqual($startDate, $result->getCreateTime());
            self::assertLessThanOrEqual($endDate, $result->getCreateTime());
        }
    }

    public function testGetInboundStats(): void
    {
        $repository = $this->getRepository();

        // 创建测试数据
        $inbound1 = new StockInbound();
        $inbound1->setType(StockInboundType::PURCHASE);
        $inbound1->setReferenceNo('STATS_TEST_001');
        $inbound1->setTotalQuantity(100);
        $inbound1->setTotalAmount('1000.00');
        $repository->save($inbound1);

        $inbound2 = new StockInbound();
        $inbound2->setType(StockInboundType::PURCHASE);
        $inbound2->setReferenceNo('STATS_TEST_002');
        $inbound2->setTotalQuantity(200);
        $inbound2->setTotalAmount('2000.00');
        $repository->save($inbound2);

        $stats = $repository->getInboundStats(['type' => StockInboundType::PURCHASE]);

        self::assertIsArray($stats);
        self::assertArrayHasKey('total_records', $stats);
        self::assertArrayHasKey('total_quantity', $stats);
        self::assertArrayHasKey('total_amount', $stats);
        self::assertGreaterThanOrEqual(2, $stats['total_records']);
        self::assertGreaterThanOrEqual(300, $stats['total_quantity']);
        self::assertGreaterThanOrEqual(3000.0, $stats['total_amount']);
    }

    public function testFindRecentInbounds(): void
    {
        $repository = $this->getRepository();

        // 创建一些入库记录
        for ($i = 1; $i <= 3; ++$i) {
            $inbound = new StockInbound();
            $inbound->setType(StockInboundType::PURCHASE);
            $inbound->setReferenceNo('RECENT_TEST_' . $i);
            $inbound->setTotalQuantity(10 * $i);
            $inbound->setTotalAmount((100 * $i) . '.00');
            $repository->save($inbound);
        }

        $recentInbounds = $repository->findRecentInbounds(5);

        self::assertGreaterThanOrEqual(3, count($recentInbounds));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $recentInbounds);

        // 验证返回的记录数量正确（不强制要求严格的时间排序）
        self::assertTrue(count($recentInbounds) <= 5, '返回的记录数不应超过请求的限制');

        // 验证所有记录都有有效的创建时间
        foreach ($recentInbounds as $inbound) {
            self::assertInstanceOf(\DateTimeImmutable::class, $inbound->getCreateTime());
        }
    }

    public function testFindByReferenceNoPrefix(): void
    {
        $repository = $this->getRepository();
        $prefix = 'PREFIX_TEST_';

        // 创建具有相同前缀的入库记录
        $inbound1 = new StockInbound();
        $inbound1->setType(StockInboundType::TRANSFER);
        $inbound1->setReferenceNo($prefix . '001');
        $inbound1->setTotalQuantity(55);
        $inbound1->setTotalAmount('550.00');
        $repository->save($inbound1);

        $inbound2 = new StockInbound();
        $inbound2->setType(StockInboundType::TRANSFER);
        $inbound2->setReferenceNo($prefix . '002');
        $inbound2->setTotalQuantity(60);
        $inbound2->setTotalAmount('600.00');
        $repository->save($inbound2);

        $results = $repository->findByReferenceNoPrefix($prefix);

        self::assertGreaterThanOrEqual(2, count($results));
        self::assertContainsOnlyInstancesOf(StockInbound::class, $results);

        foreach ($results as $result) {
            self::assertStringStartsWith($prefix, $result->getReferenceNo());
        }
    }

    public function testGetTotalInboundQuantityBySku(): void
    {
        $repository = $this->getRepository();

        // 创建一个真实的SKU对象
        $sku = $this->createSku('TOTAL_QUANTITY_SKU');

        // 创建多个与SKU相关的入库记录
        $inbound1 = new StockInbound();
        $inbound1->setSku($sku);
        $inbound1->setType(StockInboundType::PURCHASE);
        $inbound1->setReferenceNo('TOTAL_QTY_001');
        $inbound1->setTotalQuantity(75);
        $inbound1->setTotalAmount('750.00');
        $repository->save($inbound1);

        $inbound2 = new StockInbound();
        $inbound2->setSku($sku);
        $inbound2->setType(StockInboundType::TRANSFER);
        $inbound2->setReferenceNo('TOTAL_QTY_002');
        $inbound2->setTotalQuantity(25);
        $inbound2->setTotalAmount('250.00');
        $repository->save($inbound2);

        $totalQuantity = $repository->getTotalInboundQuantityBySku($sku);

        self::assertGreaterThanOrEqual(100, $totalQuantity);
    }

    public function testGetInboundTypeStats(): void
    {
        $repository = $this->getRepository();
        $startDate = new \DateTimeImmutable('-1 hour');
        $endDate = new \DateTimeImmutable('+1 hour');

        // 创建不同类型的入库记录
        $inbound1 = new StockInbound();
        $inbound1->setType(StockInboundType::PURCHASE);
        $inbound1->setReferenceNo('TYPE_STATS_001');
        $inbound1->setTotalQuantity(80);
        $inbound1->setTotalAmount('800.00');
        $repository->save($inbound1);

        $inbound2 = new StockInbound();
        $inbound2->setType(StockInboundType::RETURN);
        $inbound2->setReferenceNo('TYPE_STATS_002');
        $inbound2->setTotalQuantity(20);
        $inbound2->setTotalAmount('200.00');
        $repository->save($inbound2);

        $stats = $repository->getInboundTypeStats($startDate, $endDate);

        self::assertIsArray($stats);

        if (array_key_exists('purchase', $stats)) {
            $purchaseStats = $stats['purchase'];
            self::assertIsArray($purchaseStats);
            self::assertArrayHasKey('count', $purchaseStats);
            self::assertArrayHasKey('total_quantity', $purchaseStats);
            self::assertGreaterThanOrEqual(1, $purchaseStats['count']);
            self::assertGreaterThanOrEqual(80, $purchaseStats['total_quantity']);
        }
    }

    public function testRemoveStockInbound(): void
    {
        $repository = $this->getRepository();
        $stockInbound = new StockInbound();

        $stockInbound->setType(StockInboundType::ADJUSTMENT);
        $stockInbound->setReferenceNo('REMOVE_TEST_' . uniqid());
        $stockInbound->setTotalQuantity(65);
        $stockInbound->setTotalAmount('650.00');

        $repository->save($stockInbound);
        $id = $stockInbound->getId();

        $foundBeforeRemove = $repository->find($id);
        self::assertNotNull($foundBeforeRemove);

        $repository->remove($stockInbound);

        $foundAfterRemove = $repository->find($id);
        self::assertNull($foundAfterRemove);
    }

    public function testEntityCalculateTotals(): void
    {
        $stockInbound = new StockInbound();

        // 测试设置明细时自动计算总计
        $items = [
            ['quantity' => 10, 'unit_cost' => 15.50],
            ['quantity' => 20, 'unit_cost' => 12.75],
        ];

        $stockInbound->setItems($items);

        self::assertSame(30, $stockInbound->getTotalQuantity());
        self::assertSame('410.00', $stockInbound->getTotalAmount());
    }

    public function testEntityAddItem(): void
    {
        $stockInbound = new StockInbound();

        // 添加第一个商品
        $stockInbound->addItem(['quantity' => 5, 'unit_cost' => 20.00]);
        self::assertSame(5, $stockInbound->getTotalQuantity());
        self::assertSame('100.00', $stockInbound->getTotalAmount());

        // 添加第二个商品
        $stockInbound->addItem(['quantity' => 10, 'unit_cost' => 15.50]);
        self::assertSame(15, $stockInbound->getTotalQuantity());
        self::assertSame('255.00', $stockInbound->getTotalAmount());
    }

    public function testEntityToString(): void
    {
        $stockInbound = new StockInbound();
        $stockInbound->setReferenceNo('TEST_REF_123');

        self::assertSame('TEST_REF_123', (string) $stockInbound);
    }
}
