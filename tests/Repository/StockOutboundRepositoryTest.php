<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;
use Tourze\StockManageBundle\Repository\StockOutboundRepository;

/**
 * @internal
 */
#[CoversClass(StockOutboundRepository::class)]
#[RunTestsInSeparateProcesses]
class StockOutboundRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Repository test setup
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

    protected function getRepository(): StockOutboundRepository
    {
        return self::getService(StockOutboundRepository::class);
    }

    protected function createNewEntity(): StockOutbound
    {
        $stockOutbound = new StockOutbound();
        // 设置默认的Type，避免测试失败，但测试方法可以覆盖这个设置
        $stockOutbound->setType(StockOutboundType::SALES);
        $stockOutbound->setReferenceNo('OUT_' . uniqid());
        $stockOutbound->setSku($this->createSku('SPU_' . uniqid()));
        $stockOutbound->setItems([
            'items' => [
                ['sku_id' => 1, 'quantity' => 10, 'unit_cost' => 5.0],
            ],
        ]);
        $stockOutbound->setTotalQuantity(10);
        $stockOutbound->setTotalCost('50.00');
        $stockOutbound->setLocationId('LOC_' . uniqid());
        $stockOutbound->setOperator('operator_' . uniqid());
        $stockOutbound->setRemark('Test outbound');

        return $stockOutbound;
    }

    /**
     * 创建一个带有指定类型的新实体.
     */
    private function createNewEntityWithType(StockOutboundType $type): StockOutbound
    {
        $entity = $this->createNewEntity();
        $entity->setType($type);

        return $entity;
    }

    public function testBasicRepositoryFunctionality(): void
    {
        $outbound = $this->createNewEntity();

        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($outbound);
        $em->flush();

        $this->assertNotNull($outbound->getId());

        $foundOutbound = $repository->find($outbound->getId());
        $this->assertNotNull($foundOutbound);
        $this->assertEquals($outbound->getReferenceNo(), $foundOutbound->getReferenceNo());
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();
        $outbound = $this->createNewEntity();

        // Test save
        $repository->save($outbound);
        $this->assertNotNull($outbound->getId());

        $id = $outbound->getId();
        $found = $repository->find($id);
        $this->assertNotNull($found);

        // Test remove
        $repository->remove($outbound);
        $found = $repository->find($id);
        $this->assertNull($found);
    }

    public function testFindByReferenceNo(): void
    {
        $repository = $this->getRepository();
        $outbound = $this->createNewEntity();
        $referenceNo = 'UNIQUE_REF_001';
        $outbound->setReferenceNo($referenceNo);

        $repository->save($outbound);

        $found = $repository->findByReferenceNo($referenceNo);
        $this->assertNotNull($found);
        $this->assertEquals($referenceNo, $found->getReferenceNo());

        $notFound = $repository->findByReferenceNo('NONEXISTENT_REF');
        $this->assertNull($notFound);
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_001');

        $outbound1 = $this->createNewEntity();
        $outbound1->setSku($sku);

        $outbound2 = $this->createNewEntity();
        $outbound2->setSku($this->createSku('TEST_SKU_002'));

        $repository->save($outbound1);
        $repository->save($outbound2);

        $outbounds = $repository->findBySku($sku);

        $this->assertCount(1, $outbounds);
        $this->assertContains($outbound1, $outbounds);
        $this->assertNotContains($outbound2, $outbounds);
    }

    public function testFindByType(): void
    {
        $repository = $this->getRepository();

        $outbound1 = $this->createNewEntityWithType(StockOutboundType::SALES);
        $outbound2 = $this->createNewEntityWithType(StockOutboundType::DAMAGE);

        $repository->save($outbound1);
        $repository->save($outbound2);

        $saleOutbounds = $repository->findByType(StockOutboundType::SALES);
        $damageOutbounds = $repository->findByType(StockOutboundType::DAMAGE);

        // 检查结果集包含我们创建的实体，而不是精确计数
        $this->assertGreaterThanOrEqual(1, count($saleOutbounds));
        $this->assertContains($outbound1, $saleOutbounds);

        $this->assertGreaterThanOrEqual(1, count($damageOutbounds));
        $this->assertContains($outbound2, $damageOutbounds);

        // 确保实体不在错误的类型结果中
        $this->assertNotContains($outbound1, $damageOutbounds);
        $this->assertNotContains($outbound2, $saleOutbounds);
    }

    public function testFindByOperator(): void
    {
        $repository = $this->getRepository();
        $operator = 'test_operator';

        $outbound1 = $this->createNewEntity();
        $outbound1->setOperator($operator);

        $outbound2 = $this->createNewEntity();
        $outbound2->setOperator('other_operator');

        $repository->save($outbound1);
        $repository->save($outbound2);

        $outbounds = $repository->findByOperator($operator);

        $this->assertCount(1, $outbounds);
        $this->assertContains($outbound1, $outbounds);
        $this->assertNotContains($outbound2, $outbounds);
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $locationId = 'WAREHOUSE_A';

        $outbound1 = $this->createNewEntity();
        $outbound1->setLocationId($locationId);

        $outbound2 = $this->createNewEntity();
        $outbound2->setLocationId('WAREHOUSE_B');

        $repository->save($outbound1);
        $repository->save($outbound2);

        $outbounds = $repository->findByLocation($locationId);

        $this->assertCount(1, $outbounds);
        $this->assertContains($outbound1, $outbounds);
        $this->assertNotContains($outbound2, $outbounds);
    }

    public function testFindByDateRange(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $outbound1 = $this->createNewEntity();
        $outbound2 = $this->createNewEntity();

        $repository->save($outbound1);
        $repository->save($outbound2);

        // Manually set create time within range for testing
        $em->createQuery('UPDATE ' . StockOutbound::class . ' o SET o.createTime = :date WHERE o.id = :id')
            ->setParameter('date', new \DateTimeImmutable('2023-01-15'))
            ->setParameter('id', $outbound1->getId())
            ->execute()
        ;

        // Set create time outside range
        $em->createQuery('UPDATE ' . StockOutbound::class . ' o SET o.createTime = :date WHERE o.id = :id')
            ->setParameter('date', new \DateTimeImmutable('2023-02-15'))
            ->setParameter('id', $outbound2->getId())
            ->execute()
        ;

        $outbounds = $repository->findByDateRange($startDate, $endDate);

        $this->assertCount(1, $outbounds);
        $this->assertEquals($outbound1->getId(), $outbounds[0]->getId());
    }

    public function testGetOutboundStats(): void
    {
        $repository = $this->getRepository();

        // 使用唯一的location ID来避免与fixture数据冲突
        $uniqueLocationA = 'TEST_LOC_A_' . uniqid();
        $uniqueLocationB = 'TEST_LOC_B_' . uniqid();

        $outbound1 = $this->createNewEntity();
        $outbound1->setTotalQuantity(10);
        $outbound1->setTotalCost('100.00');
        $outbound1->setType(StockOutboundType::SALES);
        $outbound1->setLocationId($uniqueLocationA);

        $outbound2 = $this->createNewEntity();
        $outbound2->setTotalQuantity(20);
        $outbound2->setTotalCost('200.00');
        $outbound2->setType(StockOutboundType::SALES);
        $outbound2->setLocationId($uniqueLocationB);

        $repository->save($outbound1);
        $repository->save($outbound2);

        // Test with criteria instead of testing all data
        $statsWithCriteria = $repository->getOutboundStats(['location_id' => $uniqueLocationA]);
        $this->assertEquals(1, $statsWithCriteria['total_records']);
        $this->assertEquals(10, $statsWithCriteria['total_quantity']);
        $this->assertEquals(100.0, $statsWithCriteria['total_cost']);

        // Test with different criteria
        $statsWithCriteriaB = $repository->getOutboundStats(['location_id' => $uniqueLocationB]);
        $this->assertEquals(1, $statsWithCriteriaB['total_records']);
        $this->assertEquals(20, $statsWithCriteriaB['total_quantity']);
        $this->assertEquals(200.0, $statsWithCriteriaB['total_cost']);
    }

    public function testFindRecentOutbounds(): void
    {
        $repository = $this->getRepository();

        $recentOutbounds = $repository->findRecentOutbounds(2);

        // 验证基本功能：返回指定数量的记录
        $this->assertCount(2, $recentOutbounds);
        $this->assertContainsOnlyInstancesOf(StockOutbound::class, $recentOutbounds);

        // 验证排序正确：第一个记录的创建时间 >= 第二个记录的创建时间
        $createTime0 = $recentOutbounds[0]->getCreateTime();
        $createTime1 = $recentOutbounds[1]->getCreateTime();
        $this->assertNotNull($createTime0);
        $this->assertNotNull($createTime1);
        $this->assertGreaterThanOrEqual(
            $createTime1->getTimestamp(),
            $createTime0->getTimestamp()
        );

        // 测试更多记录
        $moreRecentOutbounds = $repository->findRecentOutbounds(5);
        $this->assertLessThanOrEqual(5, count($moreRecentOutbounds));
        $this->assertContainsOnlyInstancesOf(StockOutbound::class, $moreRecentOutbounds);
    }

    public function testFindByReferenceNoPrefix(): void
    {
        $repository = $this->getRepository();

        $outbound1 = $this->createNewEntity();
        $outbound1->setReferenceNo('SALE_001');

        $outbound2 = $this->createNewEntity();
        $outbound2->setReferenceNo('SALE_002');

        $outbound3 = $this->createNewEntity();
        $outbound3->setReferenceNo('RETURN_001');

        $repository->save($outbound1);
        $repository->save($outbound2);
        $repository->save($outbound3);

        $saleOutbounds = $repository->findByReferenceNoPrefix('SALE_');

        $this->assertCount(2, $saleOutbounds);
        $this->assertContains($outbound1, $saleOutbounds);
        $this->assertContains($outbound2, $saleOutbounds);
        $this->assertNotContains($outbound3, $saleOutbounds);
    }

    public function testGetTotalOutboundQuantityBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_QUANTITY');

        $outbound1 = $this->createNewEntity();
        $outbound1->setSku($sku);
        $outbound1->setTotalQuantity(15);

        $outbound2 = $this->createNewEntity();
        $outbound2->setSku($sku);
        $outbound2->setTotalQuantity(25);

        $outbound3 = $this->createNewEntity();
        $outbound3->setSku($this->createSku('OTHER_SKU'));
        $outbound3->setTotalQuantity(10);

        $repository->save($outbound1);
        $repository->save($outbound2);
        $repository->save($outbound3);

        $totalQuantity = $repository->getTotalOutboundQuantityBySku($sku);

        $this->assertEquals(40, $totalQuantity);
    }

    public function testGetOutboundTypeStats(): void
    {
        $repository = $this->getRepository();

        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $outbound1 = $this->createNewEntity();
        $outbound1->setType(StockOutboundType::SALES);
        $outbound1->setTotalQuantity(10);
        $outbound1->setTotalCost('100.00');

        $outbound2 = $this->createNewEntity();
        $outbound2->setType(StockOutboundType::SALES);
        $outbound2->setTotalQuantity(20);
        $outbound2->setTotalCost('200.00');

        $outbound3 = $this->createNewEntity();
        $outbound3->setType(StockOutboundType::DAMAGE);
        $outbound3->setTotalQuantity(5);
        $outbound3->setTotalCost('50.00');

        $repository->save($outbound1);
        $repository->save($outbound2);
        $repository->save($outbound3);

        // Set create times within range
        $em = self::getService(EntityManagerInterface::class);
        $testDate = new \DateTimeImmutable('2023-01-15');
        $em->createQuery('UPDATE ' . StockOutbound::class . ' o SET o.createTime = :date WHERE o.id IN (:ids)')
            ->setParameter('date', $testDate)
            ->setParameter('ids', [$outbound1->getId(), $outbound2->getId(), $outbound3->getId()])
            ->execute()
        ;

        $stats = $repository->getOutboundTypeStats($startDate, $endDate);

        $this->assertArrayHasKey('sales', $stats);
        $this->assertArrayHasKey('damage', $stats);

        $this->assertEquals(2, $stats['sales']['count']);
        $this->assertEquals(30, $stats['sales']['total_quantity']);
        $this->assertEquals(300.0, $stats['sales']['total_cost']);

        $this->assertEquals(1, $stats['damage']['count']);
        $this->assertEquals(5, $stats['damage']['total_quantity']);
        $this->assertEquals(50.0, $stats['damage']['total_cost']);
    }

    public function testGetTotalOutboundCostBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_COST');

        $outbound1 = $this->createNewEntity();
        $outbound1->setSku($sku);
        $outbound1->setTotalCost('150.00');

        $outbound2 = $this->createNewEntity();
        $outbound2->setSku($sku);
        $outbound2->setTotalCost('250.00');

        $outbound3 = $this->createNewEntity();
        $outbound3->setSku($this->createSku('OTHER_SKU'));
        $outbound3->setTotalCost('100.00');

        $repository->save($outbound1);
        $repository->save($outbound2);
        $repository->save($outbound3);

        $totalCost = $repository->getTotalOutboundCostBySku($sku);

        $this->assertEquals(400.0, $totalCost);
    }
}
