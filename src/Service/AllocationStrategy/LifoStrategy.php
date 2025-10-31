<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service\AllocationStrategy;

use Tourze\StockManageBundle\Entity\StockBatch;

class LifoStrategy implements AllocationStrategyInterface
{
    /**
     * @param array<StockBatch> $batches
     *
     * @return array<StockBatch>
     */
    public function sortBatches(array $batches): array
    {
        // 后进先出：按创建时间降序排序
        usort($batches, function (StockBatch $a, StockBatch $b) {
            return $b->getCreateTime() <=> $a->getCreateTime();
        });

        return $batches;
    }

    public function getName(): string
    {
        return 'lifo';
    }
}
