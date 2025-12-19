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
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\StockOperator;

/**
 * @internal
 */
#[CoversClass(StockOperator::class)]
#[RunTestsInSeparateProcesses]
final class StockOperatorTest extends AbstractIntegrationTestCase
{
    private StockOperator $stockOperator;

    private StockBatchRepository $repository;

    protected function onSetUp(): void
    {
        $this->stockOperator = self::getService(StockOperator::class);
        $this->repository = self::getService(StockBatchRepository::class);
    }

    private function createSku(string $gtin): Sku
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

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatch(Sku $sku, array $data): StockBatch
    {
        $entityManager = self::getEntityManager();

        $batch = new StockBatch();
        $batch->setBatchNo($data['batch_no'] ?? 'BATCH001');
        $batch->setSku($sku);
        $batch->setQuantity($data['quantity'] ?? 100);
        $batch->setAvailableQuantity($data['available_quantity'] ?? $data['quantity'] ?? 100);
        $batch->setReservedQuantity($data['reserved_quantity'] ?? 0);
        $batch->setLockedQuantity($data['locked_quantity'] ?? 0);
        $batch->setUnitCost($data['unit_cost'] ?? 10.50);
        $batch->setQualityLevel($data['quality_level'] ?? 'A');
        $batch->setLocationId($data['location_id'] ?? 'WH001');
        $batch->setStatus($data['status'] ?? 'available');

        $entityManager->persist($batch);
        $entityManager->flush();

        return $batch;
    }

    private function createStockLog(Sku $sku, int $quantity, StockChange $type): StockLog
    {
        $log = new StockLog();
        $log->setSku($sku);
        $log->setQuantity($quantity);
        $log->setType($type);

        return $log;
    }

    public function testBatchProcess(): void
    {
        $sku1 = $this->createSku('SKU001-BATCH');
        $sku2 = $this->createSku('SKU002-BATCH');

        $batch1 = $this->createStockBatch($sku1, [
            'batch_no' => 'BATCH001',
            'quantity' => 100,
            'available_quantity' => 100,
            'locked_quantity' => 0,
        ]);

        $batch2 = $this->createStockBatch($sku2, [
            'batch_no' => 'BATCH002',
            'quantity' => 80,
            'available_quantity' => 80,
        ]);

        $log1 = $this->createStockLog($sku1, 50, StockChange::LOCK);
        $log2 = $this->createStockLog($sku2, 30, StockChange::DEDUCT);

        $logs = [$log1, $log2];

        $this->stockOperator->batchProcess($logs);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch1 = $this->repository->find($batch1->getId());
        $this->assertNotNull($updatedBatch1);
        $this->assertEquals(50, $updatedBatch1->getAvailableQuantity()); // 100 - 50
        $this->assertEquals(50, $updatedBatch1->getLockedQuantity()); // 0 + 50

        $updatedBatch2 = $this->repository->find($batch2->getId());
        $this->assertNotNull($updatedBatch2);
        $this->assertEquals(50, $updatedBatch2->getQuantity()); // 80 - 30
        $this->assertEquals(50, $updatedBatch2->getAvailableQuantity()); // 80 - 30
    }

    public function testProcessWithInvalidStockLog(): void
    {
        $log = new StockLog();
        $log->setQuantity(50);
        $log->setType(StockChange::LOCK);
        // 不设置 SKU

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('StockLog必须包含有效的SKU信息');

        $this->stockOperator->process($log);
    }

    public function testProcessWithUnsupportedType(): void
    {
        $sku = $this->createSku('SKU001-UNSUPPORTED');
        $log = $this->createStockLog($sku, 50, StockChange::INBOUND);

        // INBOUND 操作目前在 match 表达式中没有被处理，应该触发 default 分支
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('不支持的库存操作类型: inbound');

        $this->stockOperator->process($log);
    }

