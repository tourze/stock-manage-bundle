<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service\AllocationStrategy;

use Tourze\StockManageBundle\Entity\StockBatch;

class FefoStrategy implements AllocationStrategyInterface
{
    /**
     * @param array<StockBatch> $batches
     *
     * @return array<StockBatch>
     */
    public function sortBatches(array $batches): array
    {
        // 先过期先出：按过期日期升序排序，没有过期日期的排在最后
        usort($batches, function (StockBatch $a, StockBatch $b) {
            $aExpiry = $a->getExpiryDate();
            $bExpiry = $b->getExpiryDate();

            if (null === $aExpiry && null === $bExpiry) {
                return 0;
            }

            if (null === $aExpiry) {
                return 1;
            }

            if (null === $bExpiry) {
                return -1;
            }

            return $aExpiry <=> $bExpiry;
        });

        return $batches;
    }

    public function getName(): string
    {
        return 'fefo';
    }
}
