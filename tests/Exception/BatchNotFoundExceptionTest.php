<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\BatchNotFoundException;

/**
 * @internal
 */
#[CoversClass(BatchNotFoundException::class)]
class BatchNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromRuntimeException(): void
    {
        $exception = new BatchNotFoundException('Test message');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new BatchNotFoundException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithId(): void
    {
        $id = 'batch-123';
        $exception = BatchNotFoundException::withId($id);

        $this->assertInstanceOf(BatchNotFoundException::class, $exception);
        $this->assertEquals('Batch with ID batch-123 not found', $exception->getMessage());
    }

    public function testWithBatchNo(): void
    {
        $batchNo = 'BN20241201';
        $exception = BatchNotFoundException::withBatchNo($batchNo);

        $this->assertInstanceOf(BatchNotFoundException::class, $exception);
        $this->assertEquals('Batch with number BN20241201 not found', $exception->getMessage());
    }

    public function testWithEmptyId(): void
    {
        $exception = BatchNotFoundException::withId('');

        $this->assertEquals('Batch with ID  not found', $exception->getMessage());
    }

    public function testWithEmptyBatchNo(): void
    {
        $exception = BatchNotFoundException::withBatchNo('');

        $this->assertEquals('Batch with number  not found', $exception->getMessage());
    }
}
