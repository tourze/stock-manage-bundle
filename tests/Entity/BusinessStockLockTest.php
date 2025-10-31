<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\StockManageBundle\Entity\BusinessStockLock;

/**
 * @internal
 */
#[CoversClass(BusinessStockLock::class)]
class BusinessStockLockTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new BusinessStockLock();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'batchIds' => ['batchIds', ['BATCH001', 'BATCH002']];
        yield 'quantities' => ['quantities', ['BATCH001' => 50, 'BATCH002' => 30]];
        yield 'type' => ['type', 'order'];
        yield 'businessId' => ['businessId', 'ORDER001'];
        yield 'reason' => ['reason', 'Order processing'];
        yield 'status' => ['status', 'released'];
        yield 'expiresTime' => ['expiresTime', new \DateTimeImmutable('+2 hours')];
        yield 'createdBy' => ['createdBy', 'user_123'];
        yield 'releasedTime' => ['releasedTime', new \DateTimeImmutable()];
        yield 'releaseReason' => ['releaseReason', 'Order completed'];
        yield 'releasedBy' => ['releasedBy', 'admin_456'];
        yield 'metadata' => ['metadata', ['priority' => 'high', 'customer_type' => 'vip']];
    }

    public function testInitialState(): void
    {
        /** @var BusinessStockLock $lock */
        $lock = $this->createEntity();

        $this->assertNull($lock->getId());
        $this->assertEquals('active', $lock->getStatus());
        $this->assertNull($lock->getReleasedTime());
        $this->assertNull($lock->getReleaseReason());
    }

    public function testTotalLockedQuantity(): void
    {
        /** @var BusinessStockLock $lock */
        $lock = $this->createEntity();

        $this->assertEquals(0, $lock->getTotalLockedQuantity());

        $quantities = [
            'BATCH001' => 50,
            'BATCH002' => 30,
            'BATCH003' => 20,
        ];

        $lock->setQuantities($quantities);
        $this->assertEquals(100, $lock->getTotalLockedQuantity());
    }

    public function testIsExpired(): void
    {
        /** @var BusinessStockLock $lock */
        $lock = $this->createEntity();

        $this->assertFalse($lock->isExpired());

        $past = new \DateTimeImmutable('-1 hour');
        $lock->setExpiresTime($past);
        $this->assertTrue($lock->isExpired());

        $future = new \DateTimeImmutable('+1 hour');
        $lock->setExpiresTime($future);
        $this->assertFalse($lock->isExpired());
    }

    public function testIsActive(): void
    {
        /** @var BusinessStockLock $lock */
        $lock = $this->createEntity();

        $future = new \DateTimeImmutable('+1 hour');
        $lock->setExpiresTime($future);

        $lock->setStatus('active');
        $this->assertTrue($lock->isActive());

        $lock->setStatus('released');
        $this->assertFalse($lock->isActive());

        $lock->setStatus('expired');
        $this->assertFalse($lock->isActive());

        // Test expired lock
        $past = new \DateTimeImmutable('-1 hour');
        $lock->setStatus('active');
        $lock->setExpiresTime($past);
        $this->assertFalse($lock->isActive());
    }

    public function testToString(): void
    {
        /** @var BusinessStockLock $lock */
        $lock = $this->createEntity();
        $lock->setBusinessId('ORDER001');
        $lock->setType('order');

        $this->assertEquals('BusinessLock[ORDER001]-order', (string) $lock);
    }
}
