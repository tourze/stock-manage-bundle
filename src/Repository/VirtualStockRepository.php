<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\VirtualStock;

/**
 * @extends ServiceEntityRepository<VirtualStock>
 */
#[AsRepository(entityClass: VirtualStock::class)]
class VirtualStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VirtualStock::class);
    }

    /**
     * 保存虚拟库存实体.
     */
    public function save(VirtualStock $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除虚拟库存实体.
     */
    public function remove(VirtualStock $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据SKU查找虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('vs.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 根据虚拟库存类型查找.
     *
     * @return VirtualStock[]
     */
    public function findByVirtualType(string $virtualType): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.virtualType = :virtualType')
            ->setParameter('virtualType', $virtualType)
            ->orderBy('vs.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 查找活跃的虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findActiveStocks(): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.status = :status')
            ->andWhere('vs.quantity > 0')
            ->setParameter('status', 'active')
            ->orderBy('vs.expectedDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 根据状态查找虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findByStatus(string $status): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.status = :status')
            ->setParameter('status', $status)
            ->orderBy('vs.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 根据业务ID查找虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findByBusinessId(string $businessId): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.businessId = :businessId')
            ->setParameter('businessId', $businessId)
            ->orderBy('vs.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 根据位置查找虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('vs.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 查找即将到期的虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findExpiringSoon(int $days = 7): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('+%d days', $days));

        $result = $this->createQueryBuilder('vs')
            ->where('vs.status = :status')
            ->andWhere('vs.expectedDate IS NOT NULL AND vs.expectedDate <= :cutoffDate')
            ->setParameter('status', 'active')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('vs.expectedDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 查找过期的虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findOverdueStocks(): array
    {
        $now = new \DateTimeImmutable();

        $result = $this->createQueryBuilder('vs')
            ->where('vs.status = :status')
            ->andWhere('vs.expectedDate IS NOT NULL AND vs.expectedDate < :now')
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->orderBy('vs.expectedDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 获取指定SKU的虚拟库存总量.
     */
    public function getTotalVirtualQuantityBySku(SKU $sku): int
    {
        $result = $this->createQueryBuilder('vs')
            ->select('SUM(vs.quantity)')
            ->where('vs.sku = :sku')
            ->andWhere('vs.status = :status')
            ->setParameter('sku', $sku)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 根据类型获取虚拟库存总量.
     */
    public function getTotalQuantityByType(string $virtualType): int
    {
        $result = $this->createQueryBuilder('vs')
            ->select('SUM(vs.quantity)')
            ->where('vs.virtualType = :virtualType')
            ->andWhere('vs.status = :status')
            ->setParameter('virtualType', $virtualType)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 获取虚拟库存统计信息.
     *
     * @return array<string, mixed>
     */
    public function getVirtualStockStatistics(): array
    {
        $results = $this->createQueryBuilder('vs')
            ->select('vs.virtualType, vs.status, COUNT(vs.id) as count, SUM(vs.quantity) as total_quantity')
            ->groupBy('vs.virtualType', 'vs.status')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($results));

        $stats = [
            'total_count' => 0,
            'total_quantity' => 0,
            'by_type' => [],
            'by_status' => [],
        ];

        foreach ($results as $result) {
            assert(is_array($result));
            assert(isset($result['virtualType']));
            assert(isset($result['status']));
            assert(isset($result['count']));
            assert(isset($result['total_quantity']));
            assert(is_string($result['virtualType']) || is_int($result['virtualType']));
            assert(is_string($result['status']) || is_int($result['status']));

            $type = $result['virtualType'];
            $status = $result['status'];
            $countValue = $result['count'];
            $quantityValue = $result['total_quantity'];
            assert(is_numeric($countValue) && is_numeric($quantityValue));
            $count = (int) $countValue;
            $quantity = (int) $quantityValue;

            $stats['total_count'] += $count;
            $stats['total_quantity'] += $quantity;

            if (!isset($stats['by_type'][$type])) {
                $stats['by_type'][$type] = ['count' => 0, 'quantity' => 0];
            }
            $stats['by_type'][$type]['count'] += $count;
            $stats['by_type'][$type]['quantity'] += $quantity;

            if (!isset($stats['by_status'][$status])) {
                $stats['by_status'][$status] = ['count' => 0, 'quantity' => 0];
            }
            $stats['by_status'][$status]['count'] += $count;
            $stats['by_status'][$status]['quantity'] += $quantity;
        }

        return $stats;
    }

    /**
     * 根据预期日期范围查找虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findByExpectedDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.expectedDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('vs.expectedDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }

    /**
     * 查找指定SKU和类型的活跃虚拟库存.
     *
     * @return VirtualStock[]
     */
    public function findActiveBySkuAndType(SKU $sku, string $virtualType): array
    {
        $result = $this->createQueryBuilder('vs')
            ->where('vs.sku = :sku')
            ->andWhere('vs.virtualType = :virtualType')
            ->andWhere('vs.status = :status')
            ->setParameter('sku', $sku)
            ->setParameter('virtualType', $virtualType)
            ->setParameter('status', 'active')
            ->orderBy('vs.expectedDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<VirtualStock> $result */
        return $result;
    }
}
