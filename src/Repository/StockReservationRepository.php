<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;

/**
 * @extends ServiceEntityRepository<StockReservation>
 */
#[AsRepository(entityClass: StockReservation::class)]
class StockReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockReservation::class);
    }

    /**
     * 保存预约实体.
     */
    public function save(StockReservation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除预约实体.
     */
    public function remove(StockReservation $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找指定 SPU 的活跃预约.
     *
     * @return StockReservation[]
     */
    public function findActiveBySpuId(string $spuId): array
    {
        return $this->findActiveBySku($spuId);
    }

    /**
     * 查找指定 SKU 的活跃预约（别名方法）.
     *
     * @return StockReservation[]
     */
    public function findActiveBySku(string $spuId): array
    {
        $result = $this->createQueryBuilder('r')
            ->join('r.sku', 's')
            ->where('s.gtin = :spuId')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresTime > :now')
            ->setParameter('spuId', $spuId)
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockReservation> $result */
        return $result;
    }

    /**
     * 查找需要释放的过期预约.
     *
     * @return StockReservation[]
     */
    public function findExpiredReservations(): array
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresTime <= :now')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockReservation> $result */
        return $result;
    }

    /**
     * 获取指定 SPU 的总预约数量.
     */
    public function getTotalReservedQuantity(string $spuId): int
    {
        // 如果传入的是纯数字，按SKU ID查询；否则按GTIN查询
        if (is_numeric($spuId)) {
            return $this->getTotalReservedQuantityBySkuId((int) $spuId);
        }

        return $this->getTotalReservedQuantityBySku($spuId);
    }

    /**
     * 根据 SKU ID 获取总预约数量.
     */
    public function getTotalReservedQuantityBySkuId(int $skuId): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity) as total')
            ->where('IDENTITY(r.sku) = :skuId')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresTime > :now')
            ->setParameter('skuId', $skuId)
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 获取指定 SKU 的总预约数量（别名方法）.
     */
    public function getTotalReservedQuantityBySku(string $spuId): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.quantity) as total')
            ->join('r.sku', 's')
            ->where('s.gtin = :spuId')
            ->andWhere('r.status = :status')
            ->andWhere('r.expiresTime > :now')
            ->setParameter('spuId', $spuId)
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 按业务 ID 查找预约.
     *
     * @return StockReservation[]
     */
    public function findByBusinessId(string $businessId): array
    {
        $result = $this->createQueryBuilder('r')
            ->where('r.businessId = :businessId')
            ->setParameter('businessId', $businessId)
            ->orderBy('r.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockReservation> $result */
        return $result;
    }

    /**
     * 按类型查找预约.
     *
     * @return StockReservation[]
     */
    public function findByType(StockReservationType $type, ?StockReservationStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.type = :type')
            ->setParameter('type', $type)
        ;

        if (null !== $status) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status)
            ;
        }

        $result = $qb->orderBy('r.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockReservation> $result */
        return $result;
    }

    /**
     * 检查业务 ID 是否存在预约.
     */
    public function existsForBusiness(string $businessId, string $spuId): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.sku', 's')
            ->where('r.businessId = :businessId')
            ->andWhere('s.gtin = :spuId')
            ->andWhere('r.status IN (:statuses)')
            ->setParameter('businessId', $businessId)
            ->setParameter('spuId', $spuId)
            ->setParameter('statuses', ['pending', 'confirmed'])
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * 获取即将过期的预约.
     *
     * @return StockReservation[]
     */
    public function findExpiringSoon(int $hoursAhead = 24): array
    {
        $deadline = new \DateTime(sprintf('+%d hours', $hoursAhead));

        $result = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.expiresTime > :now')
            ->andWhere('r.expiresTime <= :deadline')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->setParameter('deadline', $deadline)
            ->orderBy('r.expiresTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockReservation> $result */
        return $result;
    }

    /**
     * 获取预约统计信息.
     *
     * @return array{pending: array{count: int, quantity: int}, confirmed: array{count: int, quantity: int}, released: array{count: int, quantity: int}, expired: array{count: int, quantity: int}}
     */
    public function getStatistics(): array
    {
        $stats = $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count, SUM(r.quantity) as totalQuantity')
            ->groupBy('r.status')
            ->getQuery()
            ->getResult()
        ;
        assert(is_array($stats));

        $result = [
            'pending' => ['count' => 0, 'quantity' => 0],
            'confirmed' => ['count' => 0, 'quantity' => 0],
            'released' => ['count' => 0, 'quantity' => 0],
            'expired' => ['count' => 0, 'quantity' => 0],
        ];

        foreach ($stats as $stat) {
            assert(is_array($stat));
            assert(isset($stat['status']));
            assert($stat['status'] instanceof \BackedEnum);
            assert(isset($stat['count']));

            $statusKey = $stat['status']->value;
            if (isset($result[$statusKey])) {
                $count = $stat['count'];
                $quantity = $stat['totalQuantity'] ?? 0;
                assert(is_numeric($count) && is_numeric($quantity));

                $result[$statusKey] = [
                    'count' => (int) $count,
                    'quantity' => (int) $quantity,
                ];
            }
        }

        return $result;
    }
}
