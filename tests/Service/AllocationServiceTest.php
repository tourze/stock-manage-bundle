<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\AllocationService;
use Tourze\StockManageBundle\Service\AllocationStrategy\AllocationStrategyInterface;

/**
 * @internal
 */
#[CoversClass(AllocationService::class)]
#[RunTestsInSeparateProcesses]
class AllocationServiceTest extends AbstractIntegrationTestCase
{
    private AllocationService $allocationService;

    private StockBatchRepository $repository;

    protected function onSetUp(): void
    {
        $this->allocationService = self::getService(AllocationService::class);
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

    private function createStockBatch(
        Sku $sku,
        string $batchNo,
        int $quantity,
        int $availableQuantity,
        ?\DateTimeImmutable $productionDate = null,
        ?\DateTimeImmutable $expiryDate = null,
        string $status = 'available',
    ): StockBatch {
        $entityManager = self::getEntityManager();

        $batch = new StockBatch();
        $batch->setBatchNo($batchNo);
        $batch->setSku($sku);
        $batch->setQuantity($quantity);
        $batch->setAvailableQuantity($availableQuantity);
        $batch->setReservedQuantity(0);
        $batch->setLockedQuantity(0);
        $batch->setUnitCost(10.50);
        $batch->setQualityLevel('A');
        $batch->setLocationId('WH001');
        $batch->setStatus($status);

        if (null !== $productionDate) {
            $batch->setProductionDate($productionDate);
        }

        if (null !== $expiryDate) {
            $batch->setExpiryDate($expiryDate);
        }

        $entityManager->persist($batch);
        $entityManager->flush();

        return $batch;
    }

    public function testFifoStrategy(): void
    {
        // 测试先进先出策略
        $sku = $this->createSku('SPU001-FIFO');

        $this->createStockBatch(
            $sku,
            'BATCH001-FIFO',
            100,
            100,
            new \DateTimeImmutable('2024-01-01')
        );
        $this->createStockBatch(
            $sku,
            'BATCH002-FIFO',
            200,
            200,
            new \DateTimeImmutable('2024-01-02')
        );

        $result = $this->allocationService->allocate($sku, 150, 'fifo');

        $this->assertCount(2, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(100, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH001-FIFO', $result['batches'][0]['batchNo']);
        $this->assertEquals(50, $result['batches'][1]['quantity']);
        $this->assertEquals('BATCH002-FIFO', $result['batches'][1]['batchNo']);
    }

    public function testLifoStrategy(): void
    {
        // 测试后进先出策略
        $sku = $this->createSku('SPU001-LIFO');

        $this->createStockBatch(
            $sku,
            'BATCH001-LIFO',
            100,
            100,
            new \DateTimeImmutable('2024-01-01')
        );
        $this->createStockBatch(
            $sku,
            'BATCH002-LIFO',
            200,
            200,
            new \DateTimeImmutable('2024-01-02')
        );

        $result = $this->allocationService->allocate($sku, 150, 'lifo');

        $this->assertCount(1, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(150, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH002-LIFO', $result['batches'][0]['batchNo']);
    }

    public function testFefoStrategy(): void
    {
        // 测试先过期先出策略
        $sku = $this->createSku('SPU001-FEFO');

        $this->createStockBatch(
            $sku,
            'BATCH001-FEFO',
            100,
            100,
            null,
            new \DateTimeImmutable('2024-12-01')
        );
        $this->createStockBatch(
            $sku,
            'BATCH002-FEFO',
            200,
            200,
            null,
            new \DateTimeImmutable('2024-11-01')
        );

        $result = $this->allocationService->allocate($sku, 150, 'fefo');

        $this->assertCount(1, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(150, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH002-FEFO', $result['batches'][0]['batchNo']);
    }

    public function testInsufficientStock(): void
    {
        // 测试库存不足
        $sku = $this->createSku('SPU001-INSUFFICIENT');

        $this->createStockBatch($sku, 'BATCH001-INSUFFICIENT', 100, 100);

        $this->expectException(InsufficientStockException::class);
        $expectedMessage = sprintf(
            'Insufficient stock for SKU %d: required 200, available 100',
            $sku->getId()
        );
        $this->expectExceptionMessage($expectedMessage);

        $this->allocationService->allocate($sku, 200, 'fifo');
    }

    public function testNoAvailableBatches(): void
    {
        // 测试没有可用批次
        $sku = $this->createSku('SPU001-NOBATCH');

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('没有可用的库存批次');

        $this->allocationService->allocate($sku, 100, 'fifo');
    }

    public function testInvalidStrategy(): void
    {
        // 测试无效策略
        $sku = $this->createSku('SPU001-INVALID');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的分配策略: invalid');

        $this->allocationService->allocate($sku, 100, 'invalid');
    }

    public function testAllocate(): void
    {
        $sku = $this->createSku('SPU001-ALLOCATE');
        $quantity = 100;
        $strategy = 'fifo';

        $this->createStockBatch($sku, 'BATCH001-ALLOCATE', 150, 150);

        $result = $this->allocationService->allocate($sku, $quantity, $strategy);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('totalQuantity', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('batches', $result);
        $this->assertEquals($quantity, $result['totalQuantity']);
        $this->assertEquals($strategy, $result['strategy']);
        $this->assertCount(1, $result['batches']);
        $this->assertEquals('BATCH001-ALLOCATE', $result['batches'][0]['batchNo']);
        $this->assertEquals($quantity, $result['batches'][0]['quantity']);
    }

    public function testCalculateAllocation(): void
    {
        $sku = $this->createSku('SPU001-CALC');
        $quantity = 100;
        $strategy = 'fifo';
        $criteria = ['location' => 'WH001'];

        $batch = $this->createStockBatch(
            $sku,
            'BATCH001-CALC',
            150,
            150,
            null,
            new \DateTimeImmutable('2024-12-31')
        );

        $result = $this->allocationService->calculateAllocation($sku, $quantity, $strategy, $criteria);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('requestedQuantity', $result);
        $this->assertArrayHasKey('allocatedQuantity', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('batches', $result);
        $this->assertEquals($quantity, $result['requestedQuantity']);
        $this->assertEquals($quantity, $result['allocatedQuantity']);
        $this->assertEquals($strategy, $result['strategy']);
        $this->assertCount(1, $result['batches']);
        $this->assertEquals($batch->getId(), $result['batches'][0]['batchId']);
        $this->assertEquals('BATCH001-CALC', $result['batches'][0]['batchNo']);
        $this->assertEquals($quantity, $result['batches'][0]['quantity']);
        $this->assertEquals('2024-12-31 00:00:00', $result['batches'][0]['expiryDate']);
    }

    public function testRegisterStrategy(): void
    {
        $customStrategy = new class implements AllocationStrategyInterface {
            public function getName(): string
            {
                return 'custom';
            }

            public function sortBatches(array $batches): array
            {
                return $batches;
            }
        };

        $this->allocationService->registerStrategy($customStrategy);

        $availableStrategies = $this->allocationService->getAvailableStrategies();
        $this->assertContains('custom', $availableStrategies);
    }

    public function testGetAvailableStrategies(): void
    {
        $strategies = $this->allocationService->getAvailableStrategies();

        $this->assertIsArray($strategies);
        $this->assertContains('fifo', $strategies);
        $this->assertContains('lifo', $strategies);
        $this->assertContains('fefo', $strategies);
    }

    public function testAllocateWithZeroQuantity(): void
    {
        $sku = $this->createSku('SPU001-ZERO');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('分配数量必须大于0');

        $this->allocationService->allocate($sku, 0, 'fifo');
    }

    public function testCalculateAllocationWithZeroQuantity(): void
    {
        $sku = $this->createSku('SPU001-ZERO-CALC');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('分配数量必须大于0');

        $this->allocationService->calculateAllocation($sku, 0, 'fifo');
    }

    public function testCalculateAllocationInsufficientStock(): void
    {
        $sku = $this->createSku('SPU001-CALC-INSUFFICIENT');

        $this->createStockBatch($sku, 'BATCH001-CALC-INSUFFICIENT', 50, 50);

        $this->expectException(InsufficientStockException::class);

        $this->allocationService->calculateAllocation($sku, 100, 'fifo');
    }
}