    public function testLockStock(): void
    {
        $sku = $this->createSku('SKU001-LOCK');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-LOCK',
            'quantity' => 100,
            'available_quantity' => 80,
            'locked_quantity' => 10,
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH002-LOCK',
            'quantity' => 150,
            'available_quantity' => 120,
            'locked_quantity' => 5,
        ]);

        // Mock locking 150 units: 80 from batch1 + 70 from batch2
        $this->stockOperator->lockStock($sku, 150);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch1 = $this->repository->find($batch1->getId());
        $this->assertNotNull($updatedBatch1);
        $this->assertEquals(0, $updatedBatch1->getAvailableQuantity()); // 80 - 80 = 0
        $this->assertEquals(90, $updatedBatch1->getLockedQuantity()); // 10 + 80 = 90

        $updatedBatch2 = $this->repository->find($batch2->getId());
        $this->assertNotNull($updatedBatch2);
        $this->assertEquals(50, $updatedBatch2->getAvailableQuantity()); // 120 - 70 = 50
        $this->assertEquals(75, $updatedBatch2->getLockedQuantity()); // 5 + 70 = 75
    }

    public function testLockStockInsufficientStock(): void
    {
        $sku = $this->createSku('SKU001-LOCK-INSUFFICIENT');

        $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-LOCK-INSUFFICIENT',
            'quantity' => 50,
            'available_quantity' => 50,
        ]);

        $this->expectException(InsufficientStockException::class);

        $this->stockOperator->lockStock($sku, 100);
    }

    public function testUnlockStock(): void
    {
        $sku = $this->createSku('SKU001-UNLOCK');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-UNLOCK',
            'quantity' => 100,
            'available_quantity' => 20,
            'locked_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH002-UNLOCK',
            'quantity' => 120,
            'available_quantity' => 50,
            'locked_quantity' => 70,
        ]);

        // Mock unlocking 100 units: 80 from batch1 + 20 from batch2
        $this->stockOperator->unlockStock($sku, 100);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch1 = $this->repository->find($batch1->getId());
        $this->assertNotNull($updatedBatch1);
        $this->assertEquals(0, $updatedBatch1->getLockedQuantity()); // 80 - 80 = 0
        $this->assertEquals(100, $updatedBatch1->getAvailableQuantity()); // 20 + 80 = 100

        $updatedBatch2 = $this->repository->find($batch2->getId());
        $this->assertNotNull($updatedBatch2);
        $this->assertEquals(50, $updatedBatch2->getLockedQuantity()); // 70 - 20 = 50
        $this->assertEquals(70, $updatedBatch2->getAvailableQuantity()); // 50 + 20 = 70
    }

    public function testDeductStock(): void
    {
        $sku = $this->createSku('SKU001-DEDUCT');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-DEDUCT',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH002-DEDUCT',
            'quantity' => 150,
            'available_quantity' => 120,
        ]);

        // Mock deducting 150 units: 80 from batch1 + 70 from batch2
        $this->stockOperator->deductStock($sku, 150);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch1 = $this->repository->find($batch1->getId());
        $this->assertNotNull($updatedBatch1);
        $this->assertEquals(20, $updatedBatch1->getQuantity()); // 100 - 80 = 20
        $this->assertEquals(0, $updatedBatch1->getAvailableQuantity()); // 80 - 80 = 0

        $updatedBatch2 = $this->repository->find($batch2->getId());
        $this->assertNotNull($updatedBatch2);
        $this->assertEquals(80, $updatedBatch2->getQuantity()); // 150 - 70 = 80
        $this->assertEquals(50, $updatedBatch2->getAvailableQuantity()); // 120 - 70 = 50
    }

    public function testDeductStockWithDepletedBatch(): void
    {
        $sku = $this->createSku('SKU001-DEPLETED');

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-DEPLETED',
            'quantity' => 50,
            'available_quantity' => 50,
            'status' => 'available',
        ]);

        // Mock deducting all 50 units
        $this->stockOperator->deductStock($sku, 50);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(0, $updatedBatch->getQuantity()); // 50 - 50 = 0
        $this->assertEquals(0, $updatedBatch->getAvailableQuantity()); // 50 - 50 = 0
        $this->assertEquals('depleted', $updatedBatch->getStatus()); // Should mark as depleted
    }

    public function testDeductStockInsufficientStock(): void
    {
        $sku = $this->createSku('SKU001-DEDUCT-INSUFFICIENT');

        $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-DEDUCT-INSUFFICIENT',
            'quantity' => 30,
            'available_quantity' => 30,
        ]);

        $this->expectException(InsufficientStockException::class);

        $this->stockOperator->deductStock($sku, 100);
    }

    public function testReturnStock(): void
    {
        $sku = $this->createSku('SKU001-RETURN');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-RETURN',
            'quantity' => 80,
            'available_quantity' => 50,
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH002-RETURN',
            'quantity' => 120,
            'available_quantity' => 100,
        ]);

        // Should return stock to the last batch (batch2)
        $this->stockOperator->returnStock($sku, 50);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch2 = $this->repository->find($batch2->getId());
        $this->assertNotNull($updatedBatch2);
        $this->assertEquals(170, $updatedBatch2->getQuantity()); // 120 + 50 = 170
        $this->assertEquals(150, $updatedBatch2->getAvailableQuantity()); // 100 + 50 = 150
    }

    public function testReturnStockNoBatches(): void
    {
        $sku = $this->createSku('SKU001-RETURN-NOBATCH');

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('未找到可退回库存的批次');

        $this->stockOperator->returnStock($sku, 50);
    }

    public function testPutStock(): void
    {
        $sku = $this->createSku('SKU001-PUT');

        $batch1 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PUT',
            'quantity' => 80,
            'available_quantity' => 50,
        ]);

        $batch2 = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH002-PUT',
            'quantity' => 120,
            'available_quantity' => 100,
        ]);

        // Should put stock to the first batch (batch1)
        $this->stockOperator->putStock($sku, 50);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch1 = $this->repository->find($batch1->getId());
        $this->assertNotNull($updatedBatch1);
        $this->assertEquals(130, $updatedBatch1->getQuantity()); // 80 + 50 = 130
        $this->assertEquals(100, $updatedBatch1->getAvailableQuantity()); // 50 + 50 = 100
    }

    public function testPutStockNoBatches(): void
    {
        $sku = $this->createSku('SKU001-PUT-NOBATCH');

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('未找到可入库的批次');

        $this->stockOperator->putStock($sku, 50);
    }

    public function testProcessLockOperation(): void
    {
        $sku = $this->createSku('SKU001-PROCESS-LOCK');
        $log = $this->createStockLog($sku, 50, StockChange::LOCK);

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PROCESS-LOCK',
            'quantity' => 100,
            'available_quantity' => 100,
            'locked_quantity' => 0,
        ]);

        $this->stockOperator->process($log);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(50, $updatedBatch->getAvailableQuantity()); // 100 - 50
        $this->assertEquals(50, $updatedBatch->getLockedQuantity()); // 0 + 50
    }

    public function testProcessUnlockOperation(): void
    {
        $sku = $this->createSku('SKU001-PROCESS-UNLOCK');
        $log = $this->createStockLog($sku, 30, StockChange::UNLOCK);

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PROCESS-UNLOCK',
            'quantity' => 100,
            'available_quantity' => 50,
            'locked_quantity' => 40,
        ]);

        $this->stockOperator->process($log);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(10, $updatedBatch->getLockedQuantity()); // 40 - 30
        $this->assertEquals(80, $updatedBatch->getAvailableQuantity()); // 50 + 30
    }

    public function testProcessDeductOperation(): void
    {
        $sku = $this->createSku('SKU001-PROCESS-DEDUCT');
        $log = $this->createStockLog($sku, 40, StockChange::DEDUCT);

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PROCESS-DEDUCT',
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->stockOperator->process($log);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(60, $updatedBatch->getQuantity()); // 100 - 40
        $this->assertEquals(40, $updatedBatch->getAvailableQuantity()); // 80 - 40
    }

    public function testProcessReturnOperation(): void
    {
        $sku = $this->createSku('SKU001-PROCESS-RETURN');
        $log = $this->createStockLog($sku, 25, StockChange::RETURN);

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PROCESS-RETURN',
            'quantity' => 75,
            'available_quantity' => 60,
        ]);

        $this->stockOperator->process($log);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(100, $updatedBatch->getQuantity()); // 75 + 25
        $this->assertEquals(85, $updatedBatch->getAvailableQuantity()); // 60 + 25
    }

    public function testProcessPutOperation(): void
    {
        $sku = $this->createSku('SKU001-PROCESS-PUT');
        $log = $this->createStockLog($sku, 35, StockChange::PUT);

        $batch = $this->createStockBatch($sku, [
            'batch_no' => 'BATCH001-PROCESS-PUT',
            'quantity' => 65,
            'available_quantity' => 50,
        ]);

        $this->stockOperator->process($log);

        // 验证数据库状态
        $entityManager = self::getEntityManager();
        $entityManager->clear();

        $updatedBatch = $this->repository->find($batch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(100, $updatedBatch->getQuantity()); // 65 + 35
        $this->assertEquals(85, $updatedBatch->getAvailableQuantity()); // 50 + 35
    }
}
