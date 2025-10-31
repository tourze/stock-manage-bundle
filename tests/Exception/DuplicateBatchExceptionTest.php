<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;

/**
 * @internal
 */
#[CoversClass(DuplicateBatchException::class)]
class DuplicateBatchExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromRuntimeException(): void
    {
        $exception = new DuplicateBatchException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new DuplicateBatchException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithBatchNo(): void
    {
        $batchNo = 'BN20241201';
        $exception = DuplicateBatchException::withBatchNo($batchNo);

        $this->assertInstanceOf(DuplicateBatchException::class, $exception);
        $this->assertEquals('Batch with number BN20241201 already exists', $exception->getMessage());
    }

    public function testWithEmptyBatchNo(): void
    {
        $exception = DuplicateBatchException::withBatchNo('');

        $this->assertEquals('Batch with number  already exists', $exception->getMessage());
    }

    public function testWithSpecialCharactersBatchNo(): void
    {
        $batchNo = 'BN-2024/12/01';
        $exception = DuplicateBatchException::withBatchNo($batchNo);

        $this->assertEquals('Batch with number BN-2024/12/01 already exists', $exception->getMessage());
    }
}
