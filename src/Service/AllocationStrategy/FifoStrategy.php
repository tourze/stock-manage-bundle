<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service\AllocationStrategy;

use Tourze\StockManageBundle\Entity\StockBatch;

final class FifoStrategy implements AllocationStrategyInterface
{
    /**
     * @param array<StockBatch> $batches
     *
     * @return array<StockBatch>
     */
    public function sortBatches(array $batches): array
    {
        // 先进先出：按创建时间升序排序
        usort($batches, function (StockBatch $a, StockBatch $b) {
            return $a->getCreateTime() <=> $b->getCreateTime();
        });

        return $batches;
    }

    public function getName(): string
    {
        return 'fifo';
    }
}
