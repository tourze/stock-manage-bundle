<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\BundleItem;
use Tourze\StockManageBundle\Entity\BundleStock;

class BundleStockFixtures extends Fixture
{
    public const BUNDLE_STOCK_HOTEL_PACKAGE = 'bundle_stock_hotel_package';
    public const BUNDLE_STOCK_TRAINING_COMBO = 'bundle_stock_training_combo';
    public const BUNDLE_STOCK_FLEXIBLE_PACKAGE = 'bundle_stock_flexible_package';

    public function load(ObjectManager $manager): void
    {
        // 酒店套餐组合
        $hotelPackage = new BundleStock();
        $hotelPackage->setBundleCode('HOTEL_PKG_001');
        $hotelPackage->setBundleName('豪华酒店住宿套餐');
        $hotelPackage->setDescription('包含豪华客房、早餐、温泉和健身房使用权');
        $hotelPackage->setType('fixed');
        $hotelPackage->setStatus('active');

        // 添加套餐项目
        $itemData = [
            ['spu_id' => 'ROOM_001', 'quantity' => 1, 'optional' => false],
            ['spu_id' => 'BREAKFAST_001', 'quantity' => 2, 'optional' => false],
            ['spu_id' => 'SPA_001', 'quantity' => 1, 'optional' => false],
            ['spu_id' => 'GYM_001', 'quantity' => 1, 'optional' => false],
        ];

        foreach ($itemData as $data) {
            // 这里需要实际的 SKU 实体，在实际应用中应该从数据库获取
            // 暂时跳过 BundleItem 的创建
        }

        $manager->persist($hotelPackage);
        $this->addReference(self::BUNDLE_STOCK_HOTEL_PACKAGE, $hotelPackage);

        // 培训课程组合
        $trainingCombo = new BundleStock();
        $trainingCombo->setBundleCode('TRAIN_COMBO_001');
        $trainingCombo->setBundleName('企业管理培训组合课程');
        $trainingCombo->setDescription('包含领导力课程、团队建设和项目管理三门核心课程');
        $trainingCombo->setType('fixed');
        $trainingCombo->setStatus('active');

        // 添加培训课程项目 - 在实际应用中应该获取真实的 SKU 实体

        $manager->persist($trainingCombo);
        $this->addReference(self::BUNDLE_STOCK_TRAINING_COMBO, $trainingCombo);

        // 灵活组合套餐
        $flexiblePackage = new BundleStock();
        $flexiblePackage->setBundleCode('FLEX_PKG_001');
        $flexiblePackage->setBundleName('自选服务组合包');
        $flexiblePackage->setDescription('客户可根据需要选择不同的服务组合');
        $flexiblePackage->setType('flexible');
        $flexiblePackage->setStatus('active');

        // 添加灵活套餐项目 - 在实际应用中应该获取真实的 SKU 实体

        $manager->persist($flexiblePackage);
        $this->addReference(self::BUNDLE_STOCK_FLEXIBLE_PACKAGE, $flexiblePackage);

        $manager->flush();
    }
}
