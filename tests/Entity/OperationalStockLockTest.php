<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\StockManageBundle\Entity\OperationalStockLock;

/**
 * @internal
 */
#[CoversClass(OperationalStockLock::class)]
class OperationalStockLockTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new OperationalStockLock();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'batchIds' => ['batchIds', ['BATCH001', 'BATCH002']];
        yield 'operationType' => ['operationType', 'inventory'];
        yield 'operator' => ['operator', 'inspector_001'];
        yield 'reason' => ['reason', 'Routine quality inspection'];
        yield 'status' => ['status', 'completed'];
        yield 'priority' => ['priority', 'high'];
        yield 'estimatedDuration' => ['estimatedDuration', 120];
        yield 'department' => ['department', 'Warehouse Operations'];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'completedTime' => ['completedTime', new \DateTimeImmutable()];
        yield 'completedBy' => ['completedBy', 'inspector_002'];
        yield 'completionNotes' => ['completionNotes', 'All items passed quality check'];
        yield 'releasedTime' => ['releasedTime', new \DateTimeImmutable()];
        yield 'releaseReason' => ['releaseReason', 'Operation completed successfully'];
        yield 'operationResult' => ['operationResult', ['passed_items' => 95, 'failed_items' => 5]];
    }

    public function testInitialState(): void
    {
        /** @var OperationalStockLock $lock */
        $lock = $this->createEntity();

        $this->assertNull($lock->getId());
        $this->assertEquals('active', $lock->getStatus());
        $this->assertEquals('normal', $lock->getPriority());
        $this->assertNull($lock->getReleasedTime());
        $this->assertNull($lock->getCompletedTime());
    }

    public function testIsActive(): void
    {
        /** @var OperationalStockLock $lock */
        $lock = $this->createEntity();

        $lock->setStatus('active');
        $this->assertTrue($lock->isActive());

        $lock->setStatus('completed');
        $this->assertFalse($lock->isActive());

        $lock->setStatus('cancelled');
        $this->assertFalse($lock->isActive());
    }

    public function testToString(): void
    {
        /** @var OperationalStockLock $lock */
        $lock = $this->createEntity();
        $lock->setOperator('inspector_001');
        $lock->setOperationType('inventory');

        $this->assertEquals('OpLock[inspector_001]-inventory', (string) $lock);
    }
}
