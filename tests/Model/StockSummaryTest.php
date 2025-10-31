<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\StockManageBundle\Model\StockSummary;

/**
 * StockSummary 测试.
 *
 * @internal
 */
#[CoversClass(StockSummary::class)]
class StockSummaryTest extends TestCase
{
    private StockSummary $stockSummary;

    protected function setUp(): void
    {
        $this->stockSummary = new StockSummary('test-spu-123');
    }

    public function testGetSpuId(): void
    {
        $this->assertEquals('test-spu-123', $this->stockSummary->getSpuId());
    }

    public function testSetAndGetTotalQuantity(): void
    {
        $this->stockSummary->setTotalQuantity(100);
        $this->assertEquals(100, $this->stockSummary->getTotalQuantity());
    }

    public function testSetAndGetAvailableQuantity(): void
    {
        $this->stockSummary->setAvailableQuantity(80);
        $this->assertEquals(80, $this->stockSummary->getAvailableQuantity());
    }

    public function testSetAndGetReservedQuantity(): void
    {
        $this->stockSummary->setReservedQuantity(15);
        $this->assertEquals(15, $this->stockSummary->getReservedQuantity());
    }

    public function testSetAndGetLockedQuantity(): void
    {
        $this->stockSummary->setLockedQuantity(5);
        $this->assertEquals(5, $this->stockSummary->getLockedQuantity());
    }

    public function testSetAndGetTotalBatches(): void
    {
        $this->stockSummary->setTotalBatches(3);
        $this->assertEquals(3, $this->stockSummary->getTotalBatches());
    }

    public function testSetAndGetTotalValue(): void
    {
        $this->stockSummary->setTotalValue(1250.50);
        $this->assertEquals(1250.50, $this->stockSummary->getTotalValue());
    }

    public function testSetAndGetBatches(): void
    {
        $batches = [
            ['batchId' => 'batch-1', 'quantity' => 50],
            ['batchId' => 'batch-2', 'quantity' => 30],
        ];

        $this->stockSummary->setBatches($batches);
        $this->assertEquals($batches, $this->stockSummary->getBatches());
    }

    public function testAddBatch(): void
    {
        $batch = ['batchId' => 'batch-3', 'quantity' => 20];
        $this->stockSummary->addBatch($batch);

        $batches = $this->stockSummary->getBatches();
        $this->assertCount(1, $batches);
        $this->assertEquals($batch, $batches[0]);
    }

    public function testToArray(): void
    {
        $this->stockSummary->setTotalQuantity(100);
        $this->stockSummary->setAvailableQuantity(80);
        $this->stockSummary->setReservedQuantity(15);
        $this->stockSummary->setLockedQuantity(5);
        $this->stockSummary->setTotalBatches(3);
        $this->stockSummary->setTotalValue(1250.50);

        $batch = ['batchId' => 'batch-1', 'quantity' => 50];
        $this->stockSummary->addBatch($batch);

        $result = $this->stockSummary->toArray();

        $this->assertEquals('test-spu-123', $result['spuId']);
        $this->assertEquals(100, $result['totalQuantity']);
        $this->assertEquals(80, $result['availableQuantity']);
        $this->assertEquals(15, $result['reservedQuantity']);
        $this->assertEquals(5, $result['lockedQuantity']);
        $this->assertEquals(3, $result['totalBatches']);
        $this->assertEquals(1250.50, $result['totalValue']);
        $this->assertEquals(12.505, $result['averageUnitCost']);
        $this->assertEquals([$batch], $result['batches']);
    }

    public function testToArrayWithZeroQuantity(): void
    {
        $result = $this->stockSummary->toArray();

        $this->assertEquals('test-spu-123', $result['spuId']);
        $this->assertEquals(0, $result['totalQuantity']);
        $this->assertEquals(0, $result['averageUnitCost']);
    }

    public function testDefaultValues(): void
    {
        $this->assertEquals(0, $this->stockSummary->getTotalQuantity());
        $this->assertEquals(0, $this->stockSummary->getAvailableQuantity());
        $this->assertEquals(0, $this->stockSummary->getReservedQuantity());
        $this->assertEquals(0, $this->stockSummary->getLockedQuantity());
        $this->assertEquals(0, $this->stockSummary->getTotalBatches());
        $this->assertEquals(0.00, $this->stockSummary->getTotalValue());
        $this->assertEquals([], $this->stockSummary->getBatches());
    }
}
