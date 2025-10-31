<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

/**
 * @internal
 */
interface MockStockBatchRepository
{
    public function setBatchExists(bool $exists): void;

    public function expectsOnce(string $method, string $with, bool $willReturn): void;
}
