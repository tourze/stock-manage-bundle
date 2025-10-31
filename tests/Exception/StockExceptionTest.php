<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\StockException;

/**
 * @internal
 */
#[CoversClass(StockException::class)]
class StockExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromRuntimeException(): void
    {
        $exception = new StockException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new StockException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new StockException();

        $this->assertInstanceOf(StockException::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithEmptyMessage(): void
    {
        $exception = new StockException('');

        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionIsBaseForOtherStockExceptions(): void
    {
        $exception = new StockException('Base stock exception');

        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithDetailedMessage(): void
    {
        $message = 'Stock operation failed: unable to process request due to insufficient inventory';
        $exception = new StockException($message);

        $this->assertEquals($message, $exception->getMessage());
    }
}
