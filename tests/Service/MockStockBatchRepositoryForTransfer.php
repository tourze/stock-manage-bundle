<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @internal
 */
interface MockStockBatchRepositoryForTransfer
{
    public function setBatch(int $id, StockBatch $batch): void;
}
