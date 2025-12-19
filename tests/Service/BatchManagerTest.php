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
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Service\BatchManager;

/**
 * BatchManager 集成测试
 *
 * @internal
 */
#[CoversClass(BatchManager::class)]
#[RunTestsInSeparateProcesses]
class BatchManagerTest extends AbstractIntegrationTestCase
{
    private BatchManager $batchManager;

    private SkuLoaderInterface $skuLoader;

    private SpuLoaderInterface $spuLoader;

    protected function onSetUp(): void
    {
        $this->batchManager = self::getService(BatchManager::class);
        $this->skuLoader = self::getService(SkuLoaderInterface::class);
        $this->spuLoader = self::getService(SpuLoaderInterface::class);
    }

    private function createSku(string $gtin): SKU
    {
        $spu = $this->spuLoader->loadOrCreateSpu(gtin: $gtin . '-SPU', title: 'Test Product ' . $gtin);

        return $this->skuLoader->createSku($spu, gtin: $gtin);
    }

    private function createStockBatch(SKU $sku, array $data = []): StockBatch
    {
        $batch = new StockBatch();
        $batch->setSku($sku);
        $batch->setBatchNo($data['batch_no'] ?? 'BATCH-' . uniqid());
        $batch->setQuantity($data['quantity'] ?? 100);
        $batch->setAvailableQuantity($data['available_quantity'] ?? ($data['quantity'] ?? 100));
        $batch->setReservedQuantity($data['reserved_quantity'] ?? 0);
        $batch->setLockedQuantity($data['locked_quantity'] ?? 0);
        $batch->setUnitCost($data['unit_cost'] ?? 10.50);
        $batch->setQualityLevel($data['quality_level'] ?? 'A');
        $batch->setLocationId($data['location_id'] ?? 'WH001');
        $batch->setStatus($data['status'] ?? 'available');

        if (isset($data['production_date'])) {
            $batch->setProductionDate($data['production_date']);
        }
        if (isset($data['expiry_date'])) {
            $batch->setExpiryDate($data['expiry_date']);
        }

        $this->persistAndFlush($batch);

        return $batch;
    }

    public function testCreateBatch(): void
    {
        $sku = $this->createSku('SKU-BM-001');
        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ];

        $result = $this->batchManager->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('BATCH001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(100, $result->getQuantity());
        $this->assertEquals(100, $result->getAvailableQuantity());
        $this->assertEquals(0, $result->getReservedQuantity());
        $this->assertEquals(0, $result->getLockedQuantity());
        $this->assertEquals(10.50, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());
    }

