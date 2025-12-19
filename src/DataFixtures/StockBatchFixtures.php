<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockBatch;

final class StockBatchFixtures extends Fixture
{
    public const STOCK_BATCH_NORMAL = 'stock_batch_normal';
    public const STOCK_BATCH_PREMIUM = 'stock_batch_premium';
    public const STOCK_BATCH_ECONOMY = 'stock_batch_economy';

    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly SpuLoaderInterface $spuLoader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 标准批次
        $normalBatch = new StockBatch();
        $spu1 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-BATCH-001', title: '标准批次商品');
        $sku1 = $this->skuLoader->createSku($spu1, gtin: 'SKU-BATCH-001');
        $normalBatch->setSku($sku1);
        $normalBatch->setBatchNo('BATCH2024001');
        $normalBatch->setQuantity(100);
        $normalBatch->setAvailableQuantity(95);
        $normalBatch->setReservedQuantity(3);
        $normalBatch->setLockedQuantity(2);
        $normalBatch->setUnitCost(50.00);
        $normalBatch->setQualityLevel('A');
        $normalBatch->setStatus('active');
        $normalBatch->setProductionDate(new \DateTimeImmutable('2024-01-15'));
        $normalBatch->setExpiryDate(new \DateTimeImmutable('2025-01-15'));
        $normalBatch->setLocationId('WH-A-01');
        $normalBatch->setAttributes([
            'supplier' => 'SUPPLIER_001',
            'manufacture_date' => '2024-01-10',
            'storage_conditions' => 'room_temperature',
        ]);

        $manager->persist($normalBatch);
        $this->addReference(self::STOCK_BATCH_NORMAL, $normalBatch);

        // 高级批次
        $premiumBatch = new StockBatch();
        $spu2 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-BATCH-002', title: '高级批次商品');
        $sku2 = $this->skuLoader->createSku($spu2, gtin: 'SKU-BATCH-002');
        $premiumBatch->setSku($sku2);
        $premiumBatch->setBatchNo('BATCH2024002');
        $premiumBatch->setQuantity(50);
        $premiumBatch->setAvailableQuantity(48);
        $premiumBatch->setReservedQuantity(1);
        $premiumBatch->setLockedQuantity(1);
        $premiumBatch->setUnitCost(120.00);
        $premiumBatch->setQualityLevel('A+');
        $premiumBatch->setStatus('active');
        $premiumBatch->setProductionDate(new \DateTimeImmutable('2024-02-01'));
        $premiumBatch->setExpiryDate(new \DateTimeImmutable('2025-02-01'));
        $premiumBatch->setLocationId('WH-B-02');
        $premiumBatch->setAttributes([
            'supplier' => 'SUPPLIER_002',
            'manufacture_date' => '2024-01-28',
            'storage_conditions' => 'cool_dry_place',
        ]);

        $manager->persist($premiumBatch);
        $this->addReference(self::STOCK_BATCH_PREMIUM, $premiumBatch);

        // 经济批次
        $economyBatch = new StockBatch();
        $spu3 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-BATCH-003', title: '经济批次商品');
        $sku3 = $this->skuLoader->createSku($spu3, gtin: 'SKU-BATCH-003');
        $economyBatch->setSku($sku3);
        $economyBatch->setBatchNo('BATCH2024003');
        $economyBatch->setQuantity(200);
        $economyBatch->setAvailableQuantity(195);
        $economyBatch->setReservedQuantity(3);
        $economyBatch->setLockedQuantity(2);
        $economyBatch->setUnitCost(30.00);
        $economyBatch->setQualityLevel('B');
        $economyBatch->setStatus('active');
        $economyBatch->setProductionDate(new \DateTimeImmutable('2024-03-01'));
        $economyBatch->setExpiryDate(new \DateTimeImmutable('2024-12-01'));
        $economyBatch->setLocationId('WH-C-03');
        $economyBatch->setAttributes([
            'supplier' => 'SUPPLIER_003',
            'manufacture_date' => '2024-02-25',
            'storage_conditions' => 'standard',
        ]);

        $manager->persist($economyBatch);
        $this->addReference(self::STOCK_BATCH_ECONOMY, $economyBatch);

        $manager->flush();
    }
}
