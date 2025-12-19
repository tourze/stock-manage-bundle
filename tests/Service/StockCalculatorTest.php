<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SKU as SKUInterface;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Model\StockSummary;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\StockCalculator;

/**
 * @internal
 */
#[CoversClass(StockCalculator::class)]
#[RunTestsInSeparateProcesses]
final class StockCalculatorTest extends AbstractIntegrationTestCase
{
    private StockCalculator $stockCalculator;

    private StockBatchRepository $repository;

    private SkuLoaderInterface $skuLoader;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(StockBatchRepository::class);
        $this->skuLoader = self::getService(SkuLoaderInterface::class);
        $this->stockCalculator = self::getService(StockCalculator::class);
    }

    /**
     * 创建 SKU 测试实体
     */
    private function createSku(string $skuId): SKUInterface
    {
        // 创建 SPU
        $spu = new Spu();
        self::getEntityManager()->persist($spu);
        self::getEntityManager()->flush();

        // 创建 SKU
        $sku = new Sku();
        $sku->setSpu($spu);

        // 使用反射设置必需字段
        $reflection = new \ReflectionClass($sku);

        // 设置 GTIN 作为标识符
        $gtinProperty = $reflection->getProperty('gtin');
        $gtinProperty->setValue($sku, $skuId);

        // 设置 unit 字段（必填）
        $unitProperty = $reflection->getProperty('unit');
        $unitProperty->setValue($sku, '个');

        // 持久化 SKU
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        return $sku;
    }

    /**
     * 创建 StockBatch 测试实体
     *
     * @param array<string, mixed> $data
     */
    private function createStockBatch(array $data): StockBatch
    {
        $batch = new StockBatch();

        // 设置必填属性
        $batch->setSku($data['sku']);
        $batch->setBatchNo((string) ($data['batch_no'] ?? 'BATCH' . uniqid()));
        $batch->setQuantity((int) ($data['quantity'] ?? 100));
        $batch->setUnitCost((float) ($data['unit_cost'] ?? 10.50));
        $batch->setQualityLevel((string) ($data['quality_level'] ?? 'A'));
        $batch->setLocationId((string) ($data['location_id'] ?? 'WH001'));

        // 设置可用数量（默认等于总数量）
        $batch->setAvailableQuantity((int) ($data['available_quantity'] ?? $data['quantity'] ?? 100));

        // 设置其他可选属性
        if (isset($data['reserved_quantity'])) {
            $batch->setReservedQuantity((int) $data['reserved_quantity']);
        }
        if (isset($data['locked_quantity'])) {
            $batch->setLockedQuantity((int) $data['locked_quantity']);
        }
        if (isset($data['status'])) {
            $batch->setStatus((string) $data['status']);
        } else {
            $batch->setStatus('available');
        }
        if (isset($data['production_date'])) {
            $batch->setProductionDate($data['production_date']);
        }
        if (isset($data['expiry_date'])) {
            $batch->setExpiryDate($data['expiry_date']);
        }

        // 持久化
        self::getEntityManager()->persist($batch);
        self::getEntityManager()->flush();

        return $batch;
    }

    public function testGetAvailableStock(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
            'reserved_quantity' => 15,
            'locked_quantity' => 5,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'WH001',
            'status' => 'available',
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 150,
            'available_quantity' => 120,
            'reserved_quantity' => 20,
            'locked_quantity' => 10,
            'unit_cost' => 12.00,
            'quality_level' => 'A',
            'location_id' => 'WH001',
            'status' => 'available',
        ]);

        $result = $this->stockCalculator->getAvailableStock($sku);

        $this->assertInstanceOf(StockSummary::class, $result);
        $this->assertEquals($sku->getId(), $result->getSpuId());
        $this->assertEquals(250, $result->getTotalQuantity()); // 100 + 150
        $this->assertEquals(200, $result->getAvailableQuantity()); // 80 + 120
        $this->assertEquals(35, $result->getReservedQuantity()); // 15 + 20
        $this->assertEquals(15, $result->getLockedQuantity()); // 5 + 10
        $this->assertEquals(2, $result->getTotalBatches());
        $this->assertEquals(2850.0, $result->getTotalValue()); // 100*10.50 + 150*12.00

        $batches = $result->getBatches();
        $this->assertCount(2, $batches);

        $this->assertIsArray($batches[0]);
        $this->assertIsArray($batches[1]);
        /** @var array<string, mixed> $firstBatch */
        $firstBatch = $batches[0];
        /** @var array<string, mixed> $secondBatch */
        $secondBatch = $batches[1];

        $this->assertEquals('BATCH001', $firstBatch['batchNo']);
        $this->assertEquals('BATCH002', $secondBatch['batchNo']);
    }

    public function testCheckStockAvailability(): void
    {
        $sku = $this->createSku('SKU002');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 120,
        ]);

        $result = $this->stockCalculator->checkStockAvailability($sku, 150);

        $this->assertTrue($result);
    }

    public function testCheckStockAvailabilityInsufficientStock(): void
    {
        $sku = $this->createSku('SKU003');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
        ]);

        $result = $this->stockCalculator->checkStockAvailability($sku, 100);

        $this->assertFalse($result);
    }

    public function testCheckStockAvailabilityEarlyReturn(): void
    {
        $sku = $this->createSku('SKU004');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 150,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
        ]);

        $result = $this->stockCalculator->checkStockAvailability($sku, 100);

        // 应该在第一个批次就满足需求并返回true
        $this->assertTrue($result);
    }

    public function testGetBatchDetails(): void
    {
        $sku = $this->createSku('SKU005');

        $productionDate = new \DateTimeImmutable('2024-01-01');
        $expiryDate = new \DateTimeImmutable('2024-12-31');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
            'reserved_quantity' => 15,
            'locked_quantity' => 5,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'status' => 'available',
            'location_id' => 'WH001',
            'production_date' => $productionDate,
            'expiry_date' => $expiryDate,
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 150,
            'available_quantity' => 120,
            'reserved_quantity' => 20,
            'locked_quantity' => 10,
            'unit_cost' => 12.00,
            'quality_level' => 'B',
            'status' => 'available',
            'location_id' => 'WH002',
        ]);

        $result = $this->stockCalculator->getBatchDetails($sku);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $detail1 = $result[0];
        $this->assertEquals($batch1->getId(), $detail1['id']);
        $this->assertEquals('BATCH001', $detail1['batchNo']);
        $this->assertEquals(100, $detail1['quantity']);
        $this->assertEquals(80, $detail1['availableQuantity']);
        $this->assertEquals(15, $detail1['reservedQuantity']);
        $this->assertEquals(5, $detail1['lockedQuantity']);
        $this->assertEquals(10.50, $detail1['unitCost']);
        $this->assertEquals('A', $detail1['qualityLevel']);
        $this->assertEquals('available', $detail1['status']);
        $this->assertEquals('WH001', $detail1['locationId']);
        $this->assertEquals('2024-01-01', $detail1['productionDate']);
        $this->assertEquals('2024-12-31', $detail1['expiryDate']);

        $detail2 = $result[1];
        $this->assertEquals($batch2->getId(), $detail2['id']);
        $this->assertEquals('BATCH002', $detail2['batchNo']);
        $this->assertEquals('B', $detail2['qualityLevel']);
        $this->assertEquals('available', $detail2['status']);
        $this->assertEquals('WH002', $detail2['locationId']);
        $this->assertNull($detail2['productionDate']);
        $this->assertNull($detail2['expiryDate']);
        // 真实实体构造函数会自动设置 createTime，所以这里不再验证为 null
        $this->assertNotNull($detail2['createTime']);
    }

    public function testGetValidStock(): void
    {
        $sku = $this->createSku('SKU006');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 10.50,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 150,
            'available_quantity' => 120,
            'unit_cost' => 12.00,
        ]);

        $result = $this->stockCalculator->getValidStock($sku);

        $this->assertEquals(200, $result); // 80 + 120
    }
}
