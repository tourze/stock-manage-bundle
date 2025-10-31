<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\StockManageBundle\Entity\BusinessStockLock;
use Tourze\StockManageBundle\Repository\BusinessStockLockRepository;

/**
 * @internal
 */
#[CoversClass(BusinessStockLockRepository::class)]
#[RunTestsInSeparateProcesses]
class BusinessStockLockRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepositoryClass(): string
    {
        return BusinessStockLockRepository::class;
    }

    protected function getEntityClass(): string
    {
        return BusinessStockLock::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    protected function createNewEntity(): object
    {
        $businessStockLock = new BusinessStockLock();
        $businessStockLock->setType('order');
        $businessStockLock->setBusinessId('TEST_BUSINESS_' . uniqid());
        $businessStockLock->setReason('测试用途');
        $businessStockLock->setBatchIds(['batch_1', 'batch_2']);
        $businessStockLock->setQuantities([10, 20]);

        return $businessStockLock;
    }

    protected function getRepository(): BusinessStockLockRepository
    {
        return self::getService(BusinessStockLockRepository::class);
    }

    public function testCanSaveAndRetrieveBusinessStockLock(): void
    {
        $repository = $this->getRepository();
        $businessStockLock = new BusinessStockLock();

        // 设置必要的字段以满足验证要求
        $businessStockLock->setType('order');
        $businessStockLock->setBusinessId('TEST_BUSINESS_001');
        $businessStockLock->setReason('测试库存锁定');
        $businessStockLock->setBatchIds(['batch_001', 'batch_002']);
        $businessStockLock->setQuantities([50, 30]);

        $repository->save($businessStockLock);

        self::assertGreaterThan(0, $businessStockLock->getId());

        $found = $repository->find($businessStockLock->getId());
        self::assertInstanceOf(BusinessStockLock::class, $found);
        self::assertSame($businessStockLock->getId(), $found->getId());
        self::assertSame('order', $found->getType());
        self::assertSame('TEST_BUSINESS_001', $found->getBusinessId());
    }

    public function testFindByBusinessId(): void
    {
        $repository = $this->getRepository();
        $businessId = 'TEST_BUSINESS_FIND_' . uniqid();

        // 创建两个不同的锁定记录
        $lock1 = new BusinessStockLock();
        $lock1->setType('order');
        $lock1->setBusinessId($businessId);
        $lock1->setReason('第一个锁定');
        $lock1->setBatchIds(['batch_001']);
        $lock1->setQuantities([10]);
        $repository->save($lock1);

        $lock2 = new BusinessStockLock();
        $lock2->setType('promotion');
        $lock2->setBusinessId($businessId);
        $lock2->setReason('第二个锁定');
        $lock2->setBatchIds(['batch_002']);
        $lock2->setQuantities([20]);
        $repository->save($lock2);

        $results = $repository->findByBusinessId($businessId);

        self::assertCount(2, $results);
        self::assertContainsOnlyInstancesOf(BusinessStockLock::class, $results);

        // 验证所有结果都属于指定的业务ID
        foreach ($results as $result) {
            self::assertSame($businessId, $result->getBusinessId());
        }

        // 验证结果包含我们创建的两个锁定记录（不依赖排序）
        $foundIds = array_map(fn ($lock) => $lock->getId(), $results);
        self::assertContains($lock1->getId(), $foundIds);
        self::assertContains($lock2->getId(), $foundIds);
    }

    public function testFindActiveLocks(): void
    {
        $repository = $this->getRepository();

        // 创建活跃锁定（无过期时间）
        $activeLock1 = new BusinessStockLock();
        $activeLock1->setType('order');
        $activeLock1->setBusinessId('ACTIVE_BUSINESS_001');
        $activeLock1->setReason('活跃锁定1');
        $activeLock1->setBatchIds(['batch_001']);
        $activeLock1->setQuantities([10]);
        $activeLock1->setStatus('active');
        $repository->save($activeLock1);

        // 创建活跃锁定（未来过期时间）
        $activeLock2 = new BusinessStockLock();
        $activeLock2->setType('promotion');
        $activeLock2->setBusinessId('ACTIVE_BUSINESS_002');
        $activeLock2->setReason('活跃锁定2');
        $activeLock2->setBatchIds(['batch_002']);
        $activeLock2->setQuantities([20]);
        $activeLock2->setStatus('active');
        $activeLock2->setExpiresTime(new \DateTimeImmutable('+1 hour'));
        $repository->save($activeLock2);

        // 创建过期锁定
        $expiredLock = new BusinessStockLock();
        $expiredLock->setType('system');
        $expiredLock->setBusinessId('EXPIRED_BUSINESS_001');
        $expiredLock->setReason('过期锁定');
        $expiredLock->setBatchIds(['batch_003']);
        $expiredLock->setQuantities([30]);
        $expiredLock->setStatus('active');
        $expiredLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        $repository->save($expiredLock);

        $activeLocks = $repository->findActiveLocks();

        self::assertGreaterThanOrEqual(2, count($activeLocks));
        self::assertContainsOnlyInstancesOf(BusinessStockLock::class, $activeLocks);

        foreach ($activeLocks as $lock) {
            self::assertSame('active', $lock->getStatus());
            if (null !== $lock->getExpiresTime()) {
                self::assertGreaterThan(new \DateTimeImmutable(), $lock->getExpiresTime());
            }
        }
    }

    public function testFindExpiredLocks(): void
    {
        $repository = $this->getRepository();

        // 创建过期锁定
        $expiredLock = new BusinessStockLock();
        $expiredLock->setType('order');
        $expiredLock->setBusinessId('EXPIRED_BUSINESS_TEST');
        $expiredLock->setReason('测试过期锁定');
        $expiredLock->setBatchIds(['batch_expired']);
        $expiredLock->setQuantities([50]);
        $expiredLock->setStatus('active');
        $expiredLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        $repository->save($expiredLock);

        $expiredLocks = $repository->findExpiredLocks();

        self::assertGreaterThanOrEqual(1, count($expiredLocks));
        self::assertContainsOnlyInstancesOf(BusinessStockLock::class, $expiredLocks);

        foreach ($expiredLocks as $lock) {
            self::assertSame('active', $lock->getStatus());
            self::assertNotNull($lock->getExpiresTime());
            self::assertLessThanOrEqual(new \DateTimeImmutable(), $lock->getExpiresTime());
        }
    }

    public function testFindByType(): void
    {
        $repository = $this->getRepository();
        $testType = 'manual';

        // 创建指定类型的锁定记录
        $lock = new BusinessStockLock();
        $lock->setType($testType);
        $lock->setBusinessId('TYPE_TEST_BUSINESS');
        $lock->setReason('类型测试');
        $lock->setBatchIds(['batch_type']);
        $lock->setQuantities([25]);
        $repository->save($lock);

        $results = $repository->findByType($testType);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(BusinessStockLock::class, $results);

        foreach ($results as $result) {
            self::assertSame($testType, $result->getType());
        }
    }

    public function testHasActiveLock(): void
    {
        $repository = $this->getRepository();
        $businessId = 'HAS_ACTIVE_TEST_' . uniqid();

        // 测试没有锁定的情况
        self::assertFalse($repository->hasActiveLock($businessId));

        // 创建活跃锁定
        $activeLock = new BusinessStockLock();
        $activeLock->setType('order');
        $activeLock->setBusinessId($businessId);
        $activeLock->setReason('活跃锁定测试');
        $activeLock->setBatchIds(['batch_active']);
        $activeLock->setQuantities([15]);
        $activeLock->setStatus('active');
        $repository->save($activeLock);

        // 测试有活跃锁定的情况
        self::assertTrue($repository->hasActiveLock($businessId));

        // 创建过期锁定
        $expiredBusinessId = 'EXPIRED_TEST_' . uniqid();
        $expiredLock = new BusinessStockLock();
        $expiredLock->setType('order');
        $expiredLock->setBusinessId($expiredBusinessId);
        $expiredLock->setReason('过期锁定测试');
        $expiredLock->setBatchIds(['batch_expired']);
        $expiredLock->setQuantities([35]);
        $expiredLock->setStatus('active');
        $expiredLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        $repository->save($expiredLock);

        // 测试过期锁定不算活跃
        self::assertFalse($repository->hasActiveLock($expiredBusinessId));
    }

    public function testRemoveBusinessStockLock(): void
    {
        $repository = $this->getRepository();
        $businessStockLock = new BusinessStockLock();

        $businessStockLock->setType('order');
        $businessStockLock->setBusinessId('REMOVE_TEST_BUSINESS');
        $businessStockLock->setReason('删除测试');
        $businessStockLock->setBatchIds(['batch_remove']);
        $businessStockLock->setQuantities([40]);

        $repository->save($businessStockLock);
        $id = $businessStockLock->getId();

        self::assertNotNull($repository->find($id));

        $repository->remove($businessStockLock);

        self::assertNull($repository->find($id));
    }

    public function testEntityTotalLockedQuantity(): void
    {
        $businessStockLock = new BusinessStockLock();
        $businessStockLock->setQuantities([10, 20, 30]);

        self::assertSame(60, $businessStockLock->getTotalLockedQuantity());
    }

    public function testEntityIsExpired(): void
    {
        $businessStockLock = new BusinessStockLock();

        // 无过期时间
        self::assertFalse($businessStockLock->isExpired());

        // 未来时间
        $businessStockLock->setExpiresTime(new \DateTimeImmutable('+1 hour'));
        self::assertFalse($businessStockLock->isExpired());

        // 过去时间
        $businessStockLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        self::assertTrue($businessStockLock->isExpired());
    }

    public function testEntityIsActive(): void
    {
        $businessStockLock = new BusinessStockLock();
        $businessStockLock->setStatus('active');

        // 活跃状态且未过期
        self::assertTrue($businessStockLock->isActive());

        // 活跃状态但已过期
        $businessStockLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        self::assertFalse($businessStockLock->isActive());

        // 非活跃状态
        $businessStockLock->setStatus('released');
        $businessStockLock->setExpiresTime(null);
        self::assertFalse($businessStockLock->isActive());
    }
}
