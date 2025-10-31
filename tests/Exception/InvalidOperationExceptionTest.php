<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;

/**
 * @internal
 */
#[CoversClass(InvalidOperationException::class)]
class InvalidOperationExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new InvalidOperationException('Test message');

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidOperationException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new InvalidOperationException();

        $this->assertInstanceOf(InvalidOperationException::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithEmptyMessage(): void
    {
        $exception = new InvalidOperationException('');

        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionWithLongMessage(): void
    {
        $longMessage = str_repeat('This operation is invalid. ', 100);
        $exception = new InvalidOperationException($longMessage);

        $this->assertEquals($longMessage, $exception->getMessage());
    }
}
