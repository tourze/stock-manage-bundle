<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
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
class AllocationServiceTest extends TestCase
{
    private AllocationService $allocationService;

    private StockBatchRepository&MockObject $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(StockBatchRepository::class);
        $this->allocationService = new AllocationService($this->repository);
    }

    private function createSku(string $skuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($skuId);

        // 使用反射设置 ID，模拟数据库中的实体
        // 将字符串转换为对应的整数ID
        $numericId = crc32($skuId) & 0x7FFFFFFF; // 确保正整数
        $reflection = new \ReflectionClass($sku);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($sku, $numericId);

        return $sku;
    }

    public function testFifoStrategy(): void
    {
        // 测试先进先出策略
        $sku = $this->createSku('SPU001');

        $batch1 = new StockBatch();
        $batch1->setBatchNo('BATCH001');
        $batch1->setQuantity(100);
        $batch1->setAvailableQuantity(100);
        $batch1->setProductionDate(new \DateTimeImmutable('2024-01-01'));
        $batch1->setStatus('available');

        $batch2 = new StockBatch();
        $batch2->setBatchNo('BATCH002');
        $batch2->setQuantity(200);
        $batch2->setAvailableQuantity(200);
        $batch2->setProductionDate(new \DateTimeImmutable('2024-01-02'));
        $batch2->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1, $batch2])
        ;

        $result = $this->allocationService->allocate($sku, 150, 'fifo');

        $this->assertCount(2, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(100, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH001', $result['batches'][0]['batchNo']);
        $this->assertEquals(50, $result['batches'][1]['quantity']);
        $this->assertEquals('BATCH002', $result['batches'][1]['batchNo']);
    }

    public function testLifoStrategy(): void
    {
        // 测试后进先出策略
        $sku = $this->createSku('SPU001');

        $batch1 = new StockBatch();
        $batch1->setBatchNo('BATCH001');
        $batch1->setQuantity(100);
        $batch1->setAvailableQuantity(100);
        $batch1->setProductionDate(new \DateTimeImmutable('2024-01-01'));
        $batch1->setStatus('available');

        $batch2 = new StockBatch();
        $batch2->setBatchNo('BATCH002');
        $batch2->setQuantity(200);
        $batch2->setAvailableQuantity(200);
        $batch2->setProductionDate(new \DateTimeImmutable('2024-01-02'));
        $batch2->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1, $batch2])
        ;

        $result = $this->allocationService->allocate($sku, 150, 'lifo');

        $this->assertCount(1, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(150, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH002', $result['batches'][0]['batchNo']);
    }

    public function testFefoStrategy(): void
    {
        // 测试先过期先出策略
        $sku = $this->createSku('SPU001');

        $batch1 = new StockBatch();
        $batch1->setBatchNo('BATCH001');
        $batch1->setQuantity(100);
        $batch1->setAvailableQuantity(100);
        $batch1->setExpiryDate(new \DateTimeImmutable('2024-12-01'));
        $batch1->setStatus('available');

        $batch2 = new StockBatch();
        $batch2->setBatchNo('BATCH002');
        $batch2->setQuantity(200);
        $batch2->setAvailableQuantity(200);
        $batch2->setExpiryDate(new \DateTimeImmutable('2024-11-01'));
        $batch2->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1, $batch2])
        ;

        $result = $this->allocationService->allocate($sku, 150, 'fefo');

        $this->assertCount(1, $result['batches']);
        $this->assertEquals(150, $result['totalQuantity']);
        $this->assertEquals(150, $result['batches'][0]['quantity']);
        $this->assertEquals('BATCH002', $result['batches'][0]['batchNo']);
    }

    public function testInsufficientStock(): void
    {
        // 测试库存不足
        $sku = $this->createSku('SPU001');

        $batch1 = new StockBatch();
        $batch1->setQuantity(100);
        $batch1->setAvailableQuantity(100);
        $batch1->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1])
        ;

        $this->expectException(InsufficientStockException::class);
        // 期望异常消息包含实际的数字ID
        $expectedId = crc32('SPU001') & 0x7FFFFFFF;
        $this->expectExceptionMessage("Insufficient stock for SKU {$expectedId}: required 200, available 100");

        $this->allocationService->allocate($sku, 200, 'fifo');
    }

    public function testNoAvailableBatches(): void
    {
        // 测试没有可用批次
        $sku = $this->createSku('SPU001');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([])
        ;

        $this->expectException(InsufficientStockException::class);
        $this->expectExceptionMessage('没有可用的库存批次');

        $this->allocationService->allocate($sku, 100, 'fifo');
    }

    public function testInvalidStrategy(): void
    {
        // 测试无效策略
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('不支持的分配策略: invalid');

        $this->allocationService->allocate($this->createSku('SPU001'), 100, 'invalid');
    }

    public function testAllocate(): void
    {
        $sku = $this->createSku('SPU001');
        $quantity = 100;
        $strategy = 'fifo';

        $batch1 = new StockBatch();
        $batch1->setBatchNo('BATCH001');
        $batch1->setQuantity(150);
        $batch1->setAvailableQuantity(150);
        $batch1->setUnitCost(10.50);
        $batch1->setQualityLevel('A');
        $batch1->setLocationId('WH001');
        $batch1->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1])
        ;

        $result = $this->allocationService->allocate($sku, $quantity, $strategy);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sku', $result);
        $this->assertArrayHasKey('totalQuantity', $result);
        $this->assertArrayHasKey('strategy', $result);
        $this->assertArrayHasKey('batches', $result);
        $this->assertEquals($quantity, $result['totalQuantity']);
        $this->assertEquals($strategy, $result['strategy']);
        $this->assertCount(1, $result['batches']);
        $this->assertEquals('BATCH001', $result['batches'][0]['batchNo']);
        $this->assertEquals($quantity, $result['batches'][0]['quantity']);
    }

    public function testCalculateAllocation(): void
    {
        $sku = $this->createSku('SPU001');
        $quantity = 100;
        $strategy = 'fifo';
        $criteria = ['location' => 'WH001'];

        $batch1 = $this->createMock(StockBatch::class);
        $batch1->method('getId')->willReturn(1);
        $batch1->method('getBatchNo')->willReturn('BATCH001');
        $batch1->method('getQuantity')->willReturn(150);
        $batch1->method('getAvailableQuantity')->willReturn(150);
        $batch1->method('getUnitCost')->willReturn(10.50);
        $batch1->method('getQualityLevel')->willReturn('A');
        $batch1->method('getLocationId')->willReturn('WH001');
        $batch1->method('getExpiryDate')->willReturn(new \DateTimeImmutable('2024-12-31'));
        $batch1->method('getStatus')->willReturn('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1])
        ;

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
        $this->assertEquals(1, $result['batches'][0]['batchId']);
        $this->assertEquals('BATCH001', $result['batches'][0]['batchNo']);
        $this->assertEquals($quantity, $result['batches'][0]['quantity']);
        $this->assertEquals('2024-12-31 00:00:00', $result['batches'][0]['expiryDate']);
    }

    public function testRegisterStrategy(): void
    {
        $customStrategy = $this->createMock(AllocationStrategyInterface::class);
        $customStrategy->method('getName')->willReturn('custom');

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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('分配数量必须大于0');

        $this->allocationService->allocate($this->createSku('SPU001'), 0, 'fifo');
    }

    public function testCalculateAllocationWithZeroQuantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('分配数量必须大于0');

        $this->allocationService->calculateAllocation($this->createSku('SPU001'), 0, 'fifo');
    }

    public function testCalculateAllocationInsufficientStock(): void
    {
        $sku = $this->createSku('SPU001');

        $batch1 = new StockBatch();
        // 使用反射设置ID，因为它是由Doctrine管理的
        $reflection = new \ReflectionClass($batch1);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($batch1, 1);
        $batch1->setBatchNo('BATCH001');
        $batch1->setQuantity(50);
        $batch1->setAvailableQuantity(50);
        $batch1->setUnitCost(10.0);
        $batch1->setQualityLevel('A');
        $batch1->setLocationId('WH001');
        $batch1->setStatus('available');

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch1])
        ;

        $this->expectException(InsufficientStockException::class);

        $this->allocationService->calculateAllocation($sku, 100, 'fifo');
    }
}
