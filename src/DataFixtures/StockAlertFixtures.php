<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;

class StockAlertFixtures extends Fixture
{
    public const STOCK_ALERT_LOW_STOCK = 'stock_alert_low_stock';
    public const STOCK_ALERT_EXPIRY = 'stock_alert_expiry';
    public const STOCK_ALERT_QUALITY = 'stock_alert_quality';

    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly SpuLoaderInterface $spuLoader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 低库存预警
        $lowStockAlert = new StockAlert();
        $spu1 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-ALERT-001', title: '低库存预警商品');
        $sku1 = $this->skuLoader->createSku($spu1, gtin: 'SKU-ALERT-001');
        $lowStockAlert->setSku($sku1);
        $lowStockAlert->setAlertType(StockAlertType::LOW_STOCK);
        $lowStockAlert->setSeverity(StockAlertSeverity::HIGH);
        $lowStockAlert->setStatus(StockAlertStatus::ACTIVE);
        $lowStockAlert->setThresholdValue(10.0);
        $lowStockAlert->setCurrentValue(5.0);
        $lowStockAlert->setMessage('商品PRODUCT_001库存不足，当前库存5件，低于安全库存10件');
        $lowStockAlert->setLocationId('WH-A-01');
        $lowStockAlert->setMetadata([
            'reorder_point' => 10,
            'current_stock' => 5,
            'suggested_order' => 20,
        ]);

        $manager->persist($lowStockAlert);
        $this->addReference(self::STOCK_ALERT_LOW_STOCK, $lowStockAlert);

        // 过期预警
        $expiryAlert = new StockAlert();
        $spu2 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-ALERT-002', title: '过期预警商品');
        $sku2 = $this->skuLoader->createSku($spu2, gtin: 'SKU-ALERT-002');
        $expiryAlert->setSku($sku2);
        $expiryAlert->setAlertType(StockAlertType::EXPIRY_WARNING);
        $expiryAlert->setSeverity(StockAlertSeverity::MEDIUM);
        $expiryAlert->setStatus(StockAlertStatus::ACTIVE);
        $expiryAlert->setThresholdValue(30.0);
        $expiryAlert->setCurrentValue(15.0);
        $expiryAlert->setMessage('商品PRODUCT_002将在15天后过期，请及时处理');
        $expiryAlert->setLocationId('WH-B-02');
        $expiryAlert->setMetadata([
            'expiry_date' => '2024-12-25',
            'days_until_expiry' => 15,
            'batch_number' => 'BATCH_002',
        ]);

        $manager->persist($expiryAlert);
        $this->addReference(self::STOCK_ALERT_EXPIRY, $expiryAlert);

        // 质量预警
        $qualityAlert = new StockAlert();
        $spu3 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-ALERT-003', title: '质量预警商品');
        $sku3 = $this->skuLoader->createSku($spu3, gtin: 'SKU-ALERT-003');
        $qualityAlert->setSku($sku3);
        $qualityAlert->setAlertType(StockAlertType::QUALITY_ISSUE);
        $qualityAlert->setSeverity(StockAlertSeverity::CRITICAL);
        $qualityAlert->setStatus(StockAlertStatus::ACTIVE);
        $qualityAlert->setThresholdValue(95.0);
        $qualityAlert->setCurrentValue(88.0);
        $qualityAlert->setMessage('商品PRODUCT_003质量检测结果异常，合格率仅为88%');
        $qualityAlert->setLocationId('WH-C-03');
        $qualityAlert->setMetadata([
            'quality_score' => 88,
            'test_date' => '2024-12-10',
            'test_result' => 'failed',
            'issue_details' => '包装破损，部分商品受潮',
        ]);

        $manager->persist($qualityAlert);
        $this->addReference(self::STOCK_ALERT_QUALITY, $qualityAlert);

        $manager->flush();
    }
}
