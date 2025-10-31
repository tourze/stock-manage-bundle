<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;

/**
 * @extends ServiceEntityRepository<StockTransfer>
 */
#[AsRepository(entityClass: StockTransfer::class)]
class StockTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockTransfer::class);
    }

    /**
     * 保存库存调拨实体.
     */
    public function save(StockTransfer $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除库存调拨实体.
     */
    public function remove(StockTransfer $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据调拨单号查找记录.
     */
    public function findByTransferNo(string $transferNo): ?StockTransfer
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.transferNo = :transferNo')
            ->setParameter('transferNo', $transferNo)
            ->getQuery()
            ->getOneOrNullResult()
        ;
        assert(null === $result || $result instanceof StockTransfer);

        return $result;
    }

    /**
     * 根据SKU查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据源位置查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByFromLocation(string $fromLocation): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.fromLocation = :fromLocation')
            ->setParameter('fromLocation', $fromLocation)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据目标位置查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByToLocation(string $toLocation): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.toLocation = :toLocation')
            ->setParameter('toLocation', $toLocation)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据状态查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByStatus(StockTransferStatus $status): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.status = :status')
            ->setParameter('status', $status)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 查找待处理的调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findPendingTransfers(): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.status = :status')
            ->setParameter('status', StockTransferStatus::PENDING)
            ->orderBy('st.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 查找运输中的调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findInTransitTransfers(): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.status = :status')
            ->setParameter('status', StockTransferStatus::IN_TRANSIT)
            ->orderBy('st.shippedTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据发起人查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByInitiator(string $initiator): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.initiator = :initiator')
            ->setParameter('initiator', $initiator)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据接收人查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByReceiver(string $receiver): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.receiver = :receiver')
            ->setParameter('receiver', $receiver)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 根据时间范围查找调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findByDateRange(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.createTime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 获取调拨统计信息.
     *
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function getTransferStats(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('st')
            ->select('COUNT(st.id) as total_transfers')
            ->addSelect('SUM(st.totalQuantity) as total_quantity')
        ;

        if (isset($criteria['status'])) {
            $qb->andWhere('st.status = :status')
                ->setParameter('status', $criteria['status'])
            ;
        }

        if (isset($criteria['from_location'])) {
            $qb->andWhere('st.fromLocation = :fromLocation')
                ->setParameter('fromLocation', $criteria['from_location'])
            ;
        }

        if (isset($criteria['to_location'])) {
            $qb->andWhere('st.toLocation = :toLocation')
                ->setParameter('toLocation', $criteria['to_location'])
            ;
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('st.createTime >= :startDate')
                ->setParameter('startDate', $criteria['start_date'])
            ;
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('st.createTime <= :endDate')
                ->setParameter('endDate', $criteria['end_date'])
            ;
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        $result = is_array($result) ? $result : [];

        $totalTransfers = $result['total_transfers'] ?? 0;
        $totalQuantity = $result['total_quantity'] ?? 0;
        assert(is_numeric($totalTransfers) && is_numeric($totalQuantity));

        return [
            'total_transfers' => (int) $totalTransfers,
            'total_quantity' => (int) $totalQuantity,
        ];
    }

    /**
     * 获取状态统计.
     *
     * @return array<string, array{count: int, total_quantity: int}>
     */
    public function getStatusStatistics(): array
    {
        $results = $this->createQueryBuilder('st')
            ->select('st.status, COUNT(st.id) as count, SUM(st.totalQuantity) as total_quantity')
            ->groupBy('st.status')
            ->getQuery()
            ->getResult()
        ;
        assert(is_array($results));

        $stats = [];
        foreach ($results as $result) {
            assert(is_array($result));
            assert(isset($result['status']));
            assert($result['status'] instanceof \BackedEnum);
            assert(isset($result['count']));
            assert(isset($result['total_quantity']));

            $statusValue = $result['status']->value;
            assert(is_string($statusValue));
            $count = $result['count'];
            $totalQuantity = $result['total_quantity'];
            assert(is_numeric($count) && is_numeric($totalQuantity));

            $stats[$statusValue] = [
                'count' => (int) $count,
                'total_quantity' => (int) $totalQuantity,
            ];
        }

        return $stats;
    }

    /**
     * 查找超时的运输中调拨（超过指定天数未接收）.
     *
     * @return StockTransfer[]
     */
    public function findOverdueInTransitTransfers(int $days = 7): array
    {
        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $days));

        $result = $this->createQueryBuilder('st')
            ->where('st.status = :status')
            ->andWhere('st.shippedTime IS NOT NULL AND st.shippedTime < :cutoffDate')
            ->setParameter('status', StockTransferStatus::IN_TRANSIT)
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('st.shippedTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }

    /**
     * 查找两个位置之间的调拨记录.
     *
     * @return StockTransfer[]
     */
    public function findBetweenLocations(string $fromLocation, string $toLocation): array
    {
        $result = $this->createQueryBuilder('st')
            ->where('st.fromLocation = :fromLocation')
            ->andWhere('st.toLocation = :toLocation')
            ->setParameter('fromLocation', $fromLocation)
            ->setParameter('toLocation', $toLocation)
            ->orderBy('st.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockTransfer> $result */
        return $result;
    }
}
