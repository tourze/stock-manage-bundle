<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service\AllocationStrategy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Service\AllocationStrategy\AllocationStrategyInterface;
use Tourze\StockManageBundle\Service\AllocationStrategy\LifoStrategy;

/**
 * @internal
 */
#[CoversClass(LifoStrategy::class)]
#[RunTestsInSeparateProcesses]
class LifoStrategyTest extends AbstractIntegrationTestCase
{
    private LifoStrategy $strategy;

    protected function onSetUp(): void
    {
        $this->strategy = self::getService(LifoStrategy::class);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(AllocationStrategyInterface::class, $this->strategy);
    }

    public function testGetName(): void
    {
        $this->assertEquals('lifo', $this->strategy->getName());
    }

    public function testSortBatchesByCreateTimeDescending(): void
    {
        $batch1 = $this->createBatchWithCreateTime('2024-01-15 10:00:00');
        $batch2 = $this->createBatchWithCreateTime('2024-01-10 09:00:00');
        $batch3 = $this->createBatchWithCreateTime('2024-01-20 11:00:00');

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最晚创建（后进先出）
        $this->assertSame($batch1, $sortedBatches[1]);
        $this->assertSame($batch2, $sortedBatches[2]); // 最早创建
    }

    public function testSortBatchesWithSameCreateTime(): void
    {
        $createTime = '2024-01-15 10:00:00';
        $batch1 = $this->createBatchWithCreateTime($createTime);
        $batch2 = $this->createBatchWithCreateTime($createTime);
        $batch3 = $this->createBatchWithCreateTime('2024-01-20 11:00:00');

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最晚创建
        // 相同创建时间的批次，顺序可能不确定但都在后面
        $this->assertTrue(in_array($batch1, [$sortedBatches[1], $sortedBatches[2]], true));
        $this->assertTrue(in_array($batch2, [$sortedBatches[1], $sortedBatches[2]], true));
    }

    public function testSortBatchesEmptyArray(): void
    {
        $result = $this->strategy->sortBatches([]);
        $this->assertEmpty($result);
    }

    public function testSortBatchesSingleBatch(): void
    {
        $batch = $this->createBatchWithCreateTime('2024-01-15 10:00:00');
        $result = $this->strategy->sortBatches([$batch]);

        $this->assertCount(1, $result);
        $this->assertSame($batch, $result[0]);
    }

    public function testSortBatchesAlreadySortedDescending(): void
    {
        $batch1 = $this->createBatchWithCreateTime('2024-01-20 11:00:00');
        $batch2 = $this->createBatchWithCreateTime('2024-01-15 10:00:00');
        $batch3 = $this->createBatchWithCreateTime('2024-01-10 09:00:00');

        $batches = [$batch1, $batch2, $batch3]; // 已经按LIFO排序
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch1, $sortedBatches[0]); // 最晚的
        $this->assertSame($batch2, $sortedBatches[1]);
        $this->assertSame($batch3, $sortedBatches[2]); // 最早的
    }

    public function testSortBatchesReverseSorted(): void
    {
        $batch1 = $this->createBatchWithCreateTime('2024-01-10 09:00:00');
        $batch2 = $this->createBatchWithCreateTime('2024-01-15 10:00:00');
        $batch3 = $this->createBatchWithCreateTime('2024-01-20 11:00:00');

        $batches = [$batch1, $batch2, $batch3]; // 按FIFO排序
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最晚的（LIFO）
        $this->assertSame($batch2, $sortedBatches[1]);
        $this->assertSame($batch1, $sortedBatches[2]); // 最早的
    }

    public function testSortBatchesWithMicrosecondPrecision(): void
    {
        $batch1 = $this->createBatchWithCreateTime('2024-01-15 10:00:00.001000');
        $batch2 = $this->createBatchWithCreateTime('2024-01-15 10:00:00.000500');
        $batch3 = $this->createBatchWithCreateTime('2024-01-15 10:00:00.002000');

        $batches = [$batch1, $batch2, $batch3];
        $sortedBatches = $this->strategy->sortBatches($batches);

        $this->assertSame($batch3, $sortedBatches[0]); // 最晚（微秒级）
        $this->assertSame($batch1, $sortedBatches[1]);
        $this->assertSame($batch2, $sortedBatches[2]); // 最早（微秒级）
    }

    public function testSortBatchesRandomOrder(): void
    {
        $batch1 = $this->createBatchWithCreateTime('2024-01-12 08:30:00');
        $batch2 = $this->createBatchWithCreateTime('2024-01-18 14:45:00');
        $batch3 = $this->createBatchWithCreateTime('2024-01-05 16:20:00');
        $batch4 = $this->createBatchWithCreateTime('2024-01-25 12:10:00');

        $batches = [$batch1, $batch2, $batch3, $batch4];
        $sortedBatches = $this->strategy->sortBatches($batches);

        // 按LIFO顺序验证：最新的在前
        $this->assertSame($batch4, $sortedBatches[0]); // 2024-01-25 最新
        $this->assertSame($batch2, $sortedBatches[1]); // 2024-01-18
        $this->assertSame($batch1, $sortedBatches[2]); // 2024-01-12
        $this->assertSame($batch3, $sortedBatches[3]); // 2024-01-05 最旧
    }

    private function createBatchWithCreateTime(string $createTime): StockBatch
    {
        $batch = new StockBatch();
        $createDateTime = new \DateTimeImmutable($createTime);

        // 使用反射设置私有属性，因为 setCreateTime 是 final 方法
        $reflection = new \ReflectionClass($batch);
        $property = $reflection->getProperty('createTime');
        $property->setAccessible(true);
        $property->setValue($batch, $createDateTime);

        return $batch;
    }
}
