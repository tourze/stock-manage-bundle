<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\TransferException;

/**
 * @internal
 */
#[CoversClass(TransferException::class)]
class TransferExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new TransferException('Test message');

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new TransferException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new TransferException();

        $this->assertInstanceOf(TransferException::class, $exception);
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithEmptyMessage(): void
    {
        $exception = new TransferException('');

        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionWithTransferFailureMessage(): void
    {
        $message = 'Transfer failed: source warehouse not found';
        $exception = new TransferException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithInventoryTransferMessage(): void
    {
        $message = 'Inventory transfer failed: insufficient stock in source location';
        $exception = new TransferException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithDetailedTransferError(): void
    {
        $message = 'Transfer operation failed: cannot move 100 units from warehouse A to warehouse B due to capacity restrictions';
        $exception = new TransferException($message);

        $this->assertEquals($message, $exception->getMessage());
    }
}
