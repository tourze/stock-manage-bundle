<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 批次管理服务
 */
final class BatchService
{
    public function __construct(
        private StockBatchRepository $batchRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 根据批次号查找批次
     */
    public function findByBatchNo(string $batchNo): ?StockBatch
    {
        return $this->batchRepository->findOneBy(['batchNo' => $batchNo]);
    }

    /**
     * 根据ID查找批次
     *
     * @param int|string $id
     */
    public function findById($id): ?StockBatch
    {
        return $this->batchRepository->find($id);
    }

    /**
     * 检查批次号是否存在
     */
    public function isBatchNoExists(string $batchNo): bool
    {
        $batch = $this->findByBatchNo($batchNo);

        return null !== $batch;
    }

    /**
     * 创建新批次
     *
     * @param array<string, mixed> $data
     */
    public function createBatch(array $data): StockBatch
    {
        $batch = new StockBatch();

        if (isset($data['sku'])) {
            assert($data['sku'] instanceof SKU || null === $data['sku']);
            $batch->setSku($data['sku']);
        }

        if (isset($data['batch_no'])) {
            assert(is_string($data['batch_no']));
            $batch->setBatchNo($data['batch_no']);
        }

        if (isset($data['quantity'])) {
            assert(is_int($data['quantity']));
            $batch->setQuantity($data['quantity']);
        }

        if (isset($data['available_quantity'])) {
            assert(is_int($data['available_quantity']));
            $batch->setAvailableQuantity($data['available_quantity']);
        }

        if (isset($data['unit_cost'])) {
            assert(is_float($data['unit_cost']) || is_int($data['unit_cost']));
            $batch->setUnitCost((float) $data['unit_cost']);
        }

        if (isset($data['quality_level'])) {
            assert(is_string($data['quality_level']));
            $batch->setQualityLevel($data['quality_level']);
        }

        if (isset($data['status'])) {
            assert(is_string($data['status']));
            $batch->setStatus($data['status']);
        }

        if (isset($data['location_id'])) {
            assert(is_string($data['location_id']));
            $batch->setLocationId($data['location_id']);
        }

        if (isset($data['production_date'])) {
            assert($data['production_date'] instanceof \DateTimeInterface);
            $batch->setProductionDate($data['production_date']);
        }

        if (isset($data['expiry_date'])) {
            assert($data['expiry_date'] instanceof \DateTimeInterface);
            $batch->setExpiryDate($data['expiry_date']);
        }

        $this->entityManager->persist($batch);
        $this->entityManager->flush();

        return $batch;
    }

    /**
     * 更新批次库存数量
     */
    public function updateQuantity(StockBatch $batch, int $quantity, int $availableQuantity = null): void
    {
        $batch->setQuantity($quantity);

        if (null !== $availableQuantity) {
            $batch->setAvailableQuantity($availableQuantity);
        } else {
            // 如果没有指定可用数量，假设所有数量都是可用的
            $batch->setAvailableQuantity($quantity);
        }

        $this->entityManager->flush();
    }

    /**
     * 增加批次库存
     */
    public function addQuantity(StockBatch $batch, int $quantity): void
    {
        $batch->setQuantity($batch->getQuantity() + $quantity);
        $batch->setAvailableQuantity($batch->getAvailableQuantity() + $quantity);
        $this->entityManager->flush();
    }

    /**
     * 更新批次成本（加权平均）
     */
    public function updateUnitCost(StockBatch $batch, float $newUnitCost, int $addedQuantity): void
    {
        $currentQuantity = $batch->getQuantity() - $addedQuantity;
        $totalValue = $currentQuantity * $batch->getUnitCost() + $addedQuantity * $newUnitCost;
        $batch->setUnitCost($totalValue / $batch->getQuantity());
        $this->entityManager->flush();
    }
}