<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Exception\InvalidQuantityException;
use Tourze\StockManageBundle\Exception\InvalidStatusException;
use Tourze\StockManageBundle\Service\StockService;

/**
 * @internal
 */
#[CoversClass(StockService::class)]
#[RunTestsInSeparateProcesses]
class StockServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // Custom setup logic if needed
    }

    private function getStockService(): StockService
    {
        $service = self::getContainer()->get(StockService::class);
        self::assertInstanceOf(StockService::class, $service);

        return $service;
    }

    private function createSkuWithSpu(string $gtin): Sku
    {
        $entityManager = self::getEntityManager();

        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $gtin);
        $spu->setGtin('SPU-' . $gtin);
        $entityManager->persist($spu);
        $entityManager->flush();

        $sku = new Sku();
        $sku->setGtin($gtin);
        $sku->setUnit('个');
        $sku->setSpu($spu);
        $entityManager->persist($sku);
        $entityManager->flush();

        return $sku;
    }

    public function testCreateBatch(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-001');
        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'LOC001',
        ];

        $batch = $this->getStockService()->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $batch);
        $this->assertSame('BATCH001', $batch->getBatchNo());
        $this->assertSame($sku, $batch->getSku());
        $this->assertSame(100, $batch->getQuantity());
        $this->assertSame(100, $batch->getAvailableQuantity());
        $this->assertSame(0, $batch->getReservedQuantity());
        $this->assertSame(0, $batch->getLockedQuantity());
        $this->assertSame(10.50, $batch->getUnitCost());
        $this->assertSame('A', $batch->getQualityLevel());
        $this->assertSame('available', $batch->getStatus());
        $this->assertSame('LOC001', $batch->getLocationId());
    }

    public function testCreateBatchWithDuplicateBatchNo(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-002');
        $data1 = [
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 50,
        ];

        $this->getStockService()->createBatch($data1);

        $data2 = [
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 25,
        ];

        $this->expectException(DuplicateBatchException::class);
        $this->getStockService()->createBatch($data2);
    }

    public function testCreateBatchWithoutSku(): void
    {
        // 注意：虽然类型声明期望包含sku字段，但这个测试故意传入不完整的数据来测试验证逻辑
        $data = [
            'batch_no' => 'BATCH003',
            'quantity' => 100,
        ];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('SKU不能为空');

        // 抑制类型检查，因为这是故意测试错误输入的情况
        $this->getStockService()->createBatch($data);
    }

    public function testCreateBatchWithInvalidQuantity(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-003');
        $data = [
            'batch_no' => 'BATCH004',
            'sku' => $sku,
            'quantity' => 0,
        ];

        $this->expectException(InvalidQuantityException::class);
        $this->getStockService()->createBatch($data);
    }

    public function testMergeBatches(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-004');
        $batch1 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH005',
            'sku' => $sku,
            'quantity' => 50,
            'unit_cost' => 10.00,
            'quality_level' => 'A',
            'location_id' => 'LOC001',
        ]);

        $batch2 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH006',
            'sku' => $sku,
            'quantity' => 30,
            'unit_cost' => 15.00,
            'quality_level' => 'A',
            'location_id' => 'LOC001',
        ]);

        $newBatch = $this->getStockService()->mergeBatches([$batch1, $batch2], 'MERGED001');

        $this->assertInstanceOf(StockBatch::class, $newBatch);
        $this->assertSame('MERGED001', $newBatch->getBatchNo());
        $this->assertSame($sku, $newBatch->getSku());
        $this->assertSame(80, $newBatch->getQuantity());
        $this->assertSame(80, $newBatch->getAvailableQuantity());
        $this->assertSame(11.875, $newBatch->getUnitCost()); // (50*10 + 30*15) / 80 = 11.875
        $this->assertSame('A', $newBatch->getQualityLevel());
        $this->assertSame('LOC001', $newBatch->getLocationId());
        $this->assertSame('available', $newBatch->getStatus());

        // 检查原批次状态
        $this->assertSame('depleted', $batch1->getStatus());
        $this->assertSame('depleted', $batch2->getStatus());
        $this->assertSame(0, $batch1->getAvailableQuantity());
        $this->assertSame(0, $batch2->getAvailableQuantity());
    }

    public function testMergeBatchesWithInsufficientBatches(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-005');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH007',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('至少需要2个批次才能合并');
        $this->getStockService()->mergeBatches([$batch], 'MERGED002');
    }

    public function testMergeBatchesWithIncompatibleSku(): void
    {
        $sku1 = $this->createSkuWithSpu('test-sku-006');
        $sku2 = $this->createSkuWithSpu('test-sku-007');

        $batch1 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH008',
            'sku' => $sku1,
            'quantity' => 50,
        ]);

        $batch2 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH009',
            'sku' => $sku2,
            'quantity' => 30,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：SKU不同');
        $this->getStockService()->mergeBatches([$batch1, $batch2], 'MERGED003');
    }

    public function testSplitBatch(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-008');
        $originalBatch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH010',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.00,
            'quality_level' => 'A',
            'location_id' => 'LOC001',
        ]);

        $newBatch = $this->getStockService()->splitBatch($originalBatch, 30, 'SPLIT001');

        $this->assertInstanceOf(StockBatch::class, $newBatch);
        $this->assertSame('SPLIT001', $newBatch->getBatchNo());
        $this->assertSame($sku, $newBatch->getSku());
        $this->assertSame(30, $newBatch->getQuantity());
        $this->assertSame(30, $newBatch->getAvailableQuantity());
        $this->assertSame(10.00, $newBatch->getUnitCost());
        $this->assertSame('A', $newBatch->getQualityLevel());
        $this->assertSame('LOC001', $newBatch->getLocationId());
        $this->assertSame('available', $newBatch->getStatus());

        // 检查原批次
        $this->assertSame(70, $originalBatch->getQuantity());
        $this->assertSame(70, $originalBatch->getAvailableQuantity());
        $this->assertSame('available', $originalBatch->getStatus());
    }

    public function testSplitBatchWithInvalidQuantity(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-009');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH011',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('拆分数量必须大于0且小于等于原批次数量');
        $this->getStockService()->splitBatch($batch, 60, 'SPLIT002');
    }

    public function testUpdateBatchStatus(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-010');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH012',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->getStockService()->updateBatchStatus($batch, 'quarantined');
        $this->assertSame('quarantined', $batch->getStatus());
    }

    public function testUpdateBatchStatusWithInvalidStatus(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-011');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH013',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->expectException(InvalidStatusException::class);
        $this->getStockService()->updateBatchStatus($batch, 'invalid_status');
    }

    public function testAdjustBatchQuantity(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-012');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH014',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->getStockService()->adjustBatchQuantity($batch, 25, 'Stock increase');
        $this->assertSame(75, $batch->getQuantity());
        $this->assertSame(75, $batch->getAvailableQuantity());

        $this->getStockService()->adjustBatchQuantity($batch, -10, 'Stock decrease');
        $this->assertSame(65, $batch->getQuantity());
        $this->assertSame(65, $batch->getAvailableQuantity());
    }

    public function testAdjustBatchQuantityWithNegativeResult(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-013');
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH015',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->expectException(InvalidQuantityException::class);
        $this->getStockService()->adjustBatchQuantity($batch, -60, 'Invalid adjustment');
    }

    public function testGetAvailableStock(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-014');

        $batch1 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH016',
            'sku' => $sku,
            'quantity' => 50,
            'unit_cost' => 10.00,
        ]);

        $batch2 = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH017',
            'sku' => $sku,
            'quantity' => 30,
            'unit_cost' => 15.00,
        ]);

        $summary = $this->getStockService()->getAvailableStock($sku);

        $this->assertSame($sku->getId(), $summary->getSpuId());
        $this->assertSame(80, $summary->getTotalQuantity());
        $this->assertSame(80, $summary->getAvailableQuantity());
        $this->assertSame(0, $summary->getReservedQuantity());
        $this->assertSame(0, $summary->getLockedQuantity());
        $this->assertSame(2, $summary->getTotalBatches());
        $this->assertSame(950.0, $summary->getTotalValue()); // 50*10 + 30*15
    }

    public function testCheckStockAvailability(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-015');

        $this->getStockService()->createBatch([
            'batch_no' => 'BATCH018',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        $this->getStockService()->createBatch([
            'batch_no' => 'BATCH019',
            'sku' => $sku,
            'quantity' => 30,
        ]);

        $this->assertTrue($this->getStockService()->checkStockAvailability($sku, 75));
        $this->assertTrue($this->getStockService()->checkStockAvailability($sku, 80));
        $this->assertFalse($this->getStockService()->checkStockAvailability($sku, 85));
    }

    public function testGetBatchDetails(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-016');
        $productionDate = new \DateTimeImmutable('2023-01-01');
        $expiryDate = new \DateTimeImmutable('2024-01-01');

        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH020',
            'sku' => $sku,
            'quantity' => 50,
            'unit_cost' => 10.00,
            'quality_level' => 'A',
            'location_id' => 'LOC001',
            'production_date' => $productionDate,
            'expiry_date' => $expiryDate,
        ]);

        $details = $this->getStockService()->getBatchDetails($sku);

        $this->assertCount(1, $details);
        $detail = $details[0];

        $this->assertSame($batch->getId(), $detail['id']);
        $this->assertSame('BATCH020', $detail['batchNo']);
        $this->assertSame(50, $detail['quantity']);
        $this->assertSame(50, $detail['availableQuantity']);
        $this->assertSame(0, $detail['reservedQuantity']);
        $this->assertSame(0, $detail['lockedQuantity']);
        $this->assertSame(10.00, $detail['unitCost']);
        $this->assertSame('A', $detail['qualityLevel']);
        $this->assertSame('available', $detail['status']);
        $this->assertSame('LOC001', $detail['locationId']);
        $this->assertSame('2023-01-01', $detail['productionDate']);
        $this->assertSame('2024-01-01', $detail['expiryDate']);
    }

    public function testGetStockStats(): void
    {
        $sku1 = $this->createSkuWithSpu('test-sku-017');
        $sku2 = $this->createSkuWithSpu('test-sku-018');

        $this->getStockService()->createBatch([
            'batch_no' => 'BATCH021',
            'sku' => $sku1,
            'quantity' => 50,
        ]);

        $this->getStockService()->createBatch([
            'batch_no' => 'BATCH022',
            'sku' => $sku2,
            'quantity' => 30,
        ]);

        $stats = $this->getStockService()->getStockStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('totalSkus', $stats);
        $this->assertArrayHasKey('totalQuantity', $stats);
        $this->assertArrayHasKey('totalAvailable', $stats);
        $this->assertArrayHasKey('totalReserved', $stats);
        $this->assertArrayHasKey('totalLocked', $stats);
        $this->assertArrayHasKey('expiredBatches', $stats);
        $this->assertArrayHasKey('utilizationRate', $stats);
    }

    public function testBatchProcess(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-batch-process');

        // 创建库存批次
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH-BP-001',
            'sku' => $sku,
            'quantity' => 100,
        ]);

        // 创建库存日志数组
        $log1 = new StockLog();
        $log1->setType(StockChange::LOCK);
        $log1->setQuantity(10);
        $log1->setSku($sku);

        $log2 = new StockLog();
        $log2->setType(StockChange::DEDUCT);
        $log2->setQuantity(5);
        $log2->setSku($sku);

        $logs = [$log1, $log2];

        // 执行批处理
        $this->getStockService()->batchProcess($logs);

        // 验证批处理成功（库存已更新）
        self::getEntityManager()->refresh($batch);
        $this->assertEquals(85, $batch->getAvailableQuantity()); // 100 - 10 (锁定) - 5 (扣减)
        $this->assertEquals(10, $batch->getLockedQuantity());
    }

    public function testProcessWithValidLog(): void
    {
        $sku = $this->createSkuWithSpu('test-sku-process');

        // 创建库存批次
        $batch = $this->getStockService()->createBatch([
            'batch_no' => 'BATCH-PROC-001',
            'sku' => $sku,
            'quantity' => 50,
        ]);

        // 创建库存日志
        $log = new StockLog();
        $log->setType(StockChange::LOCK);
        $log->setQuantity(10);
        $log->setSku($sku);

        // 执行处理
        $this->getStockService()->process($log);

        // 验证处理成功（库存已更新）
        self::getEntityManager()->refresh($batch);
        $this->assertEquals(40, $batch->getAvailableQuantity()); // 50 - 10 (锁定)
        $this->assertEquals(10, $batch->getLockedQuantity());
    }

    public function testProcessWithInvalidLog(): void
    {
        // 创建无效的库存日志（没有SKU信息）
        $log = new StockLog();
        $log->setType(StockChange::LOCK);
        $log->setQuantity(10);
        // 不调用setSku()，让SKU为null

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('StockLog必须包含有效的SKU信息');

        $this->getStockService()->process($log);
    }
}
