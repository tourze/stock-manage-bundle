<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

class ReservationExpiredException extends \Exception
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Reservation %s has expired', $id));
    }
}
