<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\StockManageBundle\Enum\StockReservationStatus;

/**
 * @internal
 */
#[CoversClass(StockReservationStatus::class)]
class StockReservationStatusTest extends AbstractEnumTestCase
{
    // AbstractEnumTestCase 已经提供了所有基础的枚举测试
    // 包括测试枚举值、标签、from/tryFrom 方法等

    public function testToArrayShouldReturnCorrectArray(): void
    {
        $result = StockReservationStatus::PENDING->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('label', $result);
        $this->assertEquals('pending', $result['value']);
        $this->assertEquals('待确认', $result['label']);
    }
}
