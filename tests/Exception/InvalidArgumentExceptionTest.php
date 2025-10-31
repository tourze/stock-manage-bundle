<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;

/**
 * @internal
 */
#[CoversClass(InvalidArgumentException::class)]
class InvalidArgumentExceptionTest extends AbstractExceptionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 测试设置逻辑
    }

    public function testExceptionInheritsFromStandardInvalidArgumentException(): void
    {
        $exception = new InvalidArgumentException('Test message');

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidArgumentException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
