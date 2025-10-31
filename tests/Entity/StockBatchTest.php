<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @internal
 */
#[CoversClass(StockBatch::class)]
class StockBatchTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new StockBatch();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'batchNo' => ['batchNo', 'BATCH001'];
        yield 'quantity' => ['quantity', 1000];
        yield 'availableQuantity' => ['availableQuantity', 800];
        yield 'reservedQuantity' => ['reservedQuantity', 100];
        yield 'lockedQuantity' => ['lockedQuantity', 50];
        yield 'unitCost' => ['unitCost', 10.50];
        yield 'qualityLevel' => ['qualityLevel', 'A'];
        yield 'status' => ['status', 'available'];
        yield 'locationId' => ['locationId', 'LOC001'];
        yield 'attributes' => ['attributes', ['color' => 'red']];
    }

    public function testInitialState(): void
    {
        /** @var StockBatch $batch */
        $batch = $this->createEntity();

        $this->assertNull($batch->getId());
        $this->assertEquals('pending', $batch->getStatus());
        $this->assertEquals('', $batch->getBatchNo());
        $this->assertEquals(0, $batch->getQuantity());
        $this->assertEquals(0, $batch->getAvailableQuantity());
        $this->assertEquals(0, $batch->getReservedQuantity());
        $this->assertEquals(0, $batch->getLockedQuantity());
    }

    public function testToString(): void
    {
        /** @var StockBatch $batch */
        $batch = $this->createEntity();
        $batch->setBatchNo('BATCH001');

        $this->assertEquals('BATCH001', (string) $batch);
    }
}
