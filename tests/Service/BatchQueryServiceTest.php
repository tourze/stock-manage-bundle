<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Service\BatchQueryService;

/**
 * BatchQueryService 集成测试
 *
 * @internal
 */
#[CoversClass(BatchQueryService::class)]
#[RunTestsInSeparateProcesses]
class BatchQueryServiceTest extends AbstractIntegrationTestCase
{
    private BatchQueryService $service;

    private SkuLoaderInterface $skuLoader;

    private SpuLoaderInterface $spuLoader;

    protected function onSetUp(): void
    {
        $this->service = self::getService(BatchQueryService::class);
        $this->skuLoader = self::getService(SkuLoaderInterface::class);
        $this->spuLoader = self::getService(SpuLoaderInterface::class);
    }

    public function testGetAllBatches(): void
    {
        // 创建测试数据
        $sku1 = $this->createSku('SKU-GET-ALL-001');
        $sku2 = $this->createSku('SKU-GET-ALL-002');

        $batch1 = $this->createStockBatch('B-GET-ALL-001', $sku1);
        $batch2 = $this->createStockBatch('B-GET-ALL-002', $sku2);

        // 执行查询
        $result = $this->service->getAllBatches();

        // 验证结果 - 至少包含我们创建的批次
        $this->assertGreaterThanOrEqual(2, count($result));

        // 验证我们创建的批次存在
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);
        $this->assertContains($batch1->getId(), $batchIds);
        $this->assertContains($batch2->getId(), $batchIds);
    }

    public function testFindBatchByNo(): void
    {
        // 创建测试数据
        $sku = $this->createSku('SKU-FIND-BY-NO-001');
        $batch = $this->createStockBatch('B-FIND-BY-NO-001', $sku);

        // 执行查询
        $result = $this->service->findBatchByNo('B-FIND-BY-NO-001');

        // 验证结果
        $this->assertNotNull($result);
        $this->assertEquals($batch->getId(), $result->getId());
        $this->assertEquals('B-FIND-BY-NO-001', $result->getBatchNo());
    }

    public function testFindBatchByNoReturnsNull(): void
    {
        // 执行查询（不存在的批次号）
        $result = $this->service->findBatchByNo('NOTFOUND');

        // 验证结果
        $this->assertNull($result);
    }

    public function testFindBatchesBySkuId(): void
    {
        // 创建测试数据 - 同一个 SKU 的多个批次
        $sku1 = $this->createSku('SKU-FIND-BY-SKU-001');
        $sku2 = $this->createSku('SKU-FIND-BY-SKU-002');

        // 创建第一个批次，并设置明确的创建时间
        $batch1 = $this->createStockBatch('B-FIND-SKU-001', $sku1);
        $batch1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));
        self::getEntityManager()->flush();

        // 创建第二个批次，设置更晚的创建时间
        $batch2 = $this->createStockBatch('B-FIND-SKU-002', $sku1);
        $batch2->setCreateTime(new \DateTimeImmutable('2024-01-01 11:00:00'));
        self::getEntityManager()->flush();

        // 创建第三个批次（不同 SKU）
        $batch3 = $this->createStockBatch('B-FIND-SKU-003', $sku2);

        // 清除实体管理器缓存，确保从数据库重新加载
        self::getEntityManager()->clear();

        // 执行查询
        $result = $this->service->findBatchesBySkuId($sku1->getId());

        // 验证结果
        $this->assertGreaterThanOrEqual(2, count($result));

        // 验证返回的批次属于正确的 SKU
        foreach ($result as $batch) {
            $this->assertEquals($sku1->getId(), $batch->getSku()?->getId());
        }

        // 验证返回的批次是正确的
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);
        $this->assertContains($batch1->getId(), $batchIds);
        $this->assertContains($batch2->getId(), $batchIds);
        $this->assertNotContains($batch3->getId(), $batchIds);

        // 验证按创建时间倒序排列（最新的在前面）
        // batch2 创建时间更晚，应该排在前面
        $batch1Index = array_search($batch1->getId(), $batchIds, true);
        $batch2Index = array_search($batch2->getId(), $batchIds, true);

        $this->assertNotFalse($batch1Index, 'batch1 should be in results');
        $this->assertNotFalse($batch2Index, 'batch2 should be in results');
        $this->assertLessThan($batch1Index, $batch2Index, 'Newer batch (batch2) should appear before older batch (batch1) in DESC order');
    }

    public function testFindBatchesByLocationId(): void
    {
        $sku = $this->createSku('SKU-FIND-BY-LOC-001');

        $locationId1 = 'LOC-FIND-BY-LOCATION-001';
        $locationId2 = 'LOC-FIND-BY-LOCATION-002';

        // 创建第一个批次，并设置明确的创建时间
        $batch1 = $this->createStockBatch('B-FIND-LOC-001', $sku);
        $batch1->setLocationId($locationId1);
        $batch1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));
        self::getEntityManager()->flush();

        // 创建第二个批次，设置更晚的创建时间
        $batch2 = $this->createStockBatch('B-FIND-LOC-002', $sku);
        $batch2->setLocationId($locationId1);
        $batch2->setCreateTime(new \DateTimeImmutable('2024-01-01 11:00:00'));
        self::getEntityManager()->flush();

        // 创建第三个批次（不同位置）
        $batch3 = $this->createStockBatch('B-FIND-LOC-003', $sku);
        $batch3->setLocationId($locationId2);
        self::getEntityManager()->flush();

        // 清除实体管理器缓存，确保从数据库重新加载
        self::getEntityManager()->clear();

        // 执行查询
        $result = $this->service->findBatchesByLocationId($locationId1);

        // 验证结果
        $this->assertCount(2, $result);
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);

        $this->assertContains($batch1->getId(), $batchIds);
        $this->assertContains($batch2->getId(), $batchIds);
        $this->assertNotContains($batch3->getId(), $batchIds);

        foreach ($result as $batch) {
            $this->assertEquals($locationId1, $batch->getLocationId());
        }

        // 验证按创建时间倒序排列（最新的在前面）
        // batch2 创建时间更晚，应该排在前面
        $batch1Index = array_search($batch1->getId(), $batchIds, true);
        $batch2Index = array_search($batch2->getId(), $batchIds, true);

        $this->assertNotFalse($batch1Index, 'batch1 should be in results');
        $this->assertNotFalse($batch2Index, 'batch2 should be in results');
        $this->assertLessThan($batch1Index, $batch2Index, 'Newer batch (batch2) should appear before older batch (batch1) in DESC order');
    }

    public function testGetTotalAvailableQuantity(): void
    {
        $sku = $this->createSku('SKU-TOTAL-AVAILABLE-001');

        $batch1 = $this->createStockBatch('B-TOTAL-AVAILABLE-001', $sku);
        $batch1->setAvailableQuantity(30);
        self::getEntityManager()->flush();

        $batch2 = $this->createStockBatch('B-TOTAL-AVAILABLE-002', $sku);
        $batch2->setAvailableQuantity(20);
        self::getEntityManager()->flush();

        self::getEntityManager()->clear();

        $totalAvailable = $this->service->getTotalAvailableQuantity($sku->getId());

        $this->assertEquals(50, $totalAvailable);
    }

    public function testFindBatchesExpiringSoon(): void
    {
        $sku = $this->createSku('SKU-EXPIRING-SOON-001');

        $batchSoon = $this->createStockBatch('B-EXPIRING-SOON-001', $sku);
        $batchSoon->setExpiryDate(new \DateTimeImmutable('+5 days'));
        self::getEntityManager()->flush();

        $batchLater = $this->createStockBatch('B-EXPIRING-LATER-001', $sku);
        $batchLater->setExpiryDate(new \DateTimeImmutable('+40 days'));
        self::getEntityManager()->flush();

        $batchExpired = $this->createStockBatch('B-EXPIRING-PAST-001', $sku);
        $batchExpired->setExpiryDate(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->flush();

        self::getEntityManager()->clear();

        $result = $this->service->findBatchesExpiringSoon(30);
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);

        $this->assertContains($batchSoon->getId(), $batchIds);
        $this->assertNotContains($batchLater->getId(), $batchIds);
        $this->assertNotContains($batchExpired->getId(), $batchIds);
    }

    public function testFindAvailableBySku(): void
    {
        $sku = $this->createSku('SKU-AVAILABLE-001');

        // 创建可用批次（早）
        $batchAvailable1 = $this->createStockBatch('B-AVAILABLE-001', $sku);
        $batchAvailable1->setStatus('available');
        $batchAvailable1->setAvailableQuantity(50);
        $batchAvailable1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));
        self::getEntityManager()->flush();

        // 创建可用批次（晚）
        $batchAvailable2 = $this->createStockBatch('B-AVAILABLE-002', $sku);
        $batchAvailable2->setStatus('available');
        $batchAvailable2->setAvailableQuantity(30);
        $batchAvailable2->setCreateTime(new \DateTimeImmutable('2024-01-01 11:00:00'));
        self::getEntityManager()->flush();

        // 创建不可用批次（状态不是 available）
        $batchUnavailable = $this->createStockBatch('B-UNAVAILABLE-001', $sku);
        $batchUnavailable->setStatus('reserved');
        $batchUnavailable->setAvailableQuantity(20);
        self::getEntityManager()->flush();

        // 创建可用数量为 0 的批次
        $batchZeroQuantity = $this->createStockBatch('B-ZERO-QUANTITY-001', $sku);
        $batchZeroQuantity->setStatus('available');
        $batchZeroQuantity->setAvailableQuantity(0);
        self::getEntityManager()->flush();

        self::getEntityManager()->clear();

        // 执行查询
        $result = $this->service->findAvailableBySku($sku);

        // 验证结果
        $this->assertCount(2, $result);
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);

        // 验证包含可用批次
        $this->assertContains($batchAvailable1->getId(), $batchIds);
        $this->assertContains($batchAvailable2->getId(), $batchIds);

        // 验证不包含不可用批次
        $this->assertNotContains($batchUnavailable->getId(), $batchIds);
        $this->assertNotContains($batchZeroQuantity->getId(), $batchIds);

        // 验证按创建时间升序排列（先进先出，早的在前）
        $batch1Index = array_search($batchAvailable1->getId(), $batchIds, true);
        $batch2Index = array_search($batchAvailable2->getId(), $batchIds, true);

        $this->assertNotFalse($batch1Index, 'batch1 should be in results');
        $this->assertNotFalse($batch2Index, 'batch2 should be in results');
        $this->assertLessThan($batch2Index, $batch1Index, 'Older batch (batch1) should appear before newer batch (batch2) in ASC order');
    }

    public function testFindByBatchIds(): void
    {
        $sku = $this->createSku('SKU-FIND-BY-IDS-001');

        $batch1 = $this->createStockBatch('B-FIND-BY-IDS-001', $sku);
        $batch2 = $this->createStockBatch('B-FIND-BY-IDS-002', $sku);
        $batch3 = $this->createStockBatch('B-FIND-BY-IDS-003', $sku);

        self::getEntityManager()->clear();

        // 测试查询部分批次
        $result = $this->service->findByBatchIds([
            $batch1->getId(),
            $batch2->getId(),
        ]);

        $this->assertCount(2, $result);
        $batchIds = array_map(fn (StockBatch $b) => $b->getId(), $result);

        $this->assertContains($batch1->getId(), $batchIds);
        $this->assertContains($batch2->getId(), $batchIds);
        $this->assertNotContains($batch3->getId(), $batchIds);
    }

    public function testFindByBatchIdsWithEmptyArray(): void
    {
        // 测试空数组
        $result = $this->service->findByBatchIds([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    private function createSku(string $gtin): SKU
    {
        $spu = $this->spuLoader->loadOrCreateSpu(gtin: $gtin . '-SPU', title: 'Test Product ' . $gtin);

        return $this->skuLoader->createSku($spu, gtin: $gtin);
    }

    private function createStockBatch(string $batchNo, SKU $sku): StockBatch
    {
        $batch = new StockBatch();
        $batch->setBatchNo($batchNo);
        $batch->setSku($sku);
        $batch->setQuantity(100);
        $batch->setAvailableQuantity(100);
        $batch->setUnitCost(10.50);
        $batch->setQualityLevel('A');
        $batch->setStatus('available');
        $batch->setLocationId('WH001');

        $this->persistAndFlush($batch);

        return $batch;
    }
}
