<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;
use Tourze\StockManageBundle\Enum\StockChange;

/**
 * @internal
 */
#[CoversClass(StockChange::class)]
class StockChangeTest extends AbstractEnumTestCase
{
    public function testGetLabel(): void
    {
        self::assertSame('锁定库存', StockChange::LOCK->getLabel());
        self::assertSame('解锁库存', StockChange::UNLOCK->getLabel());
        self::assertSame('扣除库存', StockChange::DEDUCT->getLabel());
        self::assertSame('返还库存', StockChange::RETURN->getLabel());
        self::assertSame('入库', StockChange::INBOUND->getLabel());
        self::assertSame('出库', StockChange::OUTBOUND->getLabel());
        self::assertSame('调整库存', StockChange::ADJUSTMENT->getLabel());
        self::assertSame('入库操作', StockChange::PUT->getLabel());
        self::assertSame('预留库存', StockChange::RESERVED->getLabel());
        self::assertSame('释放预留', StockChange::RESERVED_RELEASE->getLabel());
    }

    public function testGetDescription(): void
    {
        self::assertSame('锁定库存', StockChange::LOCK->getDescription());
        self::assertSame('解锁库存', StockChange::UNLOCK->getDescription());
        self::assertSame('扣除库存', StockChange::DEDUCT->getDescription());
    }

    public function testIsDecrease(): void
    {
        self::assertTrue(StockChange::LOCK->isDecrease());
        self::assertTrue(StockChange::DEDUCT->isDecrease());
        self::assertTrue(StockChange::OUTBOUND->isDecrease());
        self::assertTrue(StockChange::RESERVED->isDecrease());

        self::assertFalse(StockChange::UNLOCK->isDecrease());
        self::assertFalse(StockChange::RETURN->isDecrease());
        self::assertFalse(StockChange::INBOUND->isDecrease());
    }

    public function testIsIncrease(): void
    {
        self::assertTrue(StockChange::UNLOCK->isIncrease());
        self::assertTrue(StockChange::RETURN->isIncrease());
        self::assertTrue(StockChange::INBOUND->isIncrease());
        self::assertTrue(StockChange::PUT->isIncrease());
        self::assertTrue(StockChange::RESERVED_RELEASE->isIncrease());

        self::assertFalse(StockChange::LOCK->isIncrease());
        self::assertFalse(StockChange::DEDUCT->isIncrease());
        self::assertFalse(StockChange::OUTBOUND->isIncrease());
    }

    public function testGenOptions(): void
    {
        $selectItems = StockChange::genOptions();

        self::assertIsArray($selectItems);
        self::assertCount(10, $selectItems);
        self::assertArrayHasKey('value', $selectItems[0]);
        self::assertArrayHasKey('label', $selectItems[0]);
    }

    public function testToArray(): void
    {
        $array = StockChange::LOCK->toArray();

        self::assertIsArray($array);
        self::assertArrayHasKey('label', $array);
        self::assertArrayHasKey('value', $array);
        self::assertSame('锁定库存', $array['label']);
        self::assertSame('lock', $array['value']);
    }
}