    public function testCreateBatchWithDefaultValues(): void
    {
        $sku = $this->createSku('SKU-BM-002');
        $data = [
            'sku' => $sku,
            'quantity' => 100,
        ];

        $result = $this->batchManager->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertStringStartsWith('BATCH_', $result->getBatchNo());
        $this->assertEquals(0.00, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('available', $result->getStatus());
        $this->assertNull($result->getLocationId());
    }

    public function testCreateBatchWithDates(): void
    {
        $sku = $this->createSku('SKU-BM-003');
        $productionDate = new \DateTimeImmutable('2024-01-01');
        $expiryDate = new \DateTimeImmutable('2024-12-31');

        $data = [
            'sku' => $sku,
            'quantity' => 100,
            'production_date' => $productionDate,
            'expiry_date' => $expiryDate,
        ];

        $result = $this->batchManager->createBatch($data);

        $this->assertEquals($productionDate, $result->getProductionDate());
        $this->assertEquals($expiryDate, $result->getExpiryDate());
    }

    public function testMergeBatches(): void
    {
        $sku = $this->createSku('SKU-BM-004');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-MERGE-001',
            'quantity' => 100,
            'available_quantity' => 100,
            'unit_cost' => 10.00,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-MERGE-002',
            'quantity' => 150,
            'available_quantity' => 150,
            'unit_cost' => 12.00,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        $result = $this->batchManager->mergeBatches($batches, 'MERGED001');

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('MERGED001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(250, $result->getQuantity()); // 100 + 150
        $this->assertEquals(250, $result->getAvailableQuantity());
        // 平均成本: (100*10.00 + 150*12.00) / 250 = 2800 / 250 = 11.2
        $this->assertEquals(11.2, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());

        // 刷新原批次并验证其状态
        self::getEntityManager()->refresh($batch1);
        self::getEntityManager()->refresh($batch2);

        // 验证原批次被标记为已耗尽
        $this->assertEquals('depleted', $batch1->getStatus());
        $this->assertEquals(0, $batch1->getAvailableQuantity());
        $this->assertEquals('depleted', $batch2->getStatus());
        $this->assertEquals(0, $batch2->getAvailableQuantity());
    }

    public function testSplitBatch(): void
    {
        $sku = $this->createSku('SKU-BM-005');
        $originalBatch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-SPLIT-001',
            'quantity' => 200,
            'available_quantity' => 180,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'WH001',
            'production_date' => new \DateTimeImmutable('2024-01-01'),
            'expiry_date' => new \DateTimeImmutable('2024-12-31'),
        ]);

        $result = $this->batchManager->splitBatch($originalBatch, 80, 'SPLIT001');

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('SPLIT001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(80, $result->getQuantity());
        $this->assertEquals(80, $result->getAvailableQuantity());
        $this->assertEquals(10.50, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());

        // 刷新并验证原批次被正确更新
        self::getEntityManager()->refresh($originalBatch);
        $this->assertEquals(120, $originalBatch->getQuantity()); // 200 - 80
        $this->assertEquals(100, $originalBatch->getAvailableQuantity()); // 180 - 80
    }

    public function testSplitBatchWithZeroRemainingQuantity(): void
    {
        $sku = $this->createSku('SKU-BM-006');
        $originalBatch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-SPLIT-002',
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        $result = $this->batchManager->splitBatch($originalBatch, 100, 'SPLIT002');

        $this->assertEquals(100, $result->getQuantity());

        // 刷新并验证原批次被标记为已耗尽
        self::getEntityManager()->refresh($originalBatch);
        $this->assertEquals(0, $originalBatch->getQuantity());
        $this->assertEquals(0, $originalBatch->getAvailableQuantity());
        $this->assertEquals('depleted', $originalBatch->getStatus());
    }

    public function testSplitBatchWithInvalidQuantity(): void
    {
        $sku = $this->createSku('SKU-BM-007');
        $originalBatch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-SPLIT-003',
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('拆分数量必须大于0且小于等于原批次数量');

        $this->batchManager->splitBatch($originalBatch, 0, 'SPLIT003');
    }

    public function testSplitBatchWithQuantityExceedingTotal(): void
    {
        $sku = $this->createSku('SKU-BM-008');
        $originalBatch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-SPLIT-004',
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('拆分数量必须大于0且小于等于原批次数量');

        $this->batchManager->splitBatch($originalBatch, 150, 'SPLIT004');
    }

    public function testSplitBatchWithInsufficientAvailable(): void
    {
        $sku = $this->createSku('SKU-BM-009');
        $originalBatch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-SPLIT-005',
            'quantity' => 100,
            'available_quantity' => 50, // 只有50可用，但要拆分80
        ]);

        $this->expectException(InsufficientStockException::class);

        $this->batchManager->splitBatch($originalBatch, 80, 'SPLIT005');
    }

    public function testUpdateBatchStatus(): void
    {
        $sku = $this->createSku('SKU-BM-010');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-STATUS-001',
            'quantity' => 100,
            'status' => 'available',
        ]);

        $this->batchManager->updateBatchStatus($batch, 'quarantined');

        $this->assertEquals('quarantined', $batch->getStatus());
    }

    public function testAdjustBatchQuantityPositive(): void
    {
        $sku = $this->createSku('SKU-BM-011');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-ADJ-001',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->batchManager->adjustBatchQuantity($batch, 50);

        $this->assertEquals(150, $batch->getQuantity()); // 100 + 50
        $this->assertEquals(130, $batch->getAvailableQuantity()); // 80 + 50
    }

    public function testAdjustBatchQuantityNegative(): void
    {
        $sku = $this->createSku('SKU-BM-012');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-ADJ-002',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->batchManager->adjustBatchQuantity($batch, -30);

        $this->assertEquals(70, $batch->getQuantity()); // 100 - 30
        $this->assertEquals(50, $batch->getAvailableQuantity()); // 80 - 30
    }

    public function testAdjustBatchQuantityLargeNegativeAdjustment(): void
    {
        $sku = $this->createSku('SKU-BM-013');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-ADJ-003',
            'quantity' => 100,
            'available_quantity' => 50,
        ]);

        $this->batchManager->adjustBatchQuantity($batch, -80);

        $this->assertEquals(20, $batch->getQuantity()); // 100 - 80
        // 可用数量调整受限于当前可用数量
        $this->assertEquals(0, $batch->getAvailableQuantity()); // min(-80, 50) = -50, 50 + (-50) = 0
    }
}
