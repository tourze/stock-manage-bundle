<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\StockManageBundle\Entity\StockAdjustment;

/**
 * @extends ServiceEntityRepository<StockAdjustment>
 */
#[AsRepository(entityClass: StockAdjustment::class)]
class StockAdjustmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockAdjustment::class);
    }

    /**
     * 保存库存调整实体.
     */
    public function save(StockAdjustment $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除库存调整实体.
     */
    public function remove(StockAdjustment $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据位置查找库存调整.
     *
     * @return StockAdjustment[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('a')
            ->where('a.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('a.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAdjustment> $result */
        return $result;
    }

    /**
     * 查找待处理的库存调整.
     *
     * @return StockAdjustment[]
     */
    public function findPending(): array
    {
        $result = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('a.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAdjustment> $result */
        return $result;
    }

    /**
     * 查找指定类型的库存调整.
     *
     * @return StockAdjustment[]
     */
    public function findByType(string $type): array
    {
        $result = $this->createQueryBuilder('a')
            ->where('a.type = :type')
            ->setParameter('type', $type)
            ->orderBy('a.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAdjustment> $result */
        return $result;
    }

    /**
     * 获取指定时间范围内的库存调整.
     *
     * @return StockAdjustment[]
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('a')
            ->where('a.createTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAdjustment> $result */
        return $result;
    }

    /**
     * 获取库存调整统计信息.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function getAdjustmentStats(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id) as total_count')
            ->addSelect('SUM(a.totalAdjusted) as total_adjusted')
            ->addSelect('SUM(a.costImpact) as total_cost_impact')
        ;

        if (isset($criteria['location_id'])) {
            $qb->andWhere('a.locationId = :locationId')
                ->setParameter('locationId', $criteria['location_id'])
            ;
        }

        if (isset($criteria['type'])) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $criteria['type'])
            ;
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $criteria['status'])
            ;
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('a.createTime >= :startDate')
                ->setParameter('startDate', $criteria['start_date'])
            ;
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('a.createTime <= :endDate')
                ->setParameter('endDate', $criteria['end_date'])
            ;
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        $result = is_array($result) ? $result : [];

        $totalCount = $result['total_count'] ?? 0;
        $totalAdjusted = $result['total_adjusted'] ?? 0;
        $totalCostImpact = $result['total_cost_impact'] ?? 0.0;

        assert(is_numeric($totalCount) && is_numeric($totalAdjusted) && is_numeric($totalCostImpact));

        return [
            'total_count' => (int) $totalCount,
            'total_adjusted' => (int) $totalAdjusted,
            'total_cost_impact' => (float) $totalCostImpact,
        ];
    }
}
