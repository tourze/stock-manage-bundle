<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\BundleItem;
use Tourze\StockManageBundle\Entity\BundleStock;

/**
 * @extends ServiceEntityRepository<BundleItem>
 */
#[AsRepository(entityClass: BundleItem::class)]
class BundleItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BundleItem::class);
    }

    /**
     * 保存组合项目实体.
     */
    public function save(BundleItem $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 删除组合项目实体.
     */
    public function remove(BundleItem $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 根据组合商品查找所有项目.
     *
     * @return array<BundleItem>
     */
    public function findByBundleStock(BundleStock $bundleStock): array
    {
        $result = $this->createQueryBuilder('bi')
            ->where('bi.bundleStock = :bundleStock')
            ->setParameter('bundleStock', $bundleStock)
            ->orderBy('bi.sortOrder', 'ASC')
            ->addOrderBy('bi.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BundleItem> $result */
        return $result;
    }

    /**
     * 根据SKU查找相关的组合项目.
     *
     * @return array<BundleItem>
     */
    public function findBySku(SKU $sku): array
    {
        $result = $this->createQueryBuilder('bi')
            ->where('bi.sku = :sku')
            ->setParameter('sku', $sku)
            ->orderBy('bi.bundleStock', 'ASC')
            ->addOrderBy('bi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BundleItem> $result */
        return $result;
    }

    /**
     * 查找必需项目（非可选）.
     *
     * @return array<BundleItem>
     */
    public function findRequiredItemsByBundleStock(BundleStock $bundleStock): array
    {
        $result = $this->createQueryBuilder('bi')
            ->where('bi.bundleStock = :bundleStock')
            ->andWhere('bi.optional = :optional')
            ->setParameter('bundleStock', $bundleStock)
            ->setParameter('optional', false)
            ->orderBy('bi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BundleItem> $result */
        return $result;
    }

    /**
     * 查找可选项目.
     *
     * @return array<BundleItem>
     */
    public function findOptionalItemsByBundleStock(BundleStock $bundleStock): array
    {
        $result = $this->createQueryBuilder('bi')
            ->where('bi.bundleStock = :bundleStock')
            ->andWhere('bi.optional = :optional')
            ->setParameter('bundleStock', $bundleStock)
            ->setParameter('optional', true)
            ->orderBy('bi.sortOrder', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        assert(is_array($result));

        /** @var array<BundleItem> $result */
        return $result;
    }

    /**
     * 获取组合商品中某个SKU的总数量.
     */
    public function getTotalQuantityBySkuInBundle(BundleStock $bundleStock, SKU $sku): int
    {
        $result = $this->createQueryBuilder('bi')
            ->select('SUM(bi.quantity)')
            ->where('bi.bundleStock = :bundleStock')
            ->andWhere('bi.sku = :sku')
            ->setParameter('bundleStock', $bundleStock)
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return (int) ($result ?? 0);
    }

    /**
     * 检查组合商品中是否包含特定SKU.
     */
    public function bundleContainsSku(BundleStock $bundleStock, SKU $sku): bool
    {
        $count = $this->createQueryBuilder('bi')
            ->select('COUNT(bi.id)')
            ->where('bi.bundleStock = :bundleStock')
            ->andWhere('bi.sku = :sku')
            ->setParameter('bundleStock', $bundleStock)
            ->setParameter('sku', $sku)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }

    /**
     * 获取组合商品项目统计
     *
     * @return array{totalItems: int, requiredItems: int, optionalItems: int, totalQuantity: int}
     */
    public function getBundleItemStats(BundleStock $bundleStock): array
    {
        $qb = $this->createQueryBuilder('bi')
            ->select([
                'COUNT(bi.id) as totalItems',
                'COUNT(CASE WHEN bi.optional = false THEN 1 END) as requiredItems',
                'COUNT(CASE WHEN bi.optional = true THEN 1 END) as optionalItems',
                'SUM(bi.quantity) as totalQuantity',
            ])
            ->where('bi.bundleStock = :bundleStock')
            ->setParameter('bundleStock', $bundleStock)
        ;

        $rawResult = $qb->getQuery()->getSingleResult();
        assert(is_array($rawResult));

        $totalItems = $rawResult['totalItems'] ?? 0;
        $requiredItems = $rawResult['requiredItems'] ?? 0;
        $optionalItems = $rawResult['optionalItems'] ?? 0;
        $totalQuantity = $rawResult['totalQuantity'] ?? 0;

        assert(is_numeric($totalItems) && is_numeric($requiredItems) && is_numeric($optionalItems) && is_numeric($totalQuantity));

        return [
            'totalItems' => (int) $totalItems,
            'requiredItems' => (int) $requiredItems,
            'optionalItems' => (int) $optionalItems,
            'totalQuantity' => (int) $totalQuantity,
        ];
    }

    /**
     * 重新排序组合项目.
     *
     * @param array<int> $itemIdOrder
     */
    public function reorderItems(BundleStock $bundleStock, array $itemIdOrder): void
    {
        $em = $this->getEntityManager();

        foreach ($itemIdOrder as $index => $itemId) {
            $item = $this->find($itemId);
            if ($item instanceof BundleItem && $item->getBundleStock() === $bundleStock) {
                $item->setSortOrder($index + 1);
                $em->persist($item);
            }
        }

        $em->flush();
    }
}
