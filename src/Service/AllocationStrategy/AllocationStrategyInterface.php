<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service\AllocationStrategy;

use Tourze\StockManageBundle\Entity\StockBatch;

interface AllocationStrategyInterface
{
    /**
     * 根据策略对批次进行排序.
     *
     * @param array<StockBatch> $batches
     *
     * @return array<StockBatch>
     */
    public function sortBatches(array $batches): array;

    /**
     * 获取策略名称.
     */
    public function getName(): string;
}
