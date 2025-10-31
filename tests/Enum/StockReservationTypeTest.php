<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\StockManageBundle\Enum\StockReservationType;

/**
 * @internal
 */
#[CoversClass(StockReservationType::class)]
class StockReservationTypeTest extends AbstractEnumTestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('order', StockReservationType::ORDER->value);
        $this->assertEquals('promotion', StockReservationType::PROMOTION->value);
        $this->assertEquals('vip', StockReservationType::VIP->value);
        $this->assertEquals('system', StockReservationType::SYSTEM->value);
    }

    public function testLabels(): void
    {
        $this->assertEquals('订单预定', StockReservationType::ORDER->getLabel());
        $this->assertEquals('促销预定', StockReservationType::PROMOTION->getLabel());
        $this->assertEquals('VIP预定', StockReservationType::VIP->getLabel());
        $this->assertEquals('系统预定', StockReservationType::SYSTEM->getLabel());
    }

    public function testCases(): void
    {
        $cases = StockReservationType::cases();
        $this->assertCount(4, $cases);
        $this->assertContains(StockReservationType::ORDER, $cases);
        $this->assertContains(StockReservationType::PROMOTION, $cases);
        $this->assertContains(StockReservationType::VIP, $cases);
        $this->assertContains(StockReservationType::SYSTEM, $cases);
    }

    public function testFromValue(): void
    {
        $this->assertEquals(StockReservationType::ORDER, StockReservationType::from('order'));
        $this->assertEquals(StockReservationType::PROMOTION, StockReservationType::from('promotion'));
        $this->assertEquals(StockReservationType::VIP, StockReservationType::from('vip'));
        $this->assertEquals(StockReservationType::SYSTEM, StockReservationType::from('system'));
    }

    protected function createEnum(): object
    {
        return StockReservationType::ORDER;
    }

    public function testToArray(): void
    {
        $expected = [
            'value' => 'order',
            'label' => '订单预定',
        ];
        $this->assertEquals($expected, StockReservationType::ORDER->toArray());
    }
}
