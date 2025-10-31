<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\InvalidStatusException;

/**
 * @internal
 */
#[CoversClass(InvalidStatusException::class)]
class InvalidStatusExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new InvalidStatusException('Test message');

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidStatusException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testCreate(): void
    {
        $status = 'invalid_status';
        $exception = InvalidStatusException::create($status);

        $this->assertInstanceOf(InvalidStatusException::class, $exception);
        $this->assertEquals('无效的状态: invalid_status', $exception->getMessage());
    }

    public function testCreateWithEmptyStatus(): void
    {
        $exception = InvalidStatusException::create('');

        $this->assertEquals('无效的状态: ', $exception->getMessage());
    }

    public function testCreateWithNumericStatus(): void
    {
        $status = '404';
        $exception = InvalidStatusException::create($status);

        $this->assertEquals('无效的状态: 404', $exception->getMessage());
    }

    public function testCreateWithSpecialCharactersStatus(): void
    {
        $status = 'status-with_special@chars';
        $exception = InvalidStatusException::create($status);

        $this->assertEquals('无效的状态: status-with_special@chars', $exception->getMessage());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new InvalidStatusException();

        $this->assertEquals('', $exception->getMessage());
    }
}
