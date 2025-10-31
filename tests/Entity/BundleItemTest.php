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
#[CoversClass(BundleItem::class)]
class BundleItemTest extends AbstractEntityTestCase
{
    protected function createEntity(): BundleItem
    {
        return new BundleItem();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        $sku = new Sku();
        $bundleStock = new BundleStock();

        yield 'bundleStock' => ['bundleStock', $bundleStock];
        yield 'sku' => ['sku', $sku];
        yield 'quantity' => ['quantity', 5];
        yield 'optional' => ['optional', true];
        yield 'sortOrder' => ['sortOrder', 10];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable()];
    }

    public function testToString(): void
    {
        /** @var BundleItem $bundleItem */
        $bundleItem = $this->createEntity();
        $sku = new Sku();

        $bundleItem->setSku($sku);
        $bundleItem->setQuantity(3);

        $expected = 'SKU x3';
        $this->assertEquals($expected, $bundleItem->__toString());
    }

    public function testBidirectionalAssociation(): void
    {
        $bundleStock = new BundleStock();
        /** @var BundleItem $bundleItem */
        $bundleItem = $this->createEntity();
        $sku = new Sku();

        $bundleItem->setSku($sku);
        $bundleItem->setQuantity(2);

        $bundleStock->addItem($bundleItem);

        $this->assertEquals($bundleStock, $bundleItem->getBundleStock());
        $this->assertTrue($bundleStock->getItems()->contains($bundleItem));
        $this->assertCount(1, $bundleStock->getItems());
    }

    public function testItemRemoval(): void
    {
        $bundleStock = new BundleStock();
        /** @var BundleItem $bundleItem */
        $bundleItem = $this->createEntity();
        $sku = new Sku();

        $bundleItem->setSku($sku);
        $bundleItem->setQuantity(2);

        $bundleStock->addItem($bundleItem);
        $this->assertCount(1, $bundleStock->getItems());

        $bundleStock->removeItem($bundleItem);
        $this->assertCount(0, $bundleStock->getItems());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
