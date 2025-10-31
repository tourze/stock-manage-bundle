<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockAdjustment;
use Tourze\StockManageBundle\Enum\StockAdjustmentStatus;
use Tourze\StockManageBundle\Enum\StockAdjustmentType;

/**
 * @internal
 */
#[CoversClass(StockAdjustment::class)]
class StockAdjustmentTest extends AbstractEntityTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function createEntity(): StockAdjustment
    {
        return new StockAdjustment();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'adjustmentNo' => ['adjustmentNo', 'ADJ202412001'];
        yield 'type' => ['type', StockAdjustmentType::INVENTORY_COUNT];
        yield 'status' => ['status', StockAdjustmentStatus::PROCESSING];
        yield 'sku' => ['sku', new Sku()];
        yield 'items' => ['items', ['BATCH001' => ['batch_id' => 'BATCH001', 'difference' => 5]]];
        yield 'reason' => ['reason', '月末盘点'];
        yield 'costImpact' => ['costImpact', 1500.50];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'operator' => ['operator', 'user_123'];
        yield 'approver' => ['approver', 'manager_456'];
        yield 'approvedTime' => ['approvedTime', new \DateTimeImmutable()];
        yield 'notes' => ['notes', '盘点发现部分商品破损'];
        yield 'attachments' => ['attachments', ['image1' => ['type' => 'image', 'url' => 'http://example.com/image1.jpg']]];
        yield 'metadata' => ['metadata', ['department' => '仓储部', 'shift' => '白班']];
        yield 'completedTime' => ['completedTime', new \DateTimeImmutable()];
    }

    public function testDefaultValues(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();

        $this->assertSame(StockAdjustmentStatus::PENDING, $adjustment->getStatus());
        $this->assertSame(0, $adjustment->getTotalAdjusted());
        $this->assertSame(0.0, $adjustment->getCostImpact());
        $this->assertSame([], $adjustment->getItems());
        $this->assertNull($adjustment->getLocationId());
        $this->assertNull($adjustment->getApprover());
        $this->assertNull($adjustment->getApprovedTime());
        $this->assertNull($adjustment->getCompletedTime());
        $this->assertNull($adjustment->getNotes());
        $this->assertNull($adjustment->getAttachments());
        $this->assertNull($adjustment->getMetadata());
        $this->assertInstanceOf(\DateTimeImmutable::class, $adjustment->getCreateTime());
    }

    public function testItemsWithAutomaticCalculation(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();

        $items = [
            'BATCH001' => [
                'batch_id' => 'BATCH001',
                'spu_id' => 'SPU001',
                'original_quantity' => 100,
                'adjusted_quantity' => 95,
                'difference' => -5,
                'reason' => '破损',
            ],
            'BATCH002' => [
                'batch_id' => 'BATCH002',
                'spu_id' => 'SPU002',
                'original_quantity' => 50,
                'adjusted_quantity' => 52,
                'difference' => 2,
                'reason' => '发现漏记',
            ],
            'BATCH003' => [
                'batch_id' => 'BATCH003',
                'spu_id' => 'SPU003',
                'original_quantity' => 75,
                'adjusted_quantity' => 70,
                'difference' => -5,
                'reason' => '过期销毁',
            ],
        ];

        $adjustment->setItems($items);

        $this->assertSame($items, $adjustment->getItems());
        $this->assertSame(-8, $adjustment->getTotalAdjusted()); // -5 + 2 + (-5) = -8
    }

    public function testStatusHelperMethods(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();

        $adjustment->setStatus(StockAdjustmentStatus::PENDING);
        $this->assertTrue($adjustment->isPending());
        $this->assertFalse($adjustment->isProcessing());
        $this->assertFalse($adjustment->isCompleted());
        $this->assertFalse($adjustment->isCancelled());

        $adjustment->setStatus(StockAdjustmentStatus::PROCESSING);
        $this->assertFalse($adjustment->isPending());
        $this->assertTrue($adjustment->isProcessing());
        $this->assertFalse($adjustment->isCompleted());
        $this->assertFalse($adjustment->isCancelled());

        $adjustment->setStatus(StockAdjustmentStatus::COMPLETED);
        $this->assertFalse($adjustment->isPending());
        $this->assertFalse($adjustment->isProcessing());
        $this->assertTrue($adjustment->isCompleted());
        $this->assertFalse($adjustment->isCancelled());

        $adjustment->setStatus(StockAdjustmentStatus::CANCELLED);
        $this->assertFalse($adjustment->isPending());
        $this->assertFalse($adjustment->isProcessing());
        $this->assertFalse($adjustment->isCompleted());
        $this->assertTrue($adjustment->isCancelled());
    }

    public function testEmptyItemsCalculation(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();

        $adjustment->setItems([]);
        $this->assertSame(0, $adjustment->getTotalAdjusted());
    }

    public function testItemsWithoutDifference(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();

        $items = [
            'BATCH001' => ['batch_id' => 'BATCH001', 'spu_id' => 'SPU001'],
            'BATCH002' => ['batch_id' => 'BATCH002', 'spu_id' => 'SPU002'],
        ];

        $adjustment->setItems($items);
        $this->assertSame(0, $adjustment->getTotalAdjusted());
    }

    public function testToString(): void
    {
        /** @var StockAdjustment $adjustment */
        $adjustment = $this->createEntity();
        $adjustment->setAdjustmentNo('ADJ202412001');

        $this->assertEquals('ADJ202412001', (string) $adjustment);
    }
}
