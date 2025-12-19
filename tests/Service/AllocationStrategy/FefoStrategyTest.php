<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service\AllocationStrategy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Service\AllocationStrategy\AllocationStrategyInterface;
use Tourze\StockManageBundle\Service\AllocationStrategy\FefoStrategy;

/**
 * @internal
 */
#[CoversClass(FefoStrategy::class)]
#[RunTestsInSeparateProcesses]
class FefoStrategyTest extends AbstractIntegrationTestCase
{
    private FefoStrategy $strategy;

    protected function onSetUp(): void
    {
        $this->strategy = self::getService(FefoStrategy::class);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(AllocationStrategyInterface::class, $this->strategy);
    }

    public function testGetName(): void
    {
        $this->assertEquals('fefo', $this->strategy->getName());
    }

    public function testSortBatchesByExpiryDate(): void
    {
        $batch1 = $this->createBatchWithExpiry('2024-12-01');
        $batch2 = $this->createBatchWithExpiry('2024-11-15');
        $batch3 = $this->createBatchWithExpiry('2024-12-15');

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch2, $sortedBatches[0]); // 最早过期
        $this->assertSame($batch1, $sortedBatches[1]);
        $this->assertSame($batch3, $sortedBatches[2]); // 最晚过期
    }

    public function testSortBatchesWithNullExpiryDates(): void
    {
        $batch1 = $this->createBatchWithExpiry('2024-12-01');
        $batch2 = $this->createBatchWithExpiry(null);
        $batch3 = $this->createBatchWithExpiry('2024-11-15');
        $batch4 = $this->createBatchWithExpiry(null);

        $batches = [$batch1, $batch2, $batch3, $batch4];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最早过期的有日期批次
        $this->assertSame($batch1, $sortedBatches[1]); // 第二早的有日期批次

        // 无过期日期的批次排在最后，顺序可能不确定
        $this->assertTrue(in_array($batch2, [$sortedBatches[2], $sortedBatches[3]], true));
        $this->assertTrue(in_array($batch4, [$sortedBatches[2], $sortedBatches[3]], true));
    }

    public function testSortBatchesAllWithoutExpiryDate(): void
    {
        $batch1 = $this->createBatchWithExpiry(null);
        $batch2 = $this->createBatchWithExpiry(null);
        $batch3 = $this->createBatchWithExpiry(null);

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        // 所有批次都没有过期日期，排序应该保持相对稳定
        $this->assertCount(3, $sortedBatches);
        $this->assertContains($batch1, $sortedBatches);
        $this->assertContains($batch2, $sortedBatches);
        $this->assertContains($batch3, $sortedBatches);
    }

    public function testSortBatchesEmptyArray(): void
    {
        $result = $this->strategy->sortBatches([]);
        $this->assertEmpty($result);
    }

    public function testSortBatchesSingleBatch(): void
    {
        $batch = $this->createBatchWithExpiry('2024-12-01');
        $result = $this->strategy->sortBatches([$batch]);

        $this->assertCount(1, $result);
        $this->assertSame($batch, $result[0]);
    }

    public function testSortBatchesWithSameExpiryDate(): void
    {
        $batch1 = $this->createBatchWithExpiry('2024-12-01');
        $batch2 = $this->createBatchWithExpiry('2024-12-01');
        $batch3 = $this->createBatchWithExpiry('2024-11-15');

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最早过期
        // 相同过期日期的批次，顺序可能不确定但都在第二、三位
        $this->assertTrue(in_array($batch1, [$sortedBatches[1], $sortedBatches[2]], true));
        $this->assertTrue(in_array($batch2, [$sortedBatches[1], $sortedBatches[2]], true));
    }

    private function createBatchWithExpiry(?string $expiryDate): StockBatch
    {
        $batch = new StockBatch();
        $batch->setBatchNo('BATCH-' . uniqid());
        $batch->setQuantity(100);
        $batch->setAvailableQuantity(100);

        if (null !== $expiryDate) {
            $expiryDateTime = new \DateTimeImmutable($expiryDate);
            $batch->setExpiryDate($expiryDateTime);
        }

        return $batch;
    }
}
