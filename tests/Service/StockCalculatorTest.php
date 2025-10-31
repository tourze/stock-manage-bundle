<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Model\StockSummary;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\StockCalculator;

/**
 * @internal
 */
#[CoversClass(StockCalculator::class)]
final class StockCalculatorTest extends TestCase
{
    private StockCalculator $stockCalculator;

    private StockBatchRepository $repository;

    private SkuLoaderInterface $skuLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(StockBatchRepository::class);
        $this->skuLoader = $this->createMock(SkuLoaderInterface::class);

        $this->stockCalculator = new StockCalculator($this->repository, $this->skuLoader);
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
        $batch->method('getProductionDate')->willReturn($data['production_date'] ?? null);
        $batch->method('getExpiryDate')->willReturn($data['expiry_date'] ?? null);
        $batch->method('getCreateTime')->willReturn($data['create_time'] ?? null);

        return $batch;
    }

    public function testGetAvailableStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'id' => 1,
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
            'id' => 2,
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

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->getAvailableStock($sku);

        $this->assertInstanceOf(StockSummary::class, $result);
        $this->assertEquals('SKU001', $result->getSpuId());
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

    public function testGetStockSummary(): void
    {
        $spuIds = ['SPU001', 'SPU002'];

        $batchSummaries = [
            'SPU001' => [
                'total_quantity' => 200,
                'total_available' => 180,
                'total_reserved' => 15,
                'total_locked' => 5,
                'total_batches' => 2,
            ],
            'SPU002' => [
                'total_quantity' => 100,
                'total_available' => 90,
                'total_reserved' => 8,
                'total_locked' => 2,
                'total_batches' => 1,
            ],
        ];

        $this->repository->expects($this->once())
            ->method('getBatchSummary')
            ->with($spuIds)
            ->willReturn($batchSummaries)
        ;

        $sku1 = $this->createMockSku('SPU001');
        $sku2 = $this->createMockSku('SPU002');

        $this->skuLoader->expects($this->exactly(2))
            ->method('loadSkuByIdentifier')
            ->willReturnCallback(function (string $spuId) use ($sku1, $sku2) {
                return match ($spuId) {
                    'SPU001' => $sku1,
                    'SPU002' => $sku2,
                    default => null,
                };
            })
        ;

        // Mock batches for SKU retrieval
        $batch1 = $this->createStockBatch([
            'sku' => $sku1,
            'quantity' => 100,
            'unit_cost' => 10.50,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku1,
            'quantity' => 100,
            'unit_cost' => 12.00,
        ]);

        $batch3 = $this->createStockBatch([
            'sku' => $sku2,
            'quantity' => 100,
            'unit_cost' => 15.00,
        ]);

        $this->repository->expects($this->exactly(2))
            ->method('findBySku')
            ->willReturnCallback(function ($sku) use ($batch1, $batch2, $batch3) {
                $skuId = $sku->getId();

                return match ($skuId) {
                    'SPU001' => [$batch1, $batch2],
                    'SPU002' => [$batch3],
                    default => [],
                };
            })
        ;

        $result = $this->stockCalculator->getStockSummary($spuIds);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('SPU001', $result);
        $this->assertArrayHasKey('SPU002', $result);

        $summary1 = $result['SPU001'];
        $this->assertInstanceOf(StockSummary::class, $summary1);
        $this->assertEquals('SPU001', $summary1->getSpuId());
        $this->assertEquals(200, $summary1->getTotalQuantity());
        $this->assertEquals(180, $summary1->getAvailableQuantity());
        $this->assertEquals(15, $summary1->getReservedQuantity());
        $this->assertEquals(5, $summary1->getLockedQuantity());
        $this->assertEquals(2, $summary1->getTotalBatches());
        $this->assertEquals(2250.0, $summary1->getTotalValue()); // 100*10.50 + 100*12.00

        $summary2 = $result['SPU002'];
        $this->assertInstanceOf(StockSummary::class, $summary2);
        $this->assertEquals('SPU002', $summary2->getSpuId());
        $this->assertEquals(100, $summary2->getTotalQuantity());
        $this->assertEquals(90, $summary2->getAvailableQuantity());
        $this->assertEquals(1500.0, $summary2->getTotalValue()); // 100*15.00
    }

    public function testGetStockSummaryWithEmptySpuIds(): void
    {
        $this->repository->expects($this->once())
            ->method('getBatchSummary')
            ->with([])
            ->willReturn([])
        ;

        $result = $this->stockCalculator->getStockSummary([]);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testCheckStockAvailability(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 80,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 120,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->checkStockAvailability($sku, 150);

        $this->assertTrue($result);
    }

    public function testCheckStockAvailabilityInsufficientStock(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
        ]);

        $batches = [$batch1];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->checkStockAvailability($sku, 100);

        $this->assertFalse($result);
    }

    public function testCheckStockAvailabilityEarlyReturn(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 150,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'available_quantity' => 50,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->checkStockAvailability($sku, 100);

        // 应该在第一个批次就满足需求并返回true
        $this->assertTrue($result);
    }

    public function testGetBatchDetails(): void
    {
        $sku = $this->createMockSku('SKU001');

        $productionDate = new \DateTimeImmutable('2024-01-01');
        $expiryDate = new \DateTimeImmutable('2024-12-31');
        $createTime = new \DateTimeImmutable('2024-01-15 10:30:00');

        $batch1 = $this->createStockBatch([
            'id' => 1,
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
            'create_time' => $createTime,
        ]);

        $batch2 = $this->createStockBatch([
            'id' => 2,
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 150,
            'available_quantity' => 120,
            'reserved_quantity' => 20,
            'locked_quantity' => 10,
            'unit_cost' => 12.00,
            'quality_level' => 'B',
            'status' => 'partially_available',
            'location_id' => 'WH002',
            'production_date' => null,
            'expiry_date' => null,
            'create_time' => null,
        ]);

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->getBatchDetails($sku);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $detail1 = $result[0];
        $this->assertEquals(1, $detail1['id']);
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
        $this->assertEquals('2024-01-15 10:30:00', $detail1['createTime']);

        $detail2 = $result[1];
        $this->assertEquals(2, $detail2['id']);
        $this->assertEquals('BATCH002', $detail2['batchNo']);
        $this->assertEquals('B', $detail2['qualityLevel']);
        $this->assertEquals('partially_available', $detail2['status']);
        $this->assertEquals('WH002', $detail2['locationId']);
        $this->assertNull($detail2['productionDate']);
        $this->assertNull($detail2['expiryDate']);
        $this->assertNull($detail2['createTime']);
    }

    public function testGetStockStats(): void
    {
        $stats = [
            'total_skus' => 50,
            'total_quantity' => 1000,
            'total_available' => 800,
            'total_reserved' => 150,
            'total_locked' => 50,
            'expired_batches' => 5,
        ];

        $this->repository->expects($this->once())
            ->method('getTotalStockStats')
            ->willReturn($stats)
        ;

        $result = $this->stockCalculator->getStockStats();

        $this->assertIsArray($result);
        $this->assertEquals(50, $result['totalSkus']);
        $this->assertEquals(1000, $result['totalQuantity']);
        $this->assertEquals(800, $result['totalAvailable']);
        $this->assertEquals(150, $result['totalReserved']);
        $this->assertEquals(50, $result['totalLocked']);
        $this->assertEquals(5, $result['expiredBatches']);
        $this->assertEquals(20.0, $result['utilizationRate']); // (1000-800)/1000*100 = 20%
    }

    public function testGetStockStatsWithZeroQuantity(): void
    {
        $stats = [
            'total_skus' => 0,
            'total_quantity' => 0,
            'total_available' => 0,
            'total_reserved' => 0,
            'total_locked' => 0,
            'expired_batches' => 0,
        ];

        $this->repository->expects($this->once())
            ->method('getTotalStockStats')
            ->willReturn($stats)
        ;

        $result = $this->stockCalculator->getStockStats();

        $this->assertEquals(0, $result['utilizationRate']); // 避免除零错误
    }

    public function testGetValidStock(): void
    {
        $sku = $this->createMockSku('SKU001');

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

        $batches = [$batch1, $batch2];

        $this->repository->expects($this->once())
            ->method('findAvailableBySku')
            ->with($sku)
            ->willReturn($batches)
        ;

        $result = $this->stockCalculator->getValidStock($sku);

        $this->assertEquals(200, $result); // 80 + 120
    }

    public function testGetStockSummaryWithInvalidSku(): void
    {
        $spuIds = ['INVALID_SPU'];

        $batchSummaries = [
            'INVALID_SPU' => [
                'total_quantity' => 100,
                'total_available' => 90,
                'total_reserved' => 8,
                'total_locked' => 2,
                'total_batches' => 1,
            ],
        ];

        $this->repository->expects($this->once())
            ->method('getBatchSummary')
            ->with($spuIds)
            ->willReturn($batchSummaries)
        ;

        $this->skuLoader->expects($this->once())
            ->method('loadSkuByIdentifier')
            ->with('INVALID_SPU')
            ->willReturn(null)
        ;

        $result = $this->stockCalculator->getStockSummary($spuIds);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('INVALID_SPU', $result);

        $summary = $result['INVALID_SPU'];
        $this->assertInstanceOf(StockSummary::class, $summary);
        $this->assertEquals('INVALID_SPU', $summary->getSpuId());
        $this->assertEquals(100, $summary->getTotalQuantity());
        $this->assertEquals(90, $summary->getAvailableQuantity());
        $this->assertEquals(8, $summary->getReservedQuantity());
        $this->assertEquals(2, $summary->getLockedQuantity());
        $this->assertEquals(1, $summary->getTotalBatches());
        $this->assertEquals(0, $summary->getTotalValue()); // No batches found, so no value
        $this->assertCount(0, $summary->getBatches());
    }
}
