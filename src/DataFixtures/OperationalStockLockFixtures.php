<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\OperationalStockLock;

class OperationalStockLockFixtures extends Fixture
{
    public const OPERATIONAL_LOCK_MAINTENANCE = 'operational_lock_maintenance';
    public const OPERATIONAL_LOCK_AUDIT = 'operational_lock_audit';
    public const OPERATIONAL_LOCK_RELOCATION = 'operational_lock_relocation';

    public function load(ObjectManager $manager): void
    {
        // 设备维护锁定
        $maintenanceLock = new OperationalStockLock();
        $maintenanceLock->setOperationType('maintenance');
        $maintenanceLock->setOperator('tech_staff_001');
        $maintenanceLock->setReason('设备定期维护需要锁定相关库存');
        $maintenanceLock->setStatus('active');
        $maintenanceLock->setPriority('high');
        $maintenanceLock->setEstimatedDuration(120);
        $maintenanceLock->setDepartment('技术部');
        $maintenanceLock->setLocationId('WH-A-01');
        $maintenanceLock->setBatchIds(['BATCH_001', 'BATCH_002']);

        $manager->persist($maintenanceLock);
        $this->addReference(self::OPERATIONAL_LOCK_MAINTENANCE, $maintenanceLock);

        // 库存盘点锁定
        $auditLock = new OperationalStockLock();
        $auditLock->setOperationType('audit');
        $auditLock->setOperator('auditor_001');
        $auditLock->setReason('月度库存盘点');
        $auditLock->setStatus('active');
        $auditLock->setPriority('normal');
        $auditLock->setEstimatedDuration(240);
        $auditLock->setDepartment('财务部');
        $auditLock->setLocationId('WH-B-02');
        $auditLock->setBatchIds(['BATCH_003', 'BATCH_004', 'BATCH_005']);

        $manager->persist($auditLock);
        $this->addReference(self::OPERATIONAL_LOCK_AUDIT, $auditLock);

        // 库位调整锁定
        $relocationLock = new OperationalStockLock();
        $relocationLock->setOperationType('relocation');
        $relocationLock->setOperator('warehouse_staff_001');
        $relocationLock->setReason('仓库布局调整，需要移动库存位置');
        $relocationLock->setStatus('active');
        $relocationLock->setPriority('low');
        $relocationLock->setEstimatedDuration(180);
        $relocationLock->setDepartment('仓储部');
        $relocationLock->setLocationId('WH-C-03');
        $relocationLock->setBatchIds(['BATCH_006']);

        $manager->persist($relocationLock);
        $this->addReference(self::OPERATIONAL_LOCK_RELOCATION, $relocationLock);

        $manager->flush();
    }
}
