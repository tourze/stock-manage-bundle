<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\BundleItem;
use Tourze\StockManageBundle\Entity\BundleStock;

/**
 * @internal
 */
#[CoversClass(BundleStock::class)]
class BundleStockTest extends AbstractEntityTestCase
{
    protected function createEntity(): BundleStock
    {
        return new BundleStock();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'bundleCode' => ['bundleCode', 'BUNDLE_001'];
        yield 'bundleName' => ['bundleName', '测试组合商品'];
        yield 'description' => ['description', '这是一个测试组合商品'];
        yield 'type' => ['type', 'flexible'];
        yield 'status' => ['status', 'inactive'];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable()];
    }

    public function testToString(): void
    {
        /** @var BundleStock $bundleStock */
        $bundleStock = $this->createEntity();
        $bundleName = '测试组合商品';
        $bundleStock->setBundleName($bundleName);

        $this->assertEquals($bundleName, $bundleStock->__toString());
    }

    public function testItemsManagement(): void
    {
        /** @var BundleStock $bundleStock */
        $bundleStock = $this->createEntity();

        $sku1 = new Sku();
        $sku1->setGtin('sku-001');
        $item1 = new BundleItem();
        $item1->setSku($sku1);
        $item1->setQuantity(2);
        $item1->setOptional(false);

        $sku2 = new Sku();
        $sku2->setGtin('sku-002');
        $item2 = new BundleItem();
        $item2->setSku($sku2);
        $item2->setQuantity(1);
        $item2->setOptional(true);

        $bundleStock->addItem($item1);
        $bundleStock->addItem($item2);

        $this->assertCount(2, $bundleStock->getItems());
        $this->assertCount(1, $bundleStock->getRequiredItems());
        $this->assertCount(1, $bundleStock->getOptionalItems());
        $this->assertEquals(2, $bundleStock->getTotalItemCount());
    }
}
