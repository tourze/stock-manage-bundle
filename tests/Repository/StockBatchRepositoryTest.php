<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * @internal
 */
#[CoversClass(StockBatchRepository::class)]
#[RunTestsInSeparateProcesses]
class StockBatchRepositoryTest extends AbstractRepositoryTestCase
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

    protected function getRepository(): StockBatchRepository
    {
        return self::getService(StockBatchRepository::class);
    }

    protected function createNewEntity(): StockBatch
    {
        $stockBatch = new StockBatch();
        $stockBatch->setBatchNo('BATCH_' . uniqid());
        $stockBatch->setSku($this->createSku('SPU_' . uniqid()));
        $stockBatch->setLocationId('LOC_' . uniqid());
        $stockBatch->setQuantity(100);
        $stockBatch->setAvailableQuantity(100);
        $stockBatch->setReservedQuantity(0);
        $stockBatch->setLockedQuantity(0);
        $stockBatch->setUnitCost(10.5);
        $stockBatch->setStatus('available');
        $stockBatch->setQualityLevel('A');
        $stockBatch->setUpdateTime(new \DateTimeImmutable());

        return $stockBatch;
    }

    public function testBasicRepositoryFunctionality(): void
    {
        $batch = $this->createNewEntity();

        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($batch);
        $em->flush();

        $this->assertNotNull($batch->getId());

        $foundBatch = $repository->find($batch->getId());
        $this->assertNotNull($foundBatch);
        $this->assertEquals($batch->getBatchNo(), $foundBatch->getBatchNo());
    }

    public function testExistsByBatchNo(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $batch = $this->createNewEntity();
        $batchNo = 'UNIQUE_BATCH_001';
        $batch->setBatchNo($batchNo);

        $em->persist($batch);
        $em->flush();

        $this->assertTrue($repository->existsByBatchNo($batchNo));
        $this->assertFalse($repository->existsByBatchNo('NONEXISTENT_BATCH'));
    }

    public function testFindAvailable(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        // 创建可用批次
        $availableBatch = $this->createNewEntity();
        $availableBatch->setStatus('available');
        $availableBatch->setAvailableQuantity(50);
        $availableBatch->setLocationId('LOC001');
        $availableBatch->setQualityLevel('A');

        // 创建不可用批次（数量为0）
        $unavailableBatch = $this->createNewEntity();
        $unavailableBatch->setStatus('available');
        $unavailableBatch->setAvailableQuantity(0);

        $em->persist($availableBatch);
        $em->persist($unavailableBatch);
        $em->flush();

        // 测试无条件查找
        $available = $repository->findAvailable();
        $this->assertNotEmpty($available);
        $this->assertContains($availableBatch, $available);
        $this->assertNotContains($unavailableBatch, $available);

        // 测试带条件查找
        $criteriaAvailable = $repository->findAvailable([
            'location_id' => 'LOC001',
            'quality_level' => 'A',
        ]);
        $this->assertNotEmpty($criteriaAvailable);
        $this->assertContains($availableBatch, $criteriaAvailable);
    }

    public function testFindAvailableBySku(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $sku = $this->createSku('TEST_SKU_001');

        $batch1 = $this->createNewEntity();
        $batch1->setSku($sku);
        $batch1->setStatus('available');
        $batch1->setAvailableQuantity(30);

        $batch2 = $this->createNewEntity();
        $batch2->setSku($sku);
        $batch2->setStatus('locked');
        $batch2->setAvailableQuantity(20);

        $em->persist($batch1);
        $em->persist($batch2);
        $em->flush();

        $availableBatches = $repository->findAvailableBySku($sku);

        $this->assertCount(1, $availableBatches);
        $this->assertContains($batch1, $availableBatches);
        $this->assertNotContains($batch2, $availableBatches);
    }

    public function testFindBatchesExpiringSoon(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        // 创建即将过期的批次（15天后过期）
        $expiringSoon = $this->createNewEntity();
        $expiringSoon->setExpiryDate(new \DateTimeImmutable('+15 days'));
        $expiringSoon->setAvailableQuantity(25);

        // 创建远期过期的批次（60天后过期）
        $expiringLater = $this->createNewEntity();
        $expiringLater->setExpiryDate(new \DateTimeImmutable('+60 days'));
        $expiringLater->setAvailableQuantity(35);

        // 创建无过期日期的批次
        $noExpiry = $this->createNewEntity();
        $noExpiry->setExpiryDate(null);
        $noExpiry->setAvailableQuantity(40);

        $em->persist($expiringSoon);
        $em->persist($expiringLater);
        $em->persist($noExpiry);
        $em->flush();

        $expiringSoonBatches = $repository->findBatchesExpiringSoon(30);

        $this->assertCount(1, $expiringSoonBatches);
        $this->assertContains($expiringSoon, $expiringSoonBatches);
        $this->assertNotContains($expiringLater, $expiringSoonBatches);
        $this->assertNotContains($noExpiry, $expiringSoonBatches);
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $locationId = 'WAREHOUSE_A_001';

        $batch1 = $this->createNewEntity();
        $batch1->setLocationId($locationId);

        $batch2 = $this->createNewEntity();
        $batch2->setLocationId('WAREHOUSE_B_001');

        $em->persist($batch1);
        $em->persist($batch2);
        $em->flush();

        $batches = $repository->findByLocation($locationId);

        $this->assertCount(1, $batches);
        $this->assertContains($batch1, $batches);
        $this->assertNotContains($batch2, $batches);
    }

    public function testFindByQualityLevel(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        // Clear any existing data for this test
        $em->createQuery('DELETE FROM ' . StockBatch::class)->execute();

        $batch1 = $this->createNewEntity();
        $batch1->setQualityLevel('A');

        $batch2 = $this->createNewEntity();
        $batch2->setQualityLevel('B');

        $em->persist($batch1);
        $em->persist($batch2);
        $em->flush();

        $batchesA = $repository->findByQualityLevel('A');
        $batchesB = $repository->findByQualityLevel('B');

        $this->assertCount(1, $batchesA);
        $this->assertContains($batch1, $batchesA);
        $this->assertNotContains($batch2, $batchesA);

        $this->assertCount(1, $batchesB);
        $this->assertContains($batch2, $batchesB);
        $this->assertNotContains($batch1, $batchesB);
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $sku1 = $this->createSku('SKU_TEST_001');
        $sku2 = $this->createSku('SKU_TEST_002');

        $batch1 = $this->createNewEntity();
        $batch1->setSku($sku1);

        $batch2 = $this->createNewEntity();
        $batch2->setSku($sku2);

        $em->persist($batch1);
        $em->persist($batch2);
        $em->flush();

        $batches = $repository->findBySku($sku1);

        $this->assertCount(1, $batches);
        $this->assertContains($batch1, $batches);
        $this->assertNotContains($batch2, $batches);
    }

    public function testFindBySpuId(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $sku = $this->createSku('SPU_TEST_001');
        $skuId = $sku->getId();

        $batch1 = $this->createNewEntity();
        $batch1->setSku($sku);
        $batch1->setLocationId('LOC001');

        $batch2 = $this->createNewEntity();
        $batch2->setSku($this->createSku('SPU_TEST_002'));

        $em->persist($batch1);
        $em->persist($batch2);
        $em->flush();

        // 测试无条件查找（使用SKU ID而不是GTIN）
        $batches = $repository->findBySpuId($skuId);
        $this->assertCount(1, $batches);
        $this->assertContains($batch1, $batches);

        // 测试带位置条件查找
        $batchesWithLocation = $repository->findBySpuId($skuId, ['locationId' => 'LOC001']);
        $this->assertCount(1, $batchesWithLocation);
        $this->assertContains($batch1, $batchesWithLocation);

        // 测试不匹配的位置条件
        $batchesWithWrongLocation = $repository->findBySpuId($skuId, ['locationId' => 'WRONG_LOC']);
        $this->assertCount(0, $batchesWithWrongLocation);
    }

    public function testFindExpiredBatches(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        // Clear any existing data for this test
        $em->createQuery('DELETE FROM ' . StockBatch::class)->execute();

        // 创建已过期的批次
        $expiredBatch = $this->createNewEntity();
        $expiredBatch->setExpiryDate(new \DateTimeImmutable('-5 days'));

        // 创建未过期的批次
        $validBatch = $this->createNewEntity();
        $validBatch->setExpiryDate(new \DateTimeImmutable('+10 days'));

        // 创建无过期日期的批次
        $noExpiryBatch = $this->createNewEntity();
        $noExpiryBatch->setExpiryDate(null);

        $em->persist($expiredBatch);
        $em->persist($validBatch);
        $em->persist($noExpiryBatch);
        $em->flush();

        $expiredBatches = $repository->findExpiredBatches();

        $this->assertCount(1, $expiredBatches);
        $this->assertContains($expiredBatch, $expiredBatches);
        $this->assertNotContains($validBatch, $expiredBatches);
        $this->assertNotContains($noExpiryBatch, $expiredBatches);
    }
}
