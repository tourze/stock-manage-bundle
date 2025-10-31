<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
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
final class StockOperatorTest extends TestCase
{
    private StockOperator $stockOperator;

    private EntityManagerInterface $entityManager;

    private StockBatchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(StockBatchRepository::class);

        $this->stockOperator = new StockOperator($this->entityManager, $this->repository);
    }

    private function createMockSku(string $skuId): SKU
    {
        $sku = $this->createMock(SKU::class);
        $sku->method('getId')->willReturn($skuId);

        return $sku;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatch(array $data): StockBatch
    {
        $batch = $this->createMock(StockBatch::class);
        $batch->method('getId')->willReturn($data['id'] ?? 1);
        $batch->method('getBatchNo')->willReturn($data['batch_no'] ?? 'BATCH001');
        $batch->method('getSku')->willReturn($data['sku'] ?? $this->createMockSku('SKU001'));
        $batch->method('getQuantity')->willReturn($data['quantity'] ?? 100);
        $batch->method('getAvailableQuantity')->willReturn($data['available_quantity'] ?? $data['quantity'] ?? 100);
        $batch->method('getReservedQuantity')->willReturn($data['reserved_quantity'] ?? 0);
        $batch->method('getLockedQuantity')->willReturn($data['locked_quantity'] ?? 0);
        $batch->method('getUnitCost')->willReturn($data['unit_cost'] ?? 10.50);
        $batch->method('getQualityLevel')->willReturn($data['quality_level'] ?? 'A');
        $batch->method('getLocationId')->willReturn($data['location_id'] ?? 'WH001');
        $batch->method('getStatus')->willReturn($data['status'] ?? 'available');

        // 允许所有 setter 方法的调用（用于操作方法）

        return $batch;
    }

    private function createStockLog(SKU $sku, int $quantity, StockChange $type): StockLog
    {
        $log = $this->createMock(StockLog::class);
        $log->method('getSku')->willReturn($sku);
        $log->method('getQuantity')->willReturn($quantity);
        $log->method('getType')->willReturn($type);

        return $log;
    }

    public function testBatchProcess(): void
    {
        $sku1 = $this->createMockSku('SKU001');
        $sku2 = $this->createMockSku('SKU002');

        $log1 = $this->createStockLog($sku1, 50, StockChange::LOCK);
        $log2 = $this->createStockLog($sku2, 30, StockChange::DEDUCT);

        $logs = [$log1, $log2];

        $batch1 = $this->createStockBatch([
            'sku' => $sku1,
            'available_quantity' => 100,
            'locked_quantity' => 0,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku2,
            'quantity' => 80,
            'available_quantity' => 80,
        ]);

        $this->repository->expects($this->exactly(2))
            ->method('findAvailableBySku')
            ->willReturnCallback(function (SKU $sku) use ($batch1, $batch2) {
                return match ($sku->getId()) {
                    'SKU001' => [$batch1],
                    'SKU002' => [$batch2],
                    default => [],
                };
            })
        ;

        // Mock lock operation on batch1
        $batch1->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(50) // 100 - 50
        ;

        $batch1->expects($this->once())
            ->method('setLockedQuantity')
            ->with(50) // 0 + 50
        ;

        // Mock deduct operation on batch2
        $batch2->expects($this->once())
            ->method('setQuantity')
            ->with(50) // 80 - 30
        ;

        $batch2->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(50) // 80 - 30
        ;

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        $this->stockOperator->batchProcess($logs);
    }

    public function testProcessWithInvalidStockLog(): void
    {
        $log = $this->createMock(StockLog::class);
        $log->method('getSku')->willReturn(null);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('StockLog必须包含有效的SKU信息');

        $this->stockOperator->process($log);
    }

    public function testProcessWithUnsupportedType(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 50, StockChange::INBOUND);

        // INBOUND 操作目前在 match 表达式中没有被处理，应该触发 default 分支
        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('不支持的库存操作类型: inbound');

        $this->stockOperator->process($log);
    }

    public function testLockStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 80,
            'locked_quantity' => 10,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 120,
            'locked_quantity' => 5,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        // Mock locking 150 units: 80 from batch1 + 70 from batch2
        $batch1->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(0) // 80 - 80 = 0
        ;

        $batch1->expects($this->once())
            ->method('setLockedQuantity')
            ->with(90) // 10 + 80 = 90
        ;

        $batch2->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(50) // 120 - 70 = 50
        ;

        $batch2->expects($this->once())
            ->method('setLockedQuantity')
            ->with(75) // 5 + 70 = 75
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->lockStock($sku, 150);
    }

    public function testLockStockInsufficientStock(): void
    {
        $sku = $this->createMockSku('SKU001');
        $sku->method('getId')->willReturn('SKU001');

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
        ]);

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $this->expectException(InsufficientStockException::class);

        $this->stockOperator->lockStock($sku, 100);
    }

    public function testUnlockStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 20,
            'locked_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
            'locked_quantity' => 70,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        // Mock unlocking 100 units: 80 from batch1 + 20 from batch2
        $batch1->expects($this->once())
            ->method('setLockedQuantity')
            ->with(0) // 80 - 80 = 0
        ;

        $batch1->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(100) // 20 + 80 = 100
        ;

        $batch2->expects($this->once())
            ->method('setLockedQuantity')
            ->with(50) // 70 - 20 = 50
        ;

        $batch2->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(70) // 50 + 20 = 70
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->unlockStock($sku, 100);
    }

    public function testDeductStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 150,
            'available_quantity' => 120,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        // Mock deducting 150 units: 80 from batch1 + 70 from batch2
        $batch1->expects($this->once())
            ->method('setQuantity')
            ->with(20) // 100 - 80 = 20
        ;

        $batch1->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(0) // 80 - 80 = 0
        ;

        $batch2->expects($this->once())
            ->method('setQuantity')
            ->with(80) // 150 - 70 = 80
        ;

        $batch2->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(50) // 120 - 70 = 50
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->deductStock($sku, 150);
    }

    public function testDeductStockWithDepletedBatch(): void
    {
        $sku = $this->createMockSku('SKU001');

        // 创建一个不使用通用设置的特殊mock
        $batch = $this->createMock(StockBatch::class);
        $batch->method('getSku')->willReturn($sku);

        // 设置动态状态跟踪
        $currentQuantity = 50;
        $currentAvailableQuantity = 50;

        $batch->method('getQuantity')->willReturnCallback(function () use (&$currentQuantity) {
            return $currentQuantity;
        });
        $batch->method('getAvailableQuantity')->willReturnCallback(function () use (&$currentAvailableQuantity) {
            return $currentAvailableQuantity;
        });

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        // Mock deducting all 50 units
        $batch->expects($this->once())
            ->method('setQuantity')
            ->with(0) // 50 - 50 = 0
            ->willReturnCallback(function ($quantity) use (&$currentQuantity, $batch) {
                $currentQuantity = $quantity;

                return $batch;
            })
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(0) // 50 - 50 = 0
            ->willReturnCallback(function ($quantity) use (&$currentAvailableQuantity, $batch) {
                $currentAvailableQuantity = $quantity;

                return $batch;
            })
        ;

        // Should mark as depleted
        $batch->expects($this->once())
            ->method('setStatus')
            ->with('depleted')
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->deductStock($sku, 50);
    }

    public function testDeductStockInsufficientStock(): void
    {
        $sku = $this->createMockSku('SKU001');
        $sku->method('getId')->willReturn('SKU001');

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 30,
        ]);

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $this->expectException(InsufficientStockException::class);

        $this->stockOperator->deductStock($sku, 100);
    }

    public function testReturnStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 80,
            'available_quantity' => 50,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 120,
            'available_quantity' => 100,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        // Should return stock to the last batch (batch2)
        $batch2->expects($this->once())
            ->method('setQuantity')
            ->with(170) // 120 + 50 = 170
        ;

        $batch2->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(150) // 100 + 50 = 150
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->returnStock($sku, 50);
    }

    public function testReturnStockNoBatches(): void
    {
        $sku = $this->createMockSku('SKU001');

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn([])
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('未找到可退回库存的批次');

        $this->stockOperator->returnStock($sku, 50);
    }

    public function testPutStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 80,
            'available_quantity' => 50,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 120,
            'available_quantity' => 100,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        // Should put stock to the first batch (batch1)
        $batch1->expects($this->once())
            ->method('setQuantity')
            ->with(130) // 80 + 50 = 130
        ;

        $batch1->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(100) // 50 + 50 = 100
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->putStock($sku, 50);
    }

    public function testPutStockNoBatches(): void
    {
        $sku = $this->createMockSku('SKU001');

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn([])
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('未找到可入库的批次');

        $this->stockOperator->putStock($sku, 50);
    }

    public function testProcessLockOperation(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 50, StockChange::LOCK);

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 100,
            'locked_quantity' => 0,
        ]);

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(50) // 100 - 50
        ;

        $batch->expects($this->once())
            ->method('setLockedQuantity')
            ->with(50) // 0 + 50
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->process($log);
    }

    public function testProcessUnlockOperation(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 30, StockChange::UNLOCK);

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
            'locked_quantity' => 40,
        ]);

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $batch->expects($this->once())
            ->method('setLockedQuantity')
            ->with(10) // 40 - 30
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(80) // 50 + 30
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->process($log);
    }

    public function testProcessDeductOperation(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 40, StockChange::DEDUCT);

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $batch->expects($this->once())
            ->method('setQuantity')
            ->with(60) // 100 - 40
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(40) // 80 - 40
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->process($log);
    }

    public function testProcessReturnOperation(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 25, StockChange::RETURN);

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 75,
            'available_quantity' => 60,
        ]);

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $batch->expects($this->once())
            ->method('setQuantity')
            ->with(100) // 75 + 25
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(85) // 60 + 25
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->process($log);
    }

    public function testProcessPutOperation(): void
    {
        $sku = $this->createMockSku('SKU001');
        $log = $this->createStockLog($sku, 35, StockChange::PUT);

        $batch = $this->createStockBatch([
            'sku' => $sku,
            'quantity' => 65,
            'available_quantity' => 50,
        ]);

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn([$batch])
        ;

        $batch->expects($this->once())
            ->method('setQuantity')
            ->with(100) // 65 + 35
        ;

        $batch->expects($this->once())
            ->method('setAvailableQuantity')
            ->with(85) // 50 + 35
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->stockOperator->process($log);
    }
}
