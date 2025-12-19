<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\VirtualStock;

final class VirtualStockFixtures extends Fixture
{
    public const VIRTUAL_STOCK_PREORDER = 'virtual_stock_preorder';
    public const VIRTUAL_STOCK_PRODUCTION = 'virtual_stock_production';
    public const VIRTUAL_STOCK_BACKORDER = 'virtual_stock_backorder';

    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly SpuLoaderInterface $spuLoader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 预售库存
        $preorderStock = new VirtualStock();
        $spu4 = $this->spuLoader->createSpu(gtin: 'SPU_004', title: '测试商品004');
        $sku4 = $this->skuLoader->createSku($spu4, gtin: 'PRODUCT_004');
        $preorderStock->setSku($sku4);
        $preorderStock->setVirtualType('preorder');
        $preorderStock->setQuantity(50);
        $preorderStock->setStatus('active');
        $preorderStock->setDescription('新产品预售，预计1个月后发货');
        $preorderStock->setExpectedDate(new \DateTimeImmutable('+30 days'));
        $preorderStock->setBusinessId('PREORDER2024001');
        $preorderStock->setLocationId('WH-A-01');
        $preorderStock->setAttributes([
            'preorder_start_date' => '2024-12-01',
            'preorder_end_date' => '2024-12-31',
            'shipping_estimate' => '30_days',
            'deposit_required' => true,
        ]);

        $manager->persist($preorderStock);
        $this->addReference(self::VIRTUAL_STOCK_PREORDER, $preorderStock);

        // 生产库存
        $productionStock = new VirtualStock();
        $spu5 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-VIRTUAL-002', title: '生产商品');
        $sku5 = $this->skuLoader->createSku($spu5, gtin: 'SKU-VIRTUAL-002');
        $productionStock->setSku($sku5);
        $productionStock->setVirtualType('production');
        $productionStock->setQuantity(100);
        $productionStock->setStatus('active');
        $productionStock->setDescription('生产线正在生产的商品');
        $productionStock->setExpectedDate(new \DateTimeImmutable('+15 days'));
        $productionStock->setBusinessId('PRODUCTION2024002');
        $productionStock->setLocationId('WH-B-02');
        $productionStock->setAttributes([
            'production_line' => 'LINE_001',
            'production_start_date' => '2024-12-01',
            'production_capacity' => '20_per_day',
            'quality_check_required' => true,
        ]);

        $manager->persist($productionStock);
        $this->addReference(self::VIRTUAL_STOCK_PRODUCTION, $productionStock);

        // 缺货库存
        $backorderStock = new VirtualStock();
        $spu6 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-VIRTUAL-003', title: '缺货商品');
        $sku6 = $this->skuLoader->createSku($spu6, gtin: 'SKU-VIRTUAL-003');
        $backorderStock->setSku($sku6);
        $backorderStock->setVirtualType('backorder');
        $backorderStock->setQuantity(25);
        $backorderStock->setStatus('active');
        $backorderStock->setDescription('暂时缺货，等待补货的客户订单');
        $backorderStock->setExpectedDate(new \DateTimeImmutable('+7 days'));
        $backorderStock->setBusinessId('BACKORDER2024001');
        $backorderStock->setLocationId('WH-C-03');
        $backorderStock->setAttributes([
            'backorder_reason' => 'supplier_delay',
            'priority_level' => 'high',
            'customer_notification_sent' => true,
            'alternative_offered' => false,
        ]);

        $manager->persist($backorderStock);
        $this->addReference(self::VIRTUAL_STOCK_BACKORDER, $backorderStock);

        $manager->flush();
    }
}
