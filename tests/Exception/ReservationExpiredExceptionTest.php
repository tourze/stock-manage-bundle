<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\StockManageBundle\Exception\ReservationExpiredException;

/**
 * @internal
 */
#[CoversClass(ReservationExpiredException::class)]
class ReservationExpiredExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritsFromException(): void
    {
        $exception = new ReservationExpiredException('Test message');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ReservationExpiredException('Test message', 123, $previous);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithId(): void
    {
        $id = 'reservation-123';
        $exception = ReservationExpiredException::withId($id);

        $this->assertInstanceOf(ReservationExpiredException::class, $exception);
        $this->assertEquals('Reservation reservation-123 has expired', $exception->getMessage());
    }

    public function testWithEmptyId(): void
    {
        $exception = ReservationExpiredException::withId('');

        $this->assertEquals('Reservation  has expired', $exception->getMessage());
    }

    public function testWithNumericId(): void
    {
        $id = '12345';
        $exception = ReservationExpiredException::withId($id);

        $this->assertEquals('Reservation 12345 has expired', $exception->getMessage());
    }

    public function testWithUuidId(): void
    {
        $id = '550e8400-e29b-41d4-a716-446655440000';
        $exception = ReservationExpiredException::withId($id);

        $this->assertEquals('Reservation 550e8400-e29b-41d4-a716-446655440000 has expired', $exception->getMessage());
    }

    public function testExceptionWithoutMessage(): void
    {
        $exception = new ReservationExpiredException();

        $this->assertEquals('', $exception->getMessage());
    }
}
