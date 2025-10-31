<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\StockManageBundle\DTO\StockCheckResponse;

/**
 * @internal
 */
#[CoversClass(StockCheckResponse::class)]
class StockCheckResponseTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: true,
            currentStock: 10,
            requestedQuantity: 5,
            message: '库存充足'
        );

        $this->assertSame(1, $response->productId);
        $this->assertSame(2, $response->skuId);
        $this->assertTrue($response->available);
        $this->assertSame(10, $response->currentStock);
        $this->assertSame(5, $response->requestedQuantity);
        $this->assertSame('库存充足', $response->message);
    }

    public function testToArray(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: true,
            currentStock: 10,
            requestedQuantity: 5,
            message: '库存充足'
        );

        $array = $response->toArray();

        $expected = [
            'productId' => 1,
            'skuId' => 2,
            'available' => true,
            'currentStock' => 10,
            'requestedQuantity' => 5,
            'message' => '库存充足',
            'shortage' => 0,
        ];

        $this->assertSame($expected, $array);
    }

    public function testGetShortageWhenAvailable(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: true,
            currentStock: 10,
            requestedQuantity: 5
        );

        $this->assertSame(0, $response->getShortage());
    }

    public function testGetShortageWhenNotAvailable(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: false,
            currentStock: 3,
            requestedQuantity: 5
        );

        $this->assertSame(2, $response->getShortage());
    }

    public function testIsAvailable(): void
    {
        $availableResponse = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: true,
            currentStock: 10,
            requestedQuantity: 5
        );

        $unavailableResponse = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: false,
            currentStock: 3,
            requestedQuantity: 5
        );

        $this->assertTrue($availableResponse->isAvailable());
        $this->assertFalse($unavailableResponse->isAvailable());
    }

    public function testGetAvailabilityMessageWhenAvailable(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: true,
            currentStock: 10,
            requestedQuantity: 5
        );

        $this->assertSame('库存充足', $response->getAvailabilityMessage());
    }

    public function testGetAvailabilityMessageWhenNoStock(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: false,
            currentStock: 0,
            requestedQuantity: 5
        );

        $this->assertSame('库存不足，当前库存为0', $response->getAvailabilityMessage());
    }

    public function testGetAvailabilityMessageWhenInsufficientStock(): void
    {
        $response = new StockCheckResponse(
            productId: 1,
            skuId: 2,
            available: false,
            currentStock: 3,
            requestedQuantity: 5
        );

        $expected = '库存不足，需要5个，当前仅有3个，还缺2个';
        $this->assertSame($expected, $response->getAvailabilityMessage());
    }
}
