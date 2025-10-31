<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SKU;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\BundleItem;
use Tourze\StockManageBundle\Entity\BundleStock;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * BundleItem 测试数据.
 *
 * @internal
 */
class BundleItemFixtures extends Fixture
{
    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly SpuLoaderInterface $spuLoader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 创建测试用的 BundleStock
        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE-001');
        $bundleStock->setBundleName('测试组合商品');
        $bundleStock->setStatus('active');
        $manager->persist($bundleStock);

        // 创建测试用的 SPU 和 SKU
        $spu = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-BUNDLE-001', title: '组合商品测试');
        $sku = $this->skuLoader->createSku($spu, gtin: 'SKU-BUNDLE-001');

        // 创建测试用的 StockBatch
        $batch = new StockBatch();
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH-001');
        $batch->setQuantity(100);
        $batch->setUnitCost(10.00);
        $batch->setStatus('active');
        $manager->persist($batch);

        // 创建 BundleItem
        $bundleItem = new BundleItem();
        $bundleItem->setBundleStock($bundleStock);
        $bundleItem->setSku($sku);
        $bundleItem->setQuantity(2);
        $bundleItem->setOptional(false);
        $bundleItem->setSortOrder(1);
        $manager->persist($bundleItem);

        $manager->flush();
    }
}
