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
use Tourze\StockManageBundle\Service\BatchService;

/**
 * BatchService 集成测试
 *
 * @internal
 */
#[CoversClass(BatchService::class)]
#[RunTestsInSeparateProcesses]
final class BatchServiceTest extends AbstractIntegrationTestCase
{
    private BatchService $service;

    private SkuLoaderInterface $skuLoader;

    private SpuLoaderInterface $spuLoader;

    protected function onSetUp(): void
    {
        $this->service = self::getService(BatchService::class);
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

    public function testFindByBatchNo(): void
    {
        $sku = $this->createSku('SKU-BS-001');
        $batch = $this->createStockBatch($sku, ['batch_no' => 'BATCH-FIND-001']);

        $result = $this->service->findByBatchNo('BATCH-FIND-001');

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('BATCH-FIND-001', $result->getBatchNo());
        $this->assertEquals($batch->getId(), $result->getId());
    }

    public function testFindByBatchNoNotFound(): void
    {
        $result = $this->service->findByBatchNo('NON-EXISTENT-BATCH');

        $this->assertNull($result);
    }

    public function testFindById(): void
    {
        $sku = $this->createSku('SKU-BS-002');
        $batch = $this->createStockBatch($sku, ['batch_no' => 'BATCH-FIND-002']);

        $result = $this->service->findById($batch->getId());

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals($batch->getId(), $result->getId());
        $this->assertEquals('BATCH-FIND-002', $result->getBatchNo());
    }

    public function testFindByIdNotFound(): void
    {
        $result = $this->service->findById(999999);

        $this->assertNull($result);
    }

    public function testIsBatchNoExists(): void
    {
        $sku = $this->createSku('SKU-BS-003');
        $this->createStockBatch($sku, ['batch_no' => 'BATCH-EXISTS-001']);

        $exists = $this->service->isBatchNoExists('BATCH-EXISTS-001');

        $this->assertTrue($exists);
    }

    public function testIsBatchNoExistsNotFound(): void
    {
        $exists = $this->service->isBatchNoExists('NON-EXISTENT-BATCH');

        $this->assertFalse($exists);
    }

    public function testCreateBatch(): void
    {
        $sku = $this->createSku('SKU-BS-004');
        $productionDate = new \DateTimeImmutable('2024-01-01');
        $expiryDate = new \DateTimeImmutable('2024-12-31');

        $data = [
            'sku' => $sku,
            'batch_no' => 'BATCH-CREATE-001',
            'quantity' => 150,
            'available_quantity' => 150,
            'unit_cost' => 12.50,
            'quality_level' => 'B',
            'status' => 'available',
            'location_id' => 'WH002',
            'production_date' => $productionDate,
            'expiry_date' => $expiryDate,
        ];

        $result = $this->service->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals('BATCH-CREATE-001', $result->getBatchNo());
        $this->assertEquals(150, $result->getQuantity());
        $this->assertEquals(150, $result->getAvailableQuantity());
        $this->assertEquals(12.50, $result->getUnitCost());
        $this->assertEquals('B', $result->getQualityLevel());
        $this->assertEquals('available', $result->getStatus());
        $this->assertEquals('WH002', $result->getLocationId());
        $this->assertEquals($productionDate, $result->getProductionDate());
        $this->assertEquals($expiryDate, $result->getExpiryDate());
    }

    public function testCreateBatchWithPartialData(): void
    {
        $sku = $this->createSku('SKU-BS-005');

        $data = [
            'sku' => $sku,
            'batch_no' => 'BATCH-CREATE-002',
            'quantity' => 100,
        ];

        $result = $this->service->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals('BATCH-CREATE-002', $result->getBatchNo());
        $this->assertEquals(100, $result->getQuantity());
        // 当 available_quantity 未提供时,应该使用默认值 0（由实体初始化）
        $this->assertEquals(0, $result->getAvailableQuantity());
    }

    public function testCreateBatchWithNullSku(): void
    {
        $data = [
            'sku' => null,
            'batch_no' => 'BATCH-CREATE-003',
            'quantity' => 50,
        ];

        $result = $this->service->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertNull($result->getSku());
        $this->assertEquals('BATCH-CREATE-003', $result->getBatchNo());
    }

    public function testUpdateQuantity(): void
    {
        $sku = $this->createSku('SKU-BS-006');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-UPDATE-001',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->service->updateQuantity($batch, 200, 180);

        $this->assertEquals(200, $batch->getQuantity());
        $this->assertEquals(180, $batch->getAvailableQuantity());
    }

    public function testUpdateQuantityWithoutAvailableQuantity(): void
    {
        $sku = $this->createSku('SKU-BS-007');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-UPDATE-002',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->service->updateQuantity($batch, 150);

        $this->assertEquals(150, $batch->getQuantity());
        // 当未指定 availableQuantity 时,应该等于总数量
        $this->assertEquals(150, $batch->getAvailableQuantity());
    }

    public function testAddQuantity(): void
    {
        $sku = $this->createSku('SKU-BS-008');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-ADD-001',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->service->addQuantity($batch, 50);

        $this->assertEquals(150, $batch->getQuantity()); // 100 + 50
        $this->assertEquals(130, $batch->getAvailableQuantity()); // 80 + 50
    }

    public function testAddQuantityZero(): void
    {
        $sku = $this->createSku('SKU-BS-009');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-ADD-002',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->service->addQuantity($batch, 0);

        $this->assertEquals(100, $batch->getQuantity());
        $this->assertEquals(80, $batch->getAvailableQuantity());
    }

    public function testUpdateUnitCost(): void
    {
        $sku = $this->createSku('SKU-BS-010');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-COST-001',
            'quantity' => 100, // 在调用前批次总数量为100
            'unit_cost' => 10.00,
        ]);

        // 增加了50件,新增部分的单价为12.00
        // 加权平均: (50 * 10.00 + 50 * 12.00) / 100 = 11.00
        $this->service->updateUnitCost($batch, 12.00, 50);

        $this->assertEquals(11.00, $batch->getUnitCost());
    }

    public function testUpdateUnitCostWithDifferentRatio(): void
    {
        $sku = $this->createSku('SKU-BS-011');
        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH-COST-002',
            'quantity' => 150, // 在调用前批次总数量为150
            'unit_cost' => 10.00,
        ]);

        // 增加了50件,原有100件
        // 加权平均: (100 * 10.00 + 50 * 15.00) / 150 = 11.67
        $this->service->updateUnitCost($batch, 15.00, 50);

        $this->assertEqualsWithDelta(11.67, $batch->getUnitCost(), 0.01);
    }
}
