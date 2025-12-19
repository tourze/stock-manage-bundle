<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;

/**
 * 批次管理器 - 负责批次的创建、合并、拆分等操作.
 */
final class BatchManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockValidator $validator,
    ) {
    }

    /**
     * 创建新的库存批次
     *
     * @param array<string, mixed> $data
     */
    public function createBatch(array $data): StockBatch
    {
        $this->validator->validateCreateBatchData($data);

        $batch = new StockBatch();
        $this->populateBatchFromData($batch, $data);

        $this->entityManager->persist($batch);
        $this->entityManager->flush();

        return $batch;
    }

    /**
     * 合并多个批次
     *
     * @param StockBatch[] $batches
     */
    public function mergeBatches(array $batches, string $newBatchNo): StockBatch
    {
        $this->validator->validateBatchCompatibility($batches);

        $firstBatch = $batches[0];
        $mergeData = $this->calculateMergeData($batches);

        // 创建新批次
        $newBatch = new StockBatch();
        $newBatch->setBatchNo($newBatchNo);
        $newBatch->setSku($firstBatch->getSku());
        $newBatch->setQuantity($mergeData['totalQuantity']);
        $newBatch->setAvailableQuantity($mergeData['totalQuantity']);
        $newBatch->setUnitCost($mergeData['avgUnitCost']);
        $newBatch->setQualityLevel($firstBatch->getQualityLevel());
        $newBatch->setLocationId($firstBatch->getLocationId());
        $newBatch->setStatus('available');

        // 标记原批次为已耗尽
        foreach ($batches as $batch) {
            $batch->setStatus('depleted');
            $batch->setAvailableQuantity(0);
        }

        $this->entityManager->persist($newBatch);
        $this->entityManager->flush();

        return $newBatch;
    }

    /**
     * 拆分批次
     */
    public function splitBatch(StockBatch $batch, int $splitQuantity, string $newBatchNo): StockBatch
    {
        $this->validateSplitOperation($batch, $splitQuantity);

        // 创建新批次
        $newBatch = $this->cloneBatch($batch, $newBatchNo, $splitQuantity);

        // 更新原批次
        $this->updateOriginalBatch($batch, $splitQuantity);

        $this->entityManager->persist($newBatch);
        $this->entityManager->flush();

        return $newBatch;
    }

    /**
     * 更新批次状态
     */
    public function updateBatchStatus(StockBatch $batch, string $status): void
    {
        $this->validator->validateBatchStatus($status);

        $batch->setStatus($status);
        $this->entityManager->flush();
    }

    /**
     * 调整批次数量.
     */
    public function adjustBatchQuantity(StockBatch $batch, int $quantityAdjustment): void
    {
        $this->validator->validateQuantityAdjustment($batch, $quantityAdjustment);

        $newQuantity = $batch->getQuantity() + $quantityAdjustment;
        $batch->setQuantity($newQuantity);

        // 调整可用数量，确保不会变成负数
        $currentAvailable = $batch->getAvailableQuantity();
        $newAvailable = max(0, $currentAvailable + $quantityAdjustment);
        $batch->setAvailableQuantity($newAvailable);

        $this->entityManager->flush();
    }

    /**
     * 从数据填充批次
     *
     * @param array<string, mixed> $data
     */
    private function populateBatchFromData(StockBatch $batch, array $data): void
    {
        $batchNo = $data['batch_no'] ?? uniqid('BATCH_');
        assert(is_string($batchNo));
        $batch->setBatchNo($batchNo);

        $sku = $data['sku'] ?? null;
        assert($sku instanceof SKU || null === $sku);
        $batch->setSku($sku);

        $quantity = $data['quantity'];
        assert(is_int($quantity));
        $batch->setQuantity($quantity);
        $batch->setAvailableQuantity($quantity);
        $batch->setReservedQuantity(0);
        $batch->setLockedQuantity(0);

        $unitCost = $data['unit_cost'] ?? 0.00;
        assert(is_float($unitCost) || is_int($unitCost));
        $batch->setUnitCost((float) $unitCost);

        $qualityLevel = $data['quality_level'] ?? 'A';
        assert(is_string($qualityLevel));
        $batch->setQualityLevel($qualityLevel);

        $status = $data['status'] ?? 'available';
        assert(is_string($status));
        $batch->setStatus($status);

        $locationId = $data['location_id'] ?? null;
        assert(is_string($locationId) || null === $locationId);
        $batch->setLocationId($locationId);

        $batch->setProductionDate(isset($data['production_date']) && $data['production_date'] instanceof \DateTimeImmutable ? $data['production_date'] : null);
        $batch->setExpiryDate(isset($data['expiry_date']) && $data['expiry_date'] instanceof \DateTimeImmutable ? $data['expiry_date'] : null);

        $attributes = $data['attributes'] ?? null;
        if (null !== $attributes) {
            assert(is_array($attributes));
            /** @var array<string, mixed> $typedAttributes */
            $typedAttributes = $attributes;
            $batch->setAttributes($typedAttributes);
        } else {
            $batch->setAttributes(null);
        }
    }

    /**
     * 计算合并数据.
     *
     * @param StockBatch[] $batches
     *
     * @return array{totalQuantity: int, avgUnitCost: float}
     */
    private function calculateMergeData(array $batches): array
    {
        $totalQuantity = 0;
        $totalCost = 0;

        foreach ($batches as $batch) {
            $totalQuantity += $batch->getQuantity();
            $totalCost += $batch->getQuantity() * $batch->getUnitCost();
        }

        $avgUnitCost = $totalQuantity > 0 ? $totalCost / $totalQuantity : 0;

        return [
            'totalQuantity' => $totalQuantity,
            'avgUnitCost' => $avgUnitCost,
        ];
    }

    /**
     * 验证拆分操作.
     */
    private function validateSplitOperation(StockBatch $batch, int $splitQuantity): void
    {
        if ($splitQuantity <= 0 || $splitQuantity > $batch->getQuantity()) {
            throw new InvalidOperationException('拆分数量必须大于0且小于等于原批次数量');
        }

        if ($splitQuantity > $batch->getAvailableQuantity()) {
            $sku = $batch->getSku();
            $skuId = null !== $sku ? $sku->getId() : 'Unknown';
            throw InsufficientStockException::create($skuId, $splitQuantity, $batch->getAvailableQuantity());
        }
    }

    /**
     * 克隆批次
     */
    private function cloneBatch(StockBatch $originalBatch, string $newBatchNo, int $splitQuantity): StockBatch
    {
        $newBatch = new StockBatch();
        $newBatch->setBatchNo($newBatchNo);
        $newBatch->setSku($originalBatch->getSku());
        $newBatch->setQuantity($splitQuantity);
        $newBatch->setAvailableQuantity($splitQuantity);
        $newBatch->setUnitCost($originalBatch->getUnitCost());
        $newBatch->setQualityLevel($originalBatch->getQualityLevel());
        $newBatch->setLocationId($originalBatch->getLocationId());
        $newBatch->setProductionDate($originalBatch->getProductionDate());
        $newBatch->setExpiryDate($originalBatch->getExpiryDate());
        $newBatch->setAttributes($originalBatch->getAttributes());
        $newBatch->setStatus('available');

        return $newBatch;
    }

    /**
     * 更新原始批次
     */
    private function updateOriginalBatch(StockBatch $batch, int $splitQuantity): void
    {
        $batch->setQuantity($batch->getQuantity() - $splitQuantity);
        $batch->setAvailableQuantity($batch->getAvailableQuantity() - $splitQuantity);

        // 如果原批次数量为0，标记为已耗尽
        if (0 === $batch->getQuantity()) {
            $batch->setStatus('depleted');
        }
    }
}
