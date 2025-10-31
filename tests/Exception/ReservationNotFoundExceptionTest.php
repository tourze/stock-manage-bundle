<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\ReservationNotFoundException;

/**
 * @internal
 */
#[CoversClass(ReservationNotFoundException::class)]
class ReservationNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromException(): void
    {
        $exception = new ReservationNotFoundException('Test message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ReservationNotFoundException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithId(): void
    {
        $id = 'reservation-123';
        $exception = ReservationNotFoundException::withId($id);

        $this->assertInstanceOf(ReservationNotFoundException::class, $exception);
        $this->assertEquals('Reservation reservation-123 not found', $exception->getMessage());
    }

    public function testWithEmptyId(): void
    {
        $exception = ReservationNotFoundException::withId('');

        $this->assertEquals('Reservation  not found', $exception->getMessage());
    }

    public function testWithNumericId(): void
    {
        $id = '12345';
        $exception = ReservationNotFoundException::withId($id);

        $this->assertEquals('Reservation 12345 not found', $exception->getMessage());
    }

    public function testWithUuidId(): void
    {
        $id = '550e8400-e29b-41d4-a716-446655440000';
        $exception = ReservationNotFoundException::withId($id);

        $this->assertEquals('Reservation 550e8400-e29b-41d4-a716-446655440000 not found', $exception->getMessage());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new ReservationNotFoundException();

        $this->assertEquals('', $exception->getMessage());
    }
}
