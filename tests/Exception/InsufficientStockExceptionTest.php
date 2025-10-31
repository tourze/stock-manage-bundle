<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\InsufficientStockException;

/**
 * @internal
 */
#[CoversClass(InsufficientStockException::class)]
class InsufficientStockExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new InsufficientStockException('Test message');

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InsufficientStockException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCreate(): void
    {
        $spuId = 'SPU123';
        $required = 100;
        $available = 50;

        $exception = InsufficientStockException::create($spuId, $required, $available);

        $this->assertInstanceOf(InsufficientStockException::class, $exception);
        $this->assertEquals(
            'Insufficient stock for SPU SPU123: required 100, available 50',
            $exception->getMessage()
        );
    }

    public function testCreateBySku(): void
    {
        $sku = 'SKU456';
        $required = 75;
        $available = 25;

        $exception = InsufficientStockException::createBySku($sku, $required, $available);

        $this->assertInstanceOf(InsufficientStockException::class, $exception);
        $this->assertEquals(
            'Insufficient stock for SKU SKU456: required 75, available 25',
            $exception->getMessage()
        );
    }

    public function testCreateWithZeroRequired(): void
    {
        $exception = InsufficientStockException::create('SPU123', 0, 10);

        $this->assertEquals(
            'Insufficient stock for SPU SPU123: required 0, available 10',
            $exception->getMessage()
        );
    }

    public function testCreateBySkuWithZeroAvailable(): void
    {
        $exception = InsufficientStockException::createBySku('SKU456', 10, 0);

        $this->assertEquals(
            'Insufficient stock for SKU SKU456: required 10, available 0',
            $exception->getMessage()
        );
    }

    public function testCreateWithEmptySpuId(): void
    {
        $exception = InsufficientStockException::create('', 10, 5);

        $this->assertEquals(
            'Insufficient stock for SPU : required 10, available 5',
            $exception->getMessage()
        );
    }

    public function testCreateBySkuWithEmptySku(): void
    {
        $exception = InsufficientStockException::createBySku('', 20, 15);

        $this->assertEquals(
            'Insufficient stock for SKU : required 20, available 15',
            $exception->getMessage()
        );
    }
}
