<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Exception;

class ReservationNotFoundException extends \Exception
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Reservation %s not found', $id));
    }
}
