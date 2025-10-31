<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockAdjustment;
use Tourze\StockManageBundle\Enum\StockAdjustmentStatus;
use Tourze\StockManageBundle\Enum\StockAdjustmentType;

class StockAdjustmentFixtures extends Fixture
{
    public const STOCK_ADJUSTMENT_DAMAGE = 'stock_adjustment_damage';
    public const STOCK_ADJUSTMENT_THEFT = 'stock_adjustment_theft';
    public const STOCK_ADJUSTMENT_CORRECTION = 'stock_adjustment_correction';

    public function load(ObjectManager $manager): void
    {
        // 商品损坏调整
        $damageAdjustment = new StockAdjustment();
        $damageAdjustment->setAdjustmentNo('ADJ2024001');
        $damageAdjustment->setType(StockAdjustmentType::DAMAGE);
        $damageAdjustment->setStatus(StockAdjustmentStatus::COMPLETED);
        $damageAdjustment->setItems([
            'item_1' => [
                'batch_id' => 'BATCH_001',
                'spu_id' => 'PRODUCT_001',
                'original_quantity' => 100,
                'adjusted_quantity' => 95,
                'difference' => -5,
                'reason' => '运输过程中损坏5件商品',
            ],
        ]);
        $damageAdjustment->setReason('商品在运输过程中受损');
        $damageAdjustment->setCostImpact(-250.00);
        $damageAdjustment->setLocationId('WH-A-01');
        $damageAdjustment->setOperator('warehouse_staff_001');
        $damageAdjustment->setApprover('manager_001');
        $damageAdjustment->setNotes('已拍照存档，保险公司理赔中');

        $manager->persist($damageAdjustment);
        $this->addReference(self::STOCK_ADJUSTMENT_DAMAGE, $damageAdjustment);

        // 盘亏调整
        $theftAdjustment = new StockAdjustment();
        $theftAdjustment->setAdjustmentNo('ADJ2024002');
        $theftAdjustment->setType(StockAdjustmentType::OTHER);
        $theftAdjustment->setStatus(StockAdjustmentStatus::PENDING);
        $theftAdjustment->setItems([
            'item_1' => [
                'batch_id' => 'BATCH_002',
                'spu_id' => 'PRODUCT_002',
                'original_quantity' => 50,
                'adjusted_quantity' => 48,
                'difference' => -2,
                'reason' => '盘点发现短缺2件商品',
            ],
        ]);
        $theftAdjustment->setReason('月度盘点发现库存短缺');
        $theftAdjustment->setCostImpact(-180.00);
        $theftAdjustment->setLocationId('WH-B-02');
        $theftAdjustment->setOperator('auditor_001');

        $manager->persist($theftAdjustment);
        $this->addReference(self::STOCK_ADJUSTMENT_THEFT, $theftAdjustment);

        // 数据更正调整
        $correctionAdjustment = new StockAdjustment();
        $correctionAdjustment->setAdjustmentNo('ADJ2024003');
        $correctionAdjustment->setType(StockAdjustmentType::CORRECTION);
        $correctionAdjustment->setStatus(StockAdjustmentStatus::PROCESSING);
        $correctionAdjustment->setItems([
            'item_1' => [
                'batch_id' => 'BATCH_003',
                'spu_id' => 'PRODUCT_003',
                'original_quantity' => 200,
                'adjusted_quantity' => 205,
                'difference' => 5,
                'reason' => '系统录入错误，实际数量应为205',
            ],
        ]);
        $correctionAdjustment->setReason('系统数据录入错误更正');
        $correctionAdjustment->setCostImpact(300.00);
        $correctionAdjustment->setLocationId('WH-C-03');
        $correctionAdjustment->setOperator('admin_001');
        $correctionAdjustment->setApprover('supervisor_001');
        $correctionAdjustment->setNotes('经重新清点确认，实际数量为205件');

        $manager->persist($correctionAdjustment);
        $this->addReference(self::STOCK_ADJUSTMENT_CORRECTION, $correctionAdjustment);

        $manager->flush();
    }
}
