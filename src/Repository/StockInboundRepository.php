<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;

/**
 * @extends ServiceEntityRepository<StockInbound>
 */
#[AsRepository(entityClass: StockInbound::class)]
class StockInboundRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockInbound::class);
    }

    /**
     * 保存入库记录实体.
     */
    public function save(StockInbound $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除入库记录实体.
     */
    public function remove(StockInbound $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据业务单号查找入库记录.
     */
    public function findByReferenceNo(string $referenceNo): ?StockInbound
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.referenceNo = :referenceNo')
            ->setParameter('referenceNo', $referenceNo)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        assert(null === $result || $result instanceof StockInbound);

        return $result;
    }

    /**
     * 根据SKU查找入库记录.
     *
     * @return StockInbound[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 根据入库类型查找记录.
     *
     * @return StockInbound[]
     */
    public function findByType(StockInboundType $type): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.type = :type')
            ->setParameter('type', $type)
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 根据操作人查找入库记录.
     *
     * @return StockInbound[]
     */
    public function findByOperator(string $operator): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.operator = :operator')
            ->setParameter('operator', $operator)
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 根据位置查找入库记录.
     *
     * @return StockInbound[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 根据时间范围查找入库记录.
     *
     * @return StockInbound[]
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.createTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 获取入库统计信息.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function getInboundStats(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('si')
            ->select('COUNT(si.id) as total_records')
            ->addSelect('SUM(si.totalQuantity) as total_quantity')
            ->addSelect('SUM(si.totalAmount) as total_amount')
        ;

        if (isset($criteria['type'])) {
            $qb->andWhere('si.type = :type')
                ->setParameter('type', $criteria['type'])
            ;
        }

        if (isset($criteria['location_id'])) {
            $qb->andWhere('si.locationId = :locationId')
                ->setParameter('locationId', $criteria['location_id'])
            ;
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('si.createTime >= :startDate')
                ->setParameter('startDate', $criteria['start_date'])
            ;
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('si.createTime <= :endDate')
                ->setParameter('endDate', $criteria['end_date'])
            ;
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        $result = is_array($result) ? $result : [];

        $totalRecords = $result['total_records'] ?? 0;
        $totalQuantity = $result['total_quantity'] ?? 0;
        $totalAmount = $result['total_amount'] ?? 0.0;

        assert(is_numeric($totalRecords) && is_numeric($totalQuantity) && is_numeric($totalAmount));

        return [
            'total_records' => (int) $totalRecords,
            'total_quantity' => (int) $totalQuantity,
            'total_amount' => (float) $totalAmount,
        ];
    }

    /**
     * 获取最近的入库记录.
     *
     * @return StockInbound[]
     */
    public function findRecentInbounds(int $limit = 10): array
    {
        $result = $this->createQueryBuilder('si')
            ->orderBy('si.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 根据业务单号前缀查找入库记录.
     *
     * @return StockInbound[]
     */
    public function findByReferenceNoPrefix(string $prefix): array
    {
        $result = $this->createQueryBuilder('si')
            ->where('si.referenceNo LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('si.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockInbound> $result */
        return $result;
    }

    /**
     * 获取指定SKU的总入库数量.
     */
    public function getTotalInboundQuantityBySku(SKU $sku): int
    {
        $result = $this->createQueryBuilder('si')
            ->select('SUM(si.totalQuantity)')
            ->where('si.sku = :sku')
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 获取指定时间段内的入库类型统计.
     *
     * @return array<string, array{count: int, total_quantity: int}>
     */
    public function getInboundTypeStats(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $results = $this->createQueryBuilder('si')
            ->select('si.type, COUNT(si.id) as count, SUM(si.totalQuantity) as total_quantity')
            ->where('si.createTime BETWEEN :startDate AND :endDate')
            ->groupBy('si.type')
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

            $typeValue = $result['type']->value;
            assert(is_string($typeValue));
            $count = $result['count'];
            $totalQuantity = $result['total_quantity'];

            assert(is_numeric($count) && is_numeric($totalQuantity));

            $stats[$typeValue] = [
                'count' => (int) $count,
                'total_quantity' => (int) $totalQuantity,
            ];
        }

        return $stats;
    }
}
