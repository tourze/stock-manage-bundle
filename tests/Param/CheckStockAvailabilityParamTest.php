<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Param;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\StockManageBundle\Param\CheckStockAvailabilityParam;

/**
 * @internal
 */
#[CoversClass(CheckStockAvailabilityParam::class)]
final class CheckStockAvailabilityParamTest extends TestCase
{
    public function testParamCanBeConstructed(): void
    {
        $items = [
            ['productId' => 1, 'skuId' => 101, 'quantity' => 5],
            ['productId' => 2, 'skuId' => 102, 'quantity' => 10],
        ];

        $param = new CheckStockAvailabilityParam(items: $items);

        $this->assertInstanceOf(RpcParamInterface::class, $param);
        $this->assertSame($items, $param->items);
    }

    public function testParamCanBeConstructedWithEmptyItems(): void
    {
        $param = new CheckStockAvailabilityParam();

        $this->assertInstanceOf(RpcParamInterface::class, $param);
        $this->assertSame([], $param->items);
    }

    public function testParamIsReadonly(): void
    {
        $items = [
            ['productId' => 3, 'skuId' => 103, 'quantity' => 15],
        ];

        $param = new CheckStockAvailabilityParam(items: $items);

        $this->assertSame($items, $param->items);
    }
}
