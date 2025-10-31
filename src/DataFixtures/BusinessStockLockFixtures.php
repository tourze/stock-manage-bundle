<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\BusinessStockLock;

class BusinessStockLockFixtures extends Fixture
{
    public const BUSINESS_STOCK_LOCK_ORDER = 'business_stock_lock_order';
    public const BUSINESS_STOCK_LOCK_RESERVATION = 'business_stock_lock_reservation';
    public const BUSINESS_STOCK_LOCK_EXPIRED = 'business_stock_lock_expired';

    public function load(ObjectManager $manager): void
    {
        // 订单锁定
        $orderLock = new BusinessStockLock();
        $orderLock->setType('order');
        $orderLock->setBusinessId('ORDER-2024001');
        $orderLock->setReason('客户订单锁定库存');
        $orderLock->setBatchIds(['BATCH-001', 'BATCH-002']);
        $orderLock->setQuantities([10, 20]);
        $orderLock->setStatus('active');
        $orderLock->setExpiresTime(new \DateTimeImmutable('+1 hour'));
        $orderLock->setCreatedBy('customer_service_001');
        $orderLock->setMetadata([
            'customer_id' => 'CUST_001',
            'order_total' => 1500.00,
            'priority' => 'normal',
        ]);

        $manager->persist($orderLock);
        $this->addReference(self::BUSINESS_STOCK_LOCK_ORDER, $orderLock);

        // 预定锁定
        $reservationLock = new BusinessStockLock();
        $reservationLock->setType('reservation');
        $reservationLock->setBusinessId('RESERVATION-2024001');
        $reservationLock->setReason('培训课程预定锁定');
        $reservationLock->setBatchIds(['BATCH-003', 'BATCH-004', 'BATCH-005']);
        $reservationLock->setQuantities([5, 8, 12]);
        $reservationLock->setStatus('active');
        $reservationLock->setExpiresTime(new \DateTimeImmutable('+2 days'));
        $reservationLock->setCreatedBy('training_coordinator_001');
        $reservationLock->setMetadata([
            'customer_id' => 'CUST_002',
            'training_date' => '2024-12-15',
            'trainer_id' => 'TRAINER_001',
            'participants' => 25,
        ]);

        $manager->persist($reservationLock);
        $this->addReference(self::BUSINESS_STOCK_LOCK_RESERVATION, $reservationLock);

        // 已过期的锁定
        $expiredLock = new BusinessStockLock();
        $expiredLock->setType('order');
        $expiredLock->setBusinessId('ORDER-2024002');
        $expiredLock->setReason('已过期的订单锁定');
        $expiredLock->setBatchIds(['BATCH-006']);
        $expiredLock->setQuantities([15]);
        $expiredLock->setStatus('active');
        $expiredLock->setExpiresTime(new \DateTimeImmutable('-1 hour'));
        $expiredLock->setCreatedBy('customer_service_002');
        $expiredLock->setReleasedTime(new \DateTimeImmutable('-30 minutes'));
        $expiredLock->setReleaseReason('订单超时自动释放');
        $expiredLock->setReleasedBy('system');
        $expiredLock->setMetadata([
            'customer_id' => 'CUST_003',
            'order_total' => 750.00,
            'timeout_reason' => 'payment_timeout',
        ]);

        $manager->persist($expiredLock);
        $this->addReference(self::BUSINESS_STOCK_LOCK_EXPIRED, $expiredLock);

        $manager->flush();
    }
}
