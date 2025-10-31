<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Tourze\StockManageBundle\Service\InboundServiceInterface;

/**
 * @internal
 */
interface MockInboundService extends InboundServiceInterface
{
    /** @return array<string, array<array<string, mixed>>> */
    public function getCalls(): array;
}
