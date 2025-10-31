<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockSnapshot;

class StockSnapshotFixtures extends Fixture
{
    public const STOCK_SNAPSHOT_DAILY = 'stock_snapshot_daily';
    public const STOCK_SNAPSHOT_MONTHLY = 'stock_snapshot_monthly';
    public const STOCK_SNAPSHOT_INVENTORY = 'stock_snapshot_inventory';

    public function load(ObjectManager $manager): void
    {
        // 日常快照
        $dailySnapshot = new StockSnapshot();
        $dailySnapshot->setSnapshotNo('SNAP20241210001');
        $dailySnapshot->setType('daily');
        $dailySnapshot->setTriggerMethod('auto');
        $dailySnapshot->setSummary([
            'total_quantity' => 350,
            'total_value' => 21500.00,
            'product_count' => 3,
            'batch_count' => 3,
        ]);
        $dailySnapshot->setDetails([
            'PRODUCT_001' => ['quantity' => 100, 'value' => 5000.00],
            'PRODUCT_002' => ['quantity' => 50, 'value' => 6000.00],
            'PRODUCT_003' => ['quantity' => 200, 'value' => 6000.00],
        ]);
        $dailySnapshot->setTotalQuantity(350);
        $dailySnapshot->setTotalValue(17000.00);
        $dailySnapshot->setProductCount(3);
        $dailySnapshot->setBatchCount(3);
        $dailySnapshot->setOperator('system_auto');
        $dailySnapshot->setNotes('系统自动生成日常快照');
        $dailySnapshot->setMetadata([
            'snapshot_time' => '2024-12-10 00:00:00',
            'system_version' => '1.0.0',
        ]);

        $manager->persist($dailySnapshot);
        $this->addReference(self::STOCK_SNAPSHOT_DAILY, $dailySnapshot);

        // 月度快照
        $monthlySnapshot = new StockSnapshot();
        $monthlySnapshot->setSnapshotNo('SNAP2024110001');
        $monthlySnapshot->setType('monthly');
        $monthlySnapshot->setTriggerMethod('manual');
        $monthlySnapshot->setSummary([
            'total_quantity' => 420,
            'total_value' => 25800.00,
            'product_count' => 4,
            'batch_count' => 5,
        ]);
        $monthlySnapshot->setTotalQuantity(420);
        $monthlySnapshot->setTotalValue(25800.00);
        $monthlySnapshot->setProductCount(4);
        $monthlySnapshot->setBatchCount(5);
        $monthlySnapshot->setOperator('accountant_001');
        $monthlySnapshot->setNotes('月度财务快照');
        $monthlySnapshot->setMetadata([
            'report_month' => '2024-11',
            'approved_by' => 'finance_manager',
        ]);
        $monthlySnapshot->setValidUntil(new \DateTimeImmutable('2025-12-31'));

        $manager->persist($monthlySnapshot);
        $this->addReference(self::STOCK_SNAPSHOT_MONTHLY, $monthlySnapshot);

        // 盘点快照
        $inventorySnapshot = new StockSnapshot();
        $inventorySnapshot->setSnapshotNo('SNAP2024120001');
        $inventorySnapshot->setType('inventory_count');
        $inventorySnapshot->setTriggerMethod('event');
        $inventorySnapshot->setSummary([
            'total_quantity' => 345,
            'total_value' => 21150.00,
            'product_count' => 3,
            'batch_count' => 3,
        ]);
        $inventorySnapshot->setTotalQuantity(345);
        $inventorySnapshot->setTotalValue(21150.00);
        $inventorySnapshot->setProductCount(3);
        $inventorySnapshot->setBatchCount(3);
        $inventorySnapshot->setLocationId('WH-A-01');
        $inventorySnapshot->setOperator('auditor_001');
        $inventorySnapshot->setNotes('年度盘点快照');
        $inventorySnapshot->setMetadata([
            'audit_type' => 'annual',
            'audit_team' => 'TEAM_001',
            'discrepancies_found' => true,
        ]);

        $manager->persist($inventorySnapshot);
        $this->addReference(self::STOCK_SNAPSHOT_INVENTORY, $inventorySnapshot);

        $manager->flush();
    }
}
