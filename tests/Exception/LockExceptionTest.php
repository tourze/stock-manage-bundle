<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\LockException;

/**
 * @internal
 */
#[CoversClass(LockException::class)]
class LockExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new LockException('Test message');

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new LockException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new LockException();

        $this->assertInstanceOf(LockException::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithEmptyMessage(): void
    {
        $exception = new LockException('');

        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionWithLockFailureMessage(): void
    {
        $message = 'Failed to acquire lock on stock item';
        $exception = new LockException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithUnlockFailureMessage(): void
    {
        $message = 'Failed to release lock on stock item';
        $exception = new LockException($message);

        $this->assertEquals($message, $exception->getMessage());
    }
}
