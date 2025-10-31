<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;

/**
 * @extends ServiceEntityRepository<StockOutbound>
 */
#[AsRepository(entityClass: StockOutbound::class)]
class StockOutboundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockOutbound::class);
    }

    /**
     * 保存出库记录实体.
     */
    public function save(StockOutbound $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除出库记录实体.
     */
    public function remove(StockOutbound $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据业务单号查找出库记录.
     */
    public function findByReferenceNo(string $referenceNo): ?StockOutbound
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.referenceNo = :referenceNo')
            ->setParameter('referenceNo', $referenceNo)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        assert(null === $result || $result instanceof StockOutbound);

        return $result;
    }

    /**
     * 根据SKU查找出库记录.
     *
     * @return StockOutbound[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 根据出库类型查找记录.
     *
     * @return StockOutbound[]
     */
    public function findByType(StockOutboundType $type): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.type = :type')
            ->setParameter('type', $type)
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 根据操作人查找出库记录.
     *
     * @return StockOutbound[]
     */
    public function findByOperator(string $operator): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.operator = :operator')
            ->setParameter('operator', $operator)
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 根据位置查找出库记录.
     *
     * @return StockOutbound[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 根据时间范围查找出库记录.
     *
     * @return StockOutbound[]
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.createTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 获取出库统计信息.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function getOutboundStats(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('so')
            ->select('COUNT(so.id) as total_records')
            ->addSelect('SUM(so.totalQuantity) as total_quantity')
            ->addSelect('SUM(so.totalCost) as total_cost')
        ;

        if (isset($criteria['type'])) {
            $qb->andWhere('so.type = :type')
                ->setParameter('type', $criteria['type'])
            ;
        }

        if (isset($criteria['location_id'])) {
            $qb->andWhere('so.locationId = :locationId')
                ->setParameter('locationId', $criteria['location_id'])
            ;
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('so.createTime >= :startDate')
                ->setParameter('startDate', $criteria['start_date'])
            ;
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('so.createTime <= :endDate')
                ->setParameter('endDate', $criteria['end_date'])
            ;
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        $result = is_array($result) ? $result : [];

        $totalRecords = $result['total_records'] ?? 0;
        $totalQuantity = $result['total_quantity'] ?? 0;
        $totalCost = $result['total_cost'] ?? 0.0;

        assert(is_numeric($totalRecords) && is_numeric($totalQuantity) && is_numeric($totalCost));

        return [
            'total_records' => (int) $totalRecords,
            'total_quantity' => (int) $totalQuantity,
            'total_cost' => (float) $totalCost,
        ];
    }

    /**
     * 获取最近的出库记录.
     *
     * @return StockOutbound[]
     */
    public function findRecentOutbounds(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('so')
            ->orderBy('so.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 根据业务单号前缀查找出库记录.
     *
     * @return StockOutbound[]
     */
    public function findByReferenceNoPrefix(string $prefix): array
    {
        $result = $this->createQueryBuilder('so')
            ->where('so.referenceNo LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('so.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockOutbound> $result */
        return $result;
    }

    /**
     * 获取指定SKU的总出库数量.
     */
    public function getTotalOutboundQuantityBySku(SKU $sku): int
    {
        $result = $this->createQueryBuilder('so')
            ->select('SUM(so.totalQuantity)')
            ->where('so.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 获取指定时间段内的出库类型统计.
     *
     * @return array<string, array{count: int, total_quantity: int, total_cost: float}>
     */
    public function getOutboundTypeStats(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $results = $this->createQueryBuilder('so')
            ->select('so.type, COUNT(so.id) as count, SUM(so.totalQuantity) as total_quantity, SUM(so.totalCost) as total_cost')
            ->where('so.createTime BETWEEN :startDate AND :endDate')
            ->groupBy('so.type')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult()
        ;
        assert(is_array($results));

        $stats = [];
        foreach ($results as $result) {
            assert(is_array($result));
            assert(isset($result['type']));
            assert($result['type'] instanceof \BackedEnum);
            assert(isset($result['count']));
            assert(isset($result['total_quantity']));
            assert(isset($result['total_cost']));

            $typeValue = $result['type']->value;
            assert(is_string($typeValue));
            $count = $result['count'];
            $totalQuantity = $result['total_quantity'];
            $totalCost = $result['total_cost'];

            assert(is_numeric($count) && is_numeric($totalQuantity) && is_numeric($totalCost));

            $stats[$typeValue] = [
                'count' => (int) $count,
                'total_quantity' => (int) $totalQuantity,
                'total_cost' => (float) $totalCost,
            ];
        }

        return $stats;
    }

    /**
     * 获取指定SKU的总出库成本.
     */
    public function getTotalOutboundCostBySku(SKU $sku): float
    {
        $result = $this->createQueryBuilder('so')
            ->select('SUM(so.totalCost)')
            ->where('so.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (float) ($result ?? 0.0);
    }
}
