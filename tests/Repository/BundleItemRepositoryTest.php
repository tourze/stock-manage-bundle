<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\BundleItem;
use Tourze\StockManageBundle\Entity\BundleStock;
use Tourze\StockManageBundle\Repository\BundleItemRepository;

/**
 * @internal
 */
#[CoversClass(BundleItemRepository::class)]
#[RunTestsInSeparateProcesses]
class BundleItemRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Repository test setup
    }

    protected function getRepository(): BundleItemRepository
    {
        return self::getService(BundleItemRepository::class);
    }

    protected function createNewEntity(): object
    {
        $em = self::getEntityManager();

        $spu = new Spu();
        $spu->setGtin('SPU_' . uniqid());
        $spu->setTitle('Test Product');
        $spu->setValid(true);
        $em->persist($spu);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setGtin('SKU_' . uniqid());
        $sku->setUnit('个');
        $em->persist($sku);

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_' . uniqid());
        $bundleStock->setBundleName('Test Bundle');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');
        $em->persist($bundleStock);

        $em->flush();

        $bundleItem = new BundleItem();
        $bundleItem->setBundleStock($bundleStock);
        $bundleItem->setSku($sku);
        $bundleItem->setQuantity(5);
        $bundleItem->setOptional(false);
        $bundleItem->setSortOrder(1);

        return $bundleItem;
    }

    public function testBundleContainsSku(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        // 创建 SPU
        $spu1 = new Spu();
        $spu1->setGtin('SPU_TEST_001');
        $spu1->setTitle('测试商品1');
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setGtin('SPU_TEST_002');
        $spu2->setTitle('测试商品2');
        $spu2->setValid(true);

        // 创建 SKU
        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('SKU_TEST_001');
        $sku1->setUnit('个');
        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('SKU_TEST_002');
        $sku2->setUnit('个');

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_TEST_001');
        $bundleStock->setBundleName('Test Bundle');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');

        $bundleItem = new BundleItem();
        $bundleItem->setBundleStock($bundleStock);
        $bundleItem->setSku($sku1);
        $bundleItem->setQuantity(3);
        $bundleItem->setOptional(false);
        $bundleItem->setSortOrder(1);

        $em = self::getEntityManager();
        $em->persist($spu1);
        $em->persist($spu2);
        $em->persist($sku1);
        $em->persist($sku2);
        $em->persist($bundleStock);
        $em->persist($bundleItem);
        $em->flush();

        $this->assertTrue($repository->bundleContainsSku($bundleStock, $sku1));
        $this->assertFalse($repository->bundleContainsSku($bundleStock, $sku2));
    }

    public function testFindByBundleStock(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_TEST_002');
        $bundleStock->setBundleName('Test Bundle 2');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');

        $spu1 = new Spu();
        $spu1->setGtin('SPU_TEST_003');
        $spu1->setTitle('测试商品3');
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setGtin('SPU_TEST_004');
        $spu2->setTitle('测试商品4');
        $spu2->setValid(true);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('SKU_TEST_003');
        $sku1->setUnit('个');
        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('SKU_TEST_004');
        $sku2->setUnit('个');

        $bundleItem1 = new BundleItem();
        $bundleItem1->setBundleStock($bundleStock);
        $bundleItem1->setSku($sku1);
        $bundleItem1->setQuantity(2);
        $bundleItem1->setOptional(false);
        $bundleItem1->setSortOrder(2);

        $bundleItem2 = new BundleItem();
        $bundleItem2->setBundleStock($bundleStock);
        $bundleItem2->setSku($sku2);
        $bundleItem2->setQuantity(1);
        $bundleItem2->setOptional(true);
        $bundleItem2->setSortOrder(1);

        $em = self::getEntityManager();
        $em->persist($spu1);
        $em->persist($spu2);
        $em->persist($sku1);
        $em->persist($sku2);
        $em->persist($bundleStock);
        $em->persist($bundleItem1);
        $em->persist($bundleItem2);
        $em->flush();

        $items = $repository->findByBundleStock($bundleStock);

        $this->assertCount(2, $items);
        $this->assertEquals($sku2, $items[0]->getSku());
        $this->assertEquals($sku1, $items[1]->getSku());
    }

    public function testFindBySku(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        $spu = new Spu();
        $spu->setGtin('SPU_TEST_005');
        $spu->setTitle('测试商品5');
        $spu->setValid(true);

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setGtin('SKU_TEST_005');
        $sku->setUnit('个');

        $bundleStock1 = new BundleStock();
        $bundleStock1->setBundleCode('BUNDLE_TEST_003');
        $bundleStock1->setBundleName('Test Bundle 3');
        $bundleStock1->setType('fixed');
        $bundleStock1->setStatus('active');

        $bundleStock2 = new BundleStock();
        $bundleStock2->setBundleCode('BUNDLE_TEST_004');
        $bundleStock2->setBundleName('Test Bundle 4');
        $bundleStock2->setType('variable');
        $bundleStock2->setStatus('active');

        $bundleItem1 = new BundleItem();
        $bundleItem1->setBundleStock($bundleStock1);
        $bundleItem1->setSku($sku);
        $bundleItem1->setQuantity(3);
        $bundleItem1->setOptional(false);
        $bundleItem1->setSortOrder(1);

        $bundleItem2 = new BundleItem();
        $bundleItem2->setBundleStock($bundleStock2);
        $bundleItem2->setSku($sku);
        $bundleItem2->setQuantity(2);
        $bundleItem2->setOptional(true);
        $bundleItem2->setSortOrder(2);

        $em = self::getEntityManager();
        $em->persist($spu);
        $em->persist($sku);
        $em->persist($bundleStock1);
        $em->persist($bundleStock2);
        $em->persist($bundleItem1);
        $em->persist($bundleItem2);
        $em->flush();

        $items = $repository->findBySku($sku);

        $this->assertCount(2, $items);
        $this->assertEquals($bundleStock1, $items[0]->getBundleStock());
        $this->assertEquals($bundleStock2, $items[1]->getBundleStock());
    }

    public function testFindOptionalItemsByBundleStock(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_TEST_005');
        $bundleStock->setBundleName('Test Bundle 5');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');

        $spu1 = new Spu();
        $spu1->setGtin('SPU_TEST_006');
        $spu1->setTitle('测试商品6');
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setGtin('SPU_TEST_007');
        $spu2->setTitle('测试商品7');
        $spu2->setValid(true);

        $spu3 = new Spu();
        $spu3->setGtin('SPU_TEST_008');
        $spu3->setTitle('测试商品8');
        $spu3->setValid(true);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('SKU_TEST_006');
        $sku1->setUnit('个');
        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('SKU_TEST_007');
        $sku2->setUnit('个');
        $sku3 = new Sku();
        $sku3->setSpu($spu3);
        $sku3->setGtin('SKU_TEST_008');
        $sku3->setUnit('个');

        $requiredItem = new BundleItem();
        $requiredItem->setBundleStock($bundleStock);
        $requiredItem->setSku($sku1);
        $requiredItem->setQuantity(1);
        $requiredItem->setOptional(false);
        $requiredItem->setSortOrder(1);

        $optionalItem1 = new BundleItem();
        $optionalItem1->setBundleStock($bundleStock);
        $optionalItem1->setSku($sku2);
        $optionalItem1->setQuantity(1);
        $optionalItem1->setOptional(true);
        $optionalItem1->setSortOrder(2);

        $optionalItem2 = new BundleItem();
        $optionalItem2->setBundleStock($bundleStock);
        $optionalItem2->setSku($sku3);
        $optionalItem2->setQuantity(1);
        $optionalItem2->setOptional(true);
        $optionalItem2->setSortOrder(3);

        $em = self::getEntityManager();
        $em->persist($spu1);
        $em->persist($spu2);
        $em->persist($spu3);
        $em->persist($sku1);
        $em->persist($sku2);
        $em->persist($sku3);
        $em->persist($bundleStock);
        $em->persist($requiredItem);
        $em->persist($optionalItem1);
        $em->persist($optionalItem2);
        $em->flush();

        $optionalItems = $repository->findOptionalItemsByBundleStock($bundleStock);

        $this->assertCount(2, $optionalItems);
        $this->assertEquals($sku2, $optionalItems[0]->getSku());
        $this->assertEquals($sku3, $optionalItems[1]->getSku());
    }

    public function testFindRequiredItemsByBundleStock(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_TEST_006');
        $bundleStock->setBundleName('Test Bundle 6');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');

        $spu1 = new Spu();
        $spu1->setGtin('SPU_TEST_009');
        $spu1->setTitle('测试商品9');
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setGtin('SPU_TEST_010');
        $spu2->setTitle('测试商品10');
        $spu2->setValid(true);

        $spu3 = new Spu();
        $spu3->setGtin('SPU_TEST_011');
        $spu3->setTitle('测试商品11');
        $spu3->setValid(true);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('SKU_TEST_009');
        $sku1->setUnit('个');
        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('SKU_TEST_010');
        $sku2->setUnit('个');
        $sku3 = new Sku();
        $sku3->setSpu($spu3);
        $sku3->setGtin('SKU_TEST_011');
        $sku3->setUnit('个');

        $requiredItem1 = new BundleItem();
        $requiredItem1->setBundleStock($bundleStock);
        $requiredItem1->setSku($sku1);
        $requiredItem1->setQuantity(2);
        $requiredItem1->setOptional(false);
        $requiredItem1->setSortOrder(1);

        $requiredItem2 = new BundleItem();
        $requiredItem2->setBundleStock($bundleStock);
        $requiredItem2->setSku($sku2);
        $requiredItem2->setQuantity(1);
        $requiredItem2->setOptional(false);
        $requiredItem2->setSortOrder(2);

        $optionalItem = new BundleItem();
        $optionalItem->setBundleStock($bundleStock);
        $optionalItem->setSku($sku3);
        $optionalItem->setQuantity(1);
        $optionalItem->setOptional(true);
        $optionalItem->setSortOrder(3);

        $em = self::getEntityManager();
        $em->persist($spu1);
        $em->persist($spu2);
        $em->persist($spu3);
        $em->persist($sku1);
        $em->persist($sku2);
        $em->persist($sku3);
        $em->persist($bundleStock);
        $em->persist($requiredItem1);
        $em->persist($requiredItem2);
        $em->persist($optionalItem);
        $em->flush();

        $requiredItems = $repository->findRequiredItemsByBundleStock($bundleStock);

        $this->assertCount(2, $requiredItems);
        $this->assertEquals($sku1, $requiredItems[0]->getSku());
        $this->assertEquals($sku2, $requiredItems[1]->getSku());
        $this->assertFalse($requiredItems[0]->isOptional());
        $this->assertFalse($requiredItems[1]->isOptional());
    }

    public function testReorderItems(): void
    {
        /** @var BundleItemRepository $repository */
        $repository = $this->getRepository();

        $bundleStock = new BundleStock();
        $bundleStock->setBundleCode('BUNDLE_TEST_007');
        $bundleStock->setBundleName('Test Bundle 7');
        $bundleStock->setType('fixed');
        $bundleStock->setStatus('active');

        $spu1 = new Spu();
        $spu1->setGtin('SPU_TEST_012');
        $spu1->setTitle('测试商品12');
        $spu1->setValid(true);

        $spu2 = new Spu();
        $spu2->setGtin('SPU_TEST_013');
        $spu2->setTitle('测试商品13');
        $spu2->setValid(true);

        $spu3 = new Spu();
        $spu3->setGtin('SPU_TEST_014');
        $spu3->setTitle('测试商品14');
        $spu3->setValid(true);

        $sku1 = new Sku();
        $sku1->setSpu($spu1);
        $sku1->setGtin('SKU_TEST_012');
        $sku1->setUnit('个');
        $sku2 = new Sku();
        $sku2->setSpu($spu2);
        $sku2->setGtin('SKU_TEST_013');
        $sku2->setUnit('个');
        $sku3 = new Sku();
        $sku3->setSpu($spu3);
        $sku3->setGtin('SKU_TEST_014');
        $sku3->setUnit('个');

        $item1 = new BundleItem();
        $item1->setBundleStock($bundleStock);
        $item1->setSku($sku1);
        $item1->setQuantity(1);
        $item1->setOptional(false);
        $item1->setSortOrder(1);

        $item2 = new BundleItem();
        $item2->setBundleStock($bundleStock);
        $item2->setSku($sku2);
        $item2->setQuantity(1);
        $item2->setOptional(false);
        $item2->setSortOrder(2);

        $item3 = new BundleItem();
        $item3->setBundleStock($bundleStock);
        $item3->setSku($sku3);
        $item3->setQuantity(1);
        $item3->setOptional(false);
        $item3->setSortOrder(3);

        $em = self::getEntityManager();
        $em->persist($spu1);
        $em->persist($spu2);
        $em->persist($spu3);
        $em->persist($sku1);
        $em->persist($sku2);
        $em->persist($sku3);
        $em->persist($bundleStock);
        $em->persist($item1);
        $em->persist($item2);
        $em->persist($item3);
        $em->flush();

        $item1Id = $item1->getId();
        $item2Id = $item2->getId();
        $item3Id = $item3->getId();

        self::assertNotNull($item1Id, 'Item 1 ID should not be null after persist');
        self::assertNotNull($item2Id, 'Item 2 ID should not be null after persist');
        self::assertNotNull($item3Id, 'Item 3 ID should not be null after persist');

        $newOrder = [$item3Id, $item1Id, $item2Id];
        $repository->reorderItems($bundleStock, $newOrder);

        $em->refresh($item1);
        $em->refresh($item2);
        $em->refresh($item3);

        $this->assertEquals(2, $item1->getSortOrder());
        $this->assertEquals(3, $item2->getSortOrder());
        $this->assertEquals(1, $item3->getSortOrder());

        $reorderedItems = $repository->findByBundleStock($bundleStock);
        $this->assertEquals($sku3, $reorderedItems[0]->getSku());
        $this->assertEquals($sku1, $reorderedItems[1]->getSku());
        $this->assertEquals($sku2, $reorderedItems[2]->getSku());
    }
}
