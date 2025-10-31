<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Tourze\StockManageBundle\Service\OutboundServiceInterface;

/**
 * @internal
 */
interface MockOutboundService extends OutboundServiceInterface
{
    /** @return array<string, array<array<string, mixed>>> */
    public function getCalls(): array;
}
