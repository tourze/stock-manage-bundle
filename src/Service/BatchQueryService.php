<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 批次查询服务实现.
 */
class BatchQueryService implements BatchQueryServiceInterface
{
    public function __construct(
        private readonly StockBatchRepository $batchRepository,
    ) {
    }

    public function getAllBatches(): array
    {
        return $this->batchRepository->findAll();
    }

    public function findBatchByNo(string $batchNo): ?StockBatch
    {
        return $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
    }

    /**
     * @return StockBatch[]
     */
    public function findBatchesBySkuId(string $skuId): array
    {
        $result = $this->batchRepository->createQueryBuilder('b')
            ->join('b.sku', 's')
            ->where('s.id = :skuId')
            ->setParameter('skuId', $skuId)
            ->orderBy('b.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        // 确保返回的都是StockBatch实例
        return array_map(function ($item) {
            if (!$item instanceof StockBatch) {
                throw new \RuntimeException('Query returned invalid result type');
            }

            return $item;
        }, $result);
    }
}
