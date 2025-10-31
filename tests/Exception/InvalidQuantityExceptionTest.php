<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\AbstractStockException;
use Tourze\StockManageBundle\Exception\InvalidQuantityException;

/**
 * @internal
 */
#[CoversClass(InvalidQuantityException::class)]
class InvalidQuantityExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromStockException(): void
    {
        $exception = new InvalidQuantityException(5);

        $this->assertInstanceOf(AbstractStockException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('无效的数量: 5', $exception->getMessage());
    }

    public function testExceptionWithNegativeQuantity(): void
    {
        $quantity = -10;
        $exception = new InvalidQuantityException($quantity);

        $this->assertEquals('无效的数量: -10', $exception->getMessage());
    }

    public function testExceptionWithZeroQuantity(): void
    {
        $quantity = 0;
        $exception = new InvalidQuantityException($quantity);

        $this->assertEquals('无效的数量: 0', $exception->getMessage());
    }

    public function testExceptionWithPositiveQuantity(): void
    {
        $quantity = 100;
        $exception = new InvalidQuantityException($quantity);

        $this->assertEquals('无效的数量: 100', $exception->getMessage());
    }

    public function testCreate(): void
    {
        $quantity = -5;
        $exception = InvalidQuantityException::create($quantity);

        $this->assertInstanceOf(InvalidQuantityException::class, $exception);
        $this->assertEquals('无效的数量: -5', $exception->getMessage());
    }

    public function testCreateWithZero(): void
    {
        $exception = InvalidQuantityException::create(0);

        $this->assertEquals('无效的数量: 0', $exception->getMessage());
    }

    public function testCreateWithLargeNumber(): void
    {
        $quantity = 999999;
        $exception = InvalidQuantityException::create($quantity);

        $this->assertEquals('无效的数量: 999999', $exception->getMessage());
    }

    public function testExceptionCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidQuantityException(-1);

        // InvalidQuantityException 构造函数只接受 quantity 参数
        // 所以我们测试默认的 code 和 previous
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }
}
