<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\VirtualStock;
use Tourze\StockManageBundle\Repository\VirtualStockRepository;

/**
 * @internal
 */
#[CoversClass(VirtualStockRepository::class)]
#[RunTestsInSeparateProcesses]
class VirtualStockRepositoryTest extends AbstractRepositoryTestCase
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

    protected function getRepository(): VirtualStockRepository
    {
        return self::getService(VirtualStockRepository::class);
    }

    protected function createNewEntity(): VirtualStock
    {
        $virtualStock = new VirtualStock();
        $virtualStock->setSku($this->createSku('SPU_' . uniqid()));
        $virtualStock->setVirtualType('presale');
        $virtualStock->setQuantity(100);
        $virtualStock->setLocationId('LOC_' . uniqid());
        $virtualStock->setBusinessId('BIZ_' . uniqid());
        $virtualStock->setStatus('active');
        $virtualStock->setExpectedDate(new \DateTimeImmutable('+30 days'));
        $virtualStock->setDescription('Test virtual stock');

        return $virtualStock;
    }

    public function testBasicRepositoryFunctionality(): void
    {
        $virtualStock = $this->createNewEntity();

        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($virtualStock);
        $em->flush();

        $this->assertNotNull($virtualStock->getId());

        $foundVirtualStock = $repository->find($virtualStock->getId());
        $this->assertNotNull($foundVirtualStock);
        $this->assertEquals($virtualStock->getVirtualType(), $foundVirtualStock->getVirtualType());
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();
        $virtualStock = $this->createNewEntity();

        // Test save
        $repository->save($virtualStock);
        $this->assertNotNull($virtualStock->getId());

        $id = $virtualStock->getId();
        $found = $repository->find($id);
        $this->assertNotNull($found);

        // Test remove
        $repository->remove($virtualStock);
        $found = $repository->find($id);
        $this->assertNull($found);
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_001');

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setSku($sku);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setSku($this->createSku('TEST_SKU_002'));

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);

        $virtualStocks = $repository->findBySku($sku);

        $this->assertCount(1, $virtualStocks);
        $this->assertContains($virtualStock1, $virtualStocks);
        $this->assertNotContains($virtualStock2, $virtualStocks);
    }

    public function testFindByVirtualType(): void
    {
        $repository = $this->getRepository();
        $virtualType = 'presale';

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setVirtualType($virtualType);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setVirtualType('futures');

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);

        $virtualStocks = $repository->findByVirtualType($virtualType);

        $this->assertCount(1, $virtualStocks);
        $this->assertContains($virtualStock1, $virtualStocks);
        $this->assertNotContains($virtualStock2, $virtualStocks);
    }

    public function testFindActiveStocks(): void
    {
        $repository = $this->getRepository();

        // Clear existing data
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . VirtualStock::class)->execute();

        $activeStock1 = $this->createNewEntity();
        $activeStock1->setStatus('active');
        $activeStock1->setQuantity(50);

        $activeStock2 = $this->createNewEntity();
        $activeStock2->setStatus('active');
        $activeStock2->setQuantity(30);

        $inactiveStock = $this->createNewEntity();
        $inactiveStock->setStatus('inactive');
        $inactiveStock->setQuantity(20);

        $zeroQuantityStock = $this->createNewEntity();
        $zeroQuantityStock->setStatus('active');
        $zeroQuantityStock->setQuantity(0);

        $repository->save($activeStock1);
        $repository->save($activeStock2);
        $repository->save($inactiveStock);
        $repository->save($zeroQuantityStock);

        $activeStocks = $repository->findActiveStocks();

        $this->assertCount(2, $activeStocks);
        $this->assertContains($activeStock1, $activeStocks);
        $this->assertContains($activeStock2, $activeStocks);
        $this->assertNotContains($inactiveStock, $activeStocks);
        $this->assertNotContains($zeroQuantityStock, $activeStocks);
    }

    public function testFindByStatus(): void
    {
        $repository = $this->getRepository();

        // Clear existing data to avoid interference
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . VirtualStock::class)->execute();

        $activeStock = $this->createNewEntity();
        $activeStock->setStatus('active');

        $inactiveStock = $this->createNewEntity();
        $inactiveStock->setStatus('inactive');

        $repository->save($activeStock);
        $repository->save($inactiveStock);

        $activeStocks = $repository->findByStatus('active');
        $inactiveStocks = $repository->findByStatus('inactive');

        $this->assertCount(1, $activeStocks);
        $this->assertContains($activeStock, $activeStocks);

        $this->assertCount(1, $inactiveStocks);
        $this->assertContains($inactiveStock, $inactiveStocks);
    }

    public function testFindByBusinessId(): void
    {
        $repository = $this->getRepository();
        $businessId = 'BUSINESS_001';

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setBusinessId($businessId);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setBusinessId('BUSINESS_002');

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);

        $virtualStocks = $repository->findByBusinessId($businessId);

        $this->assertCount(1, $virtualStocks);
        $this->assertContains($virtualStock1, $virtualStocks);
        $this->assertNotContains($virtualStock2, $virtualStocks);
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $locationId = 'WAREHOUSE_A';

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setLocationId($locationId);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setLocationId('WAREHOUSE_B');

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);

        $virtualStocks = $repository->findByLocation($locationId);

        $this->assertCount(1, $virtualStocks);
        $this->assertContains($virtualStock1, $virtualStocks);
        $this->assertNotContains($virtualStock2, $virtualStocks);
    }

    public function testFindExpiringSoon(): void
    {
        $repository = $this->getRepository();

        // Clear existing data to avoid interference
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . VirtualStock::class)->execute();

        $expiringSoon = $this->createNewEntity();
        $expiringSoon->setStatus('active');
        $expiringSoon->setExpectedDate(new \DateTimeImmutable('+5 days'));

        $expiringLater = $this->createNewEntity();
        $expiringLater->setStatus('active');
        $expiringLater->setExpectedDate(new \DateTimeImmutable('+15 days'));

        $noExpectedDate = $this->createNewEntity();
        $noExpectedDate->setStatus('active');
        $noExpectedDate->setExpectedDate(null);

        $repository->save($expiringSoon);
        $repository->save($expiringLater);
        $repository->save($noExpectedDate);

        $expiringSoonStocks = $repository->findExpiringSoon(7);

        $this->assertCount(1, $expiringSoonStocks);
        $this->assertContains($expiringSoon, $expiringSoonStocks);
        $this->assertNotContains($expiringLater, $expiringSoonStocks);
        $this->assertNotContains($noExpectedDate, $expiringSoonStocks);
    }

    public function testFindOverdueStocks(): void
    {
        $repository = $this->getRepository();

        $overdueStock = $this->createNewEntity();
        $overdueStock->setStatus('active');
        $overdueStock->setExpectedDate(new \DateTimeImmutable('-5 days'));

        $activeStock = $this->createNewEntity();
        $activeStock->setStatus('active');
        $activeStock->setExpectedDate(new \DateTimeImmutable('+5 days'));

        $noExpectedDate = $this->createNewEntity();
        $noExpectedDate->setStatus('active');
        $noExpectedDate->setExpectedDate(null);

        $repository->save($overdueStock);
        $repository->save($activeStock);
        $repository->save($noExpectedDate);

        $overdueStocks = $repository->findOverdueStocks();

        $this->assertCount(1, $overdueStocks);
        $this->assertContains($overdueStock, $overdueStocks);
        $this->assertNotContains($activeStock, $overdueStocks);
        $this->assertNotContains($noExpectedDate, $overdueStocks);
    }

    public function testGetTotalVirtualQuantityBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_QUANTITY');

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setSku($sku);
        $virtualStock1->setStatus('active');
        $virtualStock1->setQuantity(25);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setSku($sku);
        $virtualStock2->setStatus('active');
        $virtualStock2->setQuantity(35);

        $inactiveStock = $this->createNewEntity();
        $inactiveStock->setSku($sku);
        $inactiveStock->setStatus('inactive');
        $inactiveStock->setQuantity(20);

        $otherSkuStock = $this->createNewEntity();
        $otherSkuStock->setSku($this->createSku('OTHER_SKU'));
        $otherSkuStock->setStatus('active');
        $otherSkuStock->setQuantity(15);

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);
        $repository->save($inactiveStock);
        $repository->save($otherSkuStock);

        $totalQuantity = $repository->getTotalVirtualQuantityBySku($sku);

        $this->assertEquals(60, $totalQuantity);
    }

    public function testGetTotalQuantityByType(): void
    {
        $repository = $this->getRepository();
        $virtualType = 'presale';

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setVirtualType($virtualType);
        $virtualStock1->setStatus('active');
        $virtualStock1->setQuantity(30);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setVirtualType($virtualType);
        $virtualStock2->setStatus('active');
        $virtualStock2->setQuantity(40);

        $inactiveStock = $this->createNewEntity();
        $inactiveStock->setVirtualType($virtualType);
        $inactiveStock->setStatus('inactive');
        $inactiveStock->setQuantity(25);

        $otherTypeStock = $this->createNewEntity();
        $otherTypeStock->setVirtualType('futures');
        $otherTypeStock->setStatus('active');
        $otherTypeStock->setQuantity(20);

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);
        $repository->save($inactiveStock);
        $repository->save($otherTypeStock);

        $totalQuantity = $repository->getTotalQuantityByType($virtualType);

        $this->assertEquals(70, $totalQuantity);
    }

    public function testGetVirtualStockStatistics(): void
    {
        $repository = $this->getRepository();

        // Clear existing data
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . VirtualStock::class)->execute();

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setVirtualType('presale');
        $virtualStock1->setStatus('active');
        $virtualStock1->setQuantity(10);

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setVirtualType('presale');
        $virtualStock2->setStatus('active');
        $virtualStock2->setQuantity(20);

        $virtualStock3 = $this->createNewEntity();
        $virtualStock3->setVirtualType('futures');
        $virtualStock3->setStatus('inactive');
        $virtualStock3->setQuantity(15);

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);
        $repository->save($virtualStock3);

        $stats = $repository->getVirtualStockStatistics();

        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_count']);
        $this->assertEquals(45, $stats['total_quantity']);

        $this->assertIsArray($stats['by_type']);
        $this->assertArrayHasKey('presale', $stats['by_type']);
        $this->assertArrayHasKey('futures', $stats['by_type']);

        $this->assertIsArray($stats['by_type']['presale']);
        $this->assertEquals(2, $stats['by_type']['presale']['count']);
        $this->assertEquals(30, $stats['by_type']['presale']['quantity']);

        $this->assertIsArray($stats['by_type']['futures']);
        $this->assertEquals(1, $stats['by_type']['futures']['count']);
        $this->assertEquals(15, $stats['by_type']['futures']['quantity']);

        $this->assertIsArray($stats['by_status']);
        $this->assertArrayHasKey('active', $stats['by_status']);
        $this->assertArrayHasKey('inactive', $stats['by_status']);

        $this->assertIsArray($stats['by_status']['active']);
        $this->assertEquals(2, $stats['by_status']['active']['count']);
        $this->assertEquals(30, $stats['by_status']['active']['quantity']);

        $this->assertIsArray($stats['by_status']['inactive']);
        $this->assertEquals(1, $stats['by_status']['inactive']['count']);
        $this->assertEquals(15, $stats['by_status']['inactive']['quantity']);
    }

    public function testFindByExpectedDateRange(): void
    {
        $repository = $this->getRepository();

        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $virtualStock1 = $this->createNewEntity();
        $virtualStock1->setExpectedDate(new \DateTimeImmutable('2023-01-15'));

        $virtualStock2 = $this->createNewEntity();
        $virtualStock2->setExpectedDate(new \DateTimeImmutable('2023-02-15'));

        $virtualStock3 = $this->createNewEntity();
        $virtualStock3->setExpectedDate(new \DateTimeImmutable('2023-01-25'));

        $repository->save($virtualStock1);
        $repository->save($virtualStock2);
        $repository->save($virtualStock3);

        $virtualStocks = $repository->findByExpectedDateRange($startDate, $endDate);

        $this->assertCount(2, $virtualStocks);
        $this->assertContains($virtualStock1, $virtualStocks);
        $this->assertContains($virtualStock3, $virtualStocks);
        $this->assertNotContains($virtualStock2, $virtualStocks);
    }

    public function testFindActiveBySkuAndType(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_ACTIVE');
        $virtualType = 'presale';

        $matchingStock = $this->createNewEntity();
        $matchingStock->setSku($sku);
        $matchingStock->setVirtualType($virtualType);
        $matchingStock->setStatus('active');

        $differentSku = $this->createNewEntity();
        $differentSku->setSku($this->createSku('OTHER_SKU'));
        $differentSku->setVirtualType($virtualType);
        $differentSku->setStatus('active');

        $differentType = $this->createNewEntity();
        $differentType->setSku($sku);
        $differentType->setVirtualType('futures');
        $differentType->setStatus('active');

        $inactiveStock = $this->createNewEntity();
        $inactiveStock->setSku($sku);
        $inactiveStock->setVirtualType($virtualType);
        $inactiveStock->setStatus('inactive');

        $repository->save($matchingStock);
        $repository->save($differentSku);
        $repository->save($differentType);
        $repository->save($inactiveStock);

        $virtualStocks = $repository->findActiveBySkuAndType($sku, $virtualType);

        $this->assertCount(1, $virtualStocks);
        $this->assertContains($matchingStock, $virtualStocks);
        $this->assertNotContains($differentSku, $virtualStocks);
        $this->assertNotContains($differentType, $virtualStocks);
        $this->assertNotContains($inactiveStock, $virtualStocks);
    }
}
