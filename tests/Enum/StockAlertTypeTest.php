<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\StockManageBundle\Enum\StockAlertType;

/**
 * @internal
 */
#[CoversClass(StockAlertType::class)]
class StockAlertTypeTest extends AbstractEnumTestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('low_stock', StockAlertType::LOW_STOCK->value);
        $this->assertEquals('out_of_stock', StockAlertType::OUT_OF_STOCK->value);
        $this->assertEquals('high_stock', StockAlertType::HIGH_STOCK->value);
        $this->assertEquals('expiry_warning', StockAlertType::EXPIRY_WARNING->value);
        $this->assertEquals('quality_issue', StockAlertType::QUALITY_ISSUE->value);
    }

    public function testLabels(): void
    {
        $this->assertEquals('库存不足', StockAlertType::LOW_STOCK->getLabel());
        $this->assertEquals('缺货', StockAlertType::OUT_OF_STOCK->getLabel());
        $this->assertEquals('库存过多', StockAlertType::HIGH_STOCK->getLabel());
        $this->assertEquals('过期预警', StockAlertType::EXPIRY_WARNING->getLabel());
        $this->assertEquals('质量问题', StockAlertType::QUALITY_ISSUE->getLabel());
    }

    public function testCases(): void
    {
        $cases = StockAlertType::cases();
        $this->assertCount(5, $cases);
        $this->assertContains(StockAlertType::LOW_STOCK, $cases);
        $this->assertContains(StockAlertType::OUT_OF_STOCK, $cases);
        $this->assertContains(StockAlertType::HIGH_STOCK, $cases);
        $this->assertContains(StockAlertType::EXPIRY_WARNING, $cases);
        $this->assertContains(StockAlertType::QUALITY_ISSUE, $cases);
    }

    public function testFromValue(): void
    {
        $this->assertEquals(StockAlertType::LOW_STOCK, StockAlertType::from('low_stock'));
        $this->assertEquals(StockAlertType::OUT_OF_STOCK, StockAlertType::from('out_of_stock'));
        $this->assertEquals(StockAlertType::HIGH_STOCK, StockAlertType::from('high_stock'));
        $this->assertEquals(StockAlertType::EXPIRY_WARNING, StockAlertType::from('expiry_warning'));
        $this->assertEquals(StockAlertType::QUALITY_ISSUE, StockAlertType::from('quality_issue'));
    }

    protected function createEnum(): object
    {
        return StockAlertType::LOW_STOCK;
    }

    public function testToArray(): void
    {
        $expected = [
            'value' => 'low_stock',
            'label' => '库存不足',
        ];
        $this->assertEquals($expected, StockAlertType::LOW_STOCK->toArray());
    }
}
