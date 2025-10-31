<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;

/**
 * @extends ServiceEntityRepository<StockAlert>
 */
#[AsRepository(entityClass: StockAlert::class)]
class StockAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockAlert::class);
    }

    /**
     * 保存库存预警实体.
     */
    public function save(StockAlert $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除库存预警实体.
     */
    public function remove(StockAlert $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据SKU查找预警.
     *
     * @return StockAlert[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 查找活跃的预警.
     *
     * @return StockAlert[]
     */
    public function findActiveAlerts(): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.status = :status')
            ->setParameter('status', StockAlertStatus::ACTIVE)
            ->orderBy('sa.severity', 'DESC')
            ->addOrderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 根据严重程度查找预警.
     *
     * @return StockAlert[]
     */
    public function findBySeverity(StockAlertSeverity $severity): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.severity = :severity')
            ->setParameter('severity', $severity)
            ->orderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 根据预警类型查找预警.
     *
     * @return StockAlert[]
     */
    public function findByType(StockAlertType $alertType): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.alertType = :alertType')
            ->setParameter('alertType', $alertType)
            ->orderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 根据位置查找预警.
     *
     * @return StockAlert[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 查找严重级别以上的活跃预警.
     *
     * @return StockAlert[]
     */
    public function findCriticalAndHighSeverityActiveAlerts(): array
    {
        $result = $this->createQueryBuilder('sa')
            ->where('sa.status = :status')
            ->andWhere('sa.severity IN (:severities)')
            ->setParameter('status', StockAlertStatus::ACTIVE)
            ->setParameter('severities', [StockAlertSeverity::HIGH, StockAlertSeverity::CRITICAL])
            ->orderBy('sa.severity', 'DESC')
            ->addOrderBy('sa.triggeredAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 获取预警统计信息.
     *
     * @return array<string, mixed>
     */
    public function getAlertStatistics(): array
    {
        $qb = $this->createQueryBuilder('sa')
            ->select('sa.status, sa.severity, COUNT(sa.id) as count')
            ->groupBy('sa.status', 'sa.severity')
        ;

        $results = $qb->getQuery()->getResult();
        assert(is_array($results));

        $stats = [
            'total' => 0,
            'by_status' => [],
            'by_severity' => [],
        ];

        foreach ($results as $result) {
            assert(is_array($result));
            assert(isset($result['status']));
            assert($result['status'] instanceof \BackedEnum);
            assert(isset($result['severity']));
            assert($result['severity'] instanceof \BackedEnum);
            assert(isset($result['count']));

            $status = $result['status']->value;
            $severity = $result['severity']->value;
            $countValue = $result['count'];
            assert(is_numeric($countValue));
            $count = (int) $countValue;

            $stats['total'] += $count;
            $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + $count;
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + $count;
        }

        return $stats;
    }

    /**
     * 查找需要清理的过期预警.
     *
     * @return StockAlert[]
     */
    public function findExpiredAlerts(int $daysOld = 30): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $daysOld));

        $result = $this->createQueryBuilder('sa')
            ->where('sa.status IN (:statuses)')
            ->andWhere('sa.resolvedAt IS NOT NULL AND sa.resolvedAt < :cutoffDate')
            ->setParameter('statuses', [StockAlertStatus::RESOLVED, StockAlertStatus::DISMISSED])
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockAlert> $result */
        return $result;
    }

    /**
     * 获取SKU的活跃预警计数.
     */
    public function getActiveAlertCountBySku(SKU $sku): int
    {
        $count = $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->where('sa.sku = :sku')
            ->andWhere('sa.status = :status')
            ->setParameter('sku', $sku)
            ->setParameter('status', StockAlertStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($count ?? 0);
    }

    /**
     * 检查是否存在重复的活跃预警.
     */
    public function hasDuplicateActiveAlert(SKU $sku, StockAlertType $type): bool
    {
        $count = $this->createQueryBuilder('sa')
            ->select('COUNT(sa.id)')
            ->where('sa.sku = :sku')
            ->andWhere('sa.alertType = :type')
            ->andWhere('sa.status = :status')
            ->setParameter('sku', $sku)
            ->setParameter('type', $type)
            ->setParameter('status', StockAlertStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
