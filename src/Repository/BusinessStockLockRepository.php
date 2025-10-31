<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\StockManageBundle\Entity\BusinessStockLock;

/**
 * @extends ServiceEntityRepository<BusinessStockLock>
 */
#[AsRepository(entityClass: BusinessStockLock::class)]
class BusinessStockLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessStockLock::class);
    }

    /**
     * 保存业务库存锁定实体.
     */
    public function save(BusinessStockLock $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除业务库存锁定实体.
     */
    public function remove(BusinessStockLock $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据业务ID查找锁定记录.
     *
     * @return BusinessStockLock[]
     */
    public function findByBusinessId(string $businessId): array
    {
        $result = $this->createQueryBuilder('bsl')
            ->where('bsl.businessId = :businessId')
            ->setParameter('businessId', $businessId)
            ->orderBy('bsl.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BusinessStockLock> $result */
        return $result;
    }

    /**
     * 查找活跃的锁定记录.
     *
     * @return BusinessStockLock[]
     */
    public function findActiveLocks(): array
    {
        $result = $this->createQueryBuilder('bsl')
            ->where('bsl.status = :status')
            ->andWhere('bsl.expiresTime IS NULL OR bsl.expiresTime > :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bsl.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BusinessStockLock> $result */
        return $result;
    }

    /**
     * 查找过期的锁定记录.
     *
     * @return BusinessStockLock[]
     */
    public function findExpiredLocks(): array
    {
        $result = $this->createQueryBuilder('bsl')
            ->where('bsl.status = :status')
            ->andWhere('bsl.expiresTime IS NOT NULL AND bsl.expiresTime <= :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('bsl.expiresTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BusinessStockLock> $result */
        return $result;
    }

    /**
     * 根据类型查找锁定记录.
     *
     * @return BusinessStockLock[]
     */
    public function findByType(string $type): array
    {
        $result = $this->createQueryBuilder('bsl')
            ->where('bsl.type = :type')
            ->setParameter('type', $type)
            ->orderBy('bsl.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BusinessStockLock> $result */
        return $result;
    }

    /**
     * 检查业务是否存在活跃锁定.
     */
    public function hasActiveLock(string $businessId): bool
    {
        $count = $this->createQueryBuilder('bsl')
            ->select('COUNT(bsl.id)')
            ->where('bsl.businessId = :businessId')
            ->andWhere('bsl.status = :status')
            ->andWhere('bsl.expiresTime IS NULL OR bsl.expiresTime > :now')
            ->setParameter('businessId', $businessId)
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
