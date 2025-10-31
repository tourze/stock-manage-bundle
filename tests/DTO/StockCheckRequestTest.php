<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\StockManageBundle\DTO\StockCheckRequest;

/**
 * @internal
 */
#[CoversClass(StockCheckRequest::class)]
class StockCheckRequestTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $request = new StockCheckRequest(1, 2, 5);

        $this->assertSame(1, $request->productId);
        $this->assertSame(2, $request->skuId);
        $this->assertSame(5, $request->quantity);
    }

    public function testDefaultConstructor(): void
    {
        $request = new StockCheckRequest();

        $this->assertSame(0, $request->productId);
        $this->assertSame(0, $request->skuId);
        $this->assertSame(0, $request->quantity);
    }

    public function testToArray(): void
    {
        $request = new StockCheckRequest(1, 2, 5);
        $array = $request->toArray();

        $expected = [
            'productId' => 1,
            'skuId' => 2,
            'quantity' => 5,
        ];

        $this->assertSame($expected, $array);
    }

    public function testIsValidWithValidData(): void
    {
        $request = new StockCheckRequest(1, 2, 5);

        $this->assertTrue($request->isValid());
    }

    public function testIsValidWithInvalidData(): void
    {
        $request1 = new StockCheckRequest(0, 2, 5);
        $request2 = new StockCheckRequest(1, 0, 5);
        $request3 = new StockCheckRequest(1, 2, 0);

        $this->assertFalse($request1->isValid());
        $this->assertFalse($request2->isValid());
        $this->assertFalse($request3->isValid());
    }
}
