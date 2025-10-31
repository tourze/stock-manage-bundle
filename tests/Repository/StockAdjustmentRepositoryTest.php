<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\StockManageBundle\Entity\StockAdjustment;
use Tourze\StockManageBundle\Enum\StockAdjustmentStatus;
use Tourze\StockManageBundle\Enum\StockAdjustmentType;
use Tourze\StockManageBundle\Repository\StockAdjustmentRepository;

/**
 * StockAdjustmentRepository 测试.
 *
 * @internal
 */
#[CoversClass(StockAdjustmentRepository::class)]
#[RunTestsInSeparateProcesses]
class StockAdjustmentRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Repository test setup
    }

    protected function createNewEntity(): StockAdjustment
    {
        $adjustment = new StockAdjustment();
        $adjustment->setAdjustmentNo('ADJ-' . uniqid());
        $adjustment->setType(StockAdjustmentType::INVENTORY_COUNT);
        $adjustment->setStatus(StockAdjustmentStatus::PENDING);
        $adjustment->setItems([
            'item1' => ['quantity' => 10, 'reason' => 'Test adjustment'],
        ]);
        $adjustment->setReason('Test stock adjustment');
        $adjustment->setOperator('test-user');
        $adjustment->setCostImpact(100.0);
        $adjustment->setLocationId('LOC001');

        return $adjustment;
    }

    protected function getRepository(): StockAdjustmentRepository
    {
        $repository = self::getEntityManager()->getRepository(StockAdjustment::class);
        self::assertInstanceOf(StockAdjustmentRepository::class, $repository);

        return $repository;
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $entity = $this->createNewEntity();
        $entity->setLocationId('TEST_LOC');
        $repository->save($entity, true);

        $results = $repository->findByLocation('TEST_LOC');
        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $adjustment) {
            $this->assertEquals('TEST_LOC', $adjustment->getLocationId());
        }
    }

    public function testFindPending(): void
    {
        $repository = $this->getRepository();
        $entity = $this->createNewEntity();
        $entity->setStatus(StockAdjustmentStatus::PENDING);
        $repository->save($entity, true);

        $results = $repository->findPending();
        $this->assertIsArray($results);

        foreach ($results as $adjustment) {
            $this->assertEquals(StockAdjustmentStatus::PENDING, $adjustment->getStatus());
        }
    }

    public function testFindByType(): void
    {
        $repository = $this->getRepository();
        $entity = $this->createNewEntity();
        $entity->setType(StockAdjustmentType::INVENTORY_COUNT);
        $repository->save($entity, true);

        $results = $repository->findByType(StockAdjustmentType::INVENTORY_COUNT->value);
        $this->assertIsArray($results);

        foreach ($results as $adjustment) {
            $this->assertEquals(StockAdjustmentType::INVENTORY_COUNT, $adjustment->getType());
        }
    }

    public function testFindByDateRange(): void
    {
        $repository = $this->getRepository();
        $entity = $this->createNewEntity();
        $repository->save($entity, true);

        $startDate = new \DateTimeImmutable('-1 day');
        $endDate = new \DateTimeImmutable('+1 day');

        $results = $repository->findByDateRange($startDate, $endDate);
        $this->assertIsArray($results);

        foreach ($results as $adjustment) {
            $this->assertGreaterThanOrEqual($startDate, $adjustment->getCreateTime());
            $this->assertLessThanOrEqual($endDate, $adjustment->getCreateTime());
        }
    }
}
