<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @extends ServiceEntityRepository<StockBatch>
 */
#[AsRepository(entityClass: StockBatch::class)]
final class StockBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockBatch::class);
    }

    /**
     * 保存批次实体.
     */
    public function save(StockBatch $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除批次实体.
     */
    public function remove(StockBatch $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据 SKU 查找批次
     *
     * @return StockBatch[]
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('b')
            ->where('b.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('b.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 查找可用批次
     *
     * @param array<string, mixed> $criteria
     *
     * @return StockBatch[]
     */
    public function findAvailable(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.status = :status AND b.availableQuantity > 0')
            ->setParameter('status', 'available')
            ->orderBy('b.createTime', 'ASC')
        ;

        if (isset($criteria['sku'])) {
            $qb->andWhere('b.sku = :sku')
                ->setParameter('sku', $criteria['sku'])
            ;
        }

        if (isset($criteria['location_id'])) {
            $qb->andWhere('b.locationId = :locationId')
                ->setParameter('locationId', $criteria['location_id'])
            ;
        }

        if (isset($criteria['quality_level'])) {
            $qb->andWhere('b.qualityLevel = :qualityLevel')
                ->setParameter('qualityLevel', $criteria['quality_level'])
            ;
        }

        if (isset($criteria['exclude_expired']) && (bool) $criteria['exclude_expired']) {
            $qb->andWhere('b.expiryDate IS NULL OR b.expiryDate > :now')
                ->setParameter('now', new \DateTime())
            ;
        }

        $result = $qb->getQuery()->getResult();

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 根据 SKU 查找可用批次
     *
     * @return StockBatch[]
     */
    public function findAvailableBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('b')
            ->where('b.sku = :sku')
            ->andWhere('b.status = :status')
            ->andWhere('b.availableQuantity > 0')
            ->setParameter('sku', $sku)
            ->setParameter('status', 'available')
            ->orderBy('b.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 查找过期批次
     *
     * @return StockBatch[]
     */
    public function findExpiredBatches(): array
    {
        $result = $this->createQueryBuilder('b')
            ->where('b.expiryDate IS NOT NULL AND b.expiryDate < :now')
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 获取批次汇总信息.
     *
     * @param string[] $spuIds
     *
     * @return array<string, array{total_batches: int, total_quantity: int, total_available: int, total_reserved: int, total_locked: int}>
     */
    public function getBatchSummary(array $spuIds = []): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT 
                    spu_id, 
                    COUNT(*) as total_batches,
                    SUM(quantity) as total_quantity,
                    SUM(available_quantity) as total_available,
                    SUM(reserved_quantity) as total_reserved,
                    SUM(locked_quantity) as total_locked
                FROM stock_batches';

        if ([] !== $spuIds) {
            $placeholders = implode(',', array_fill(0, count($spuIds), '?'));
            $sql .= ' WHERE spu_id IN (' . $placeholders . ')';
            $sql .= ' GROUP BY spu_id';
            // array_values确保返回的是indexed array，PHPStan可以接受
            $stmt = $conn->executeQuery($sql, array_values($spuIds));
        } else {
            $sql .= ' GROUP BY spu_id';
            $stmt = $conn->executeQuery($sql);
        }
        $results = $stmt->fetchAllAssociative();

        $summary = [];
        foreach ($results as $row) {
            assert(isset($row['spu_id']));
            assert(is_string($row['spu_id']));

            $totalBatches = $row['total_batches'] ?? 0;
            $totalQuantity = $row['total_quantity'] ?? 0;
            $totalAvailable = $row['total_available'] ?? 0;
            $totalReserved = $row['total_reserved'] ?? 0;
            $totalLocked = $row['total_locked'] ?? 0;

            assert(is_numeric($totalBatches) && is_numeric($totalQuantity) && is_numeric($totalAvailable) && is_numeric($totalReserved) && is_numeric($totalLocked));

            $summary[$row['spu_id']] = [
                'total_batches' => (int) $totalBatches,
                'total_quantity' => (int) $totalQuantity,
                'total_available' => (int) $totalAvailable,
                'total_reserved' => (int) $totalReserved,
                'total_locked' => (int) $totalLocked,
            ];
        }

        return $summary;
    }

    /**
     * 检查批次号是否存在.
     */
    public function existsByBatchNo(string $batchNo): bool
    {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.batchNo = :batchNo')
            ->setParameter('batchNo', $batchNo)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * 根据位置查找批次
     *
     * @return StockBatch[]
     */
    public function findByLocation(string $locationId): array
    {
        $result = $this->createQueryBuilder('b')
            ->where('b.locationId = :locationId')
            ->setParameter('locationId', $locationId)
            ->orderBy('b.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 根据质量等级查找批次
     *
     * @return StockBatch[]
     */
    public function findByQualityLevel(string $qualityLevel): array
    {
        $result = $this->createQueryBuilder('b')
            ->where('b.qualityLevel = :qualityLevel')
            ->setParameter('qualityLevel', $qualityLevel)
            ->orderBy('b.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 查找即将过期的批次（30天内）.
     *
     * @return StockBatch[]
     */
    public function findBatchesExpiringSoon(int $days = 30): array
    {
        $expiryDate = new \DateTime("+{$days} days");

        $result = $this->createQueryBuilder('b')
            ->where('b.expiryDate IS NOT NULL')
            ->andWhere('b.expiryDate BETWEEN :now AND :expiryDate')
            ->andWhere('b.availableQuantity > 0')
            ->setParameter('now', new \DateTime())
            ->setParameter('expiryDate', $expiryDate)
            ->orderBy('b.expiryDate', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }

    /**
     * 获取库存总量统计
     *
     * @return array{total_skus: int, total_quantity: int, total_available: int, total_reserved: int, total_locked: int, expired_batches: int}
     */
    public function getTotalStockStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT 
                    COUNT(*) as total_skus,
                    SUM(quantity) as total_quantity,
                    SUM(available_quantity) as total_available,
                    SUM(reserved_quantity) as total_reserved,
                    SUM(locked_quantity) as total_locked,
                    COUNT(CASE WHEN expiry_date IS NOT NULL AND expiry_date < CURRENT_TIMESTAMP THEN 1 END) as expired_batches
                FROM stock_batches';

        $result = $conn->executeQuery($sql)->fetchAssociative();
        $result = is_array($result) ? $result : [];

        $totalSkus = $result['total_skus'] ?? 0;
        $totalQuantity = $result['total_quantity'] ?? 0;
        $totalAvailable = $result['total_available'] ?? 0;
        $totalReserved = $result['total_reserved'] ?? 0;
        $totalLocked = $result['total_locked'] ?? 0;
        $expiredBatches = $result['expired_batches'] ?? 0;

        assert(is_numeric($totalSkus) && is_numeric($totalQuantity) && is_numeric($totalAvailable) && is_numeric($totalReserved) && is_numeric($totalLocked) && is_numeric($expiredBatches));

        return [
            'total_skus' => (int) $totalSkus,
            'total_quantity' => (int) $totalQuantity,
            'total_available' => (int) $totalAvailable,
            'total_reserved' => (int) $totalReserved,
            'total_locked' => (int) $totalLocked,
            'expired_batches' => (int) $expiredBatches,
        ];
    }

    /**
     * 获取指定SKU的总可用数量.
     */
    public function getTotalAvailableQuantityBySku(SKU $sku): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('SUM(b.availableQuantity)')
            ->where('b.sku = :sku')
            ->andWhere('b.status = :status')
            ->setParameter('sku', $sku)
            ->setParameter('status', 'available')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 获取指定SPU的加权平均成本.
     */
    public function getWeightedAverageCost(string $spuId): float
    {
        return $this->getWeightedAverageCostBySku($spuId);
    }

    /**
     * 获取指定SKU的加权平均成本（别名方法）.
     */
    public function getWeightedAverageCostBySku(string $spuId): float
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT 
                    SUM(available_quantity * unit_cost) / SUM(available_quantity) as weighted_average_cost
                FROM stock_batches 
                WHERE spu_id = ? 
                AND status = "available" 
                AND available_quantity > 0';

        $result = $conn->executeQuery($sql, [$spuId])->fetchOne();
        $value = $result ?? 0.0;
        assert(is_numeric($value));

        return (float) $value;
    }

    /**
     * 获取指定SPU ID的总可用数量（根据SKU ID查找）.
     */
    public function getTotalAvailableQuantity(string $spuId): int
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = 'SELECT SUM(b.available_quantity)
                FROM stock_batches b
                JOIN product_sku s ON b.sku_id = s.id
                WHERE s.id = ?
                AND b.status = "available"';

        $result = $conn->executeQuery($sql, [$spuId])->fetchOne();
        $value = $result ?? 0;
        assert(is_numeric($value));

        return (int) $value;
    }

    /**
     * 根据 SKU ID 获取总数量.
     */
    public function getTotalQuantityBySkuId(int $skuId): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('SUM(b.quantity)')
            ->where('IDENTITY(b.sku) = :skuId')
            ->andWhere('b.status = :status')
            ->setParameter('skuId', $skuId)
            ->setParameter('status', 'available')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 根据 SKU ID 获取总可用数量.
     */
    public function getTotalAvailableQuantityBySkuId(int $skuId): int
    {
        $result = $this->createQueryBuilder('b')
            ->select('SUM(b.availableQuantity)')
            ->where('IDENTITY(b.sku) = :skuId')
            ->andWhere('b.status = :status')
            ->setParameter('skuId', $skuId)
            ->setParameter('status', 'available')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 根据 SPU ID 和其他条件查找批次
     *
     * @param array<string, mixed> $criteria
     *
     * @return StockBatch[]
     */
    public function findBySpuId(string $spuId, array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->join('b.sku', 's')
            ->where('s.id = :spuId')
            ->setParameter('spuId', $spuId)
        ;

        if (isset($criteria['locationId'])) {
            $qb->andWhere('b.locationId = :locationId')
                ->setParameter('locationId', $criteria['locationId'])
            ;
        }

        if (isset($criteria['id'])) {
            $qb->andWhere('b.id = :batchId')
                ->setParameter('batchId', $criteria['id'])
            ;
        }

        $result = $qb->getQuery()->getResult();

        assert(is_array($result));

        /** @var array<StockBatch> $result */
        return $result;
    }
}
