<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Exception\InvalidQuantityException;
use Tourze\StockManageBundle\Exception\InvalidStatusException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 库存验证器 - 负责各种库存相关的验证逻辑.
 */
class StockValidator
{
    public function __construct(
        private StockBatchRepository $repository,
    ) {
    }

    /**
     * 验证创建批次的数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateCreateBatchData(array $data): void
    {
        // 验证批次号唯一性
        if (isset($data['batch_no'])) {
            assert(is_string($data['batch_no']));
            if ($this->repository->existsByBatchNo($data['batch_no'])) {
                throw DuplicateBatchException::withBatchNo($data['batch_no']);
            }
        }

        // 验证必填参数
        if (!isset($data['sku']) || !($data['sku'] instanceof SKU)) {
            throw new InvalidOperationException('SKU不能为空');
        }

        // 验证数量
        if (!isset($data['quantity'])) {
            throw new InvalidQuantityException(0);
        }
        assert(is_int($data['quantity']));
        if ($data['quantity'] <= 0) {
            throw new InvalidQuantityException($data['quantity']);
        }
    }

    /**
     * 验证批次合并的兼容性.
     *
     * @param array<mixed> $batches
     */
    public function validateBatchCompatibility(array $batches): void
    {
        if (count($batches) < 2) {
            throw new InvalidOperationException('至少需要2个批次才能合并');
        }

        $firstBatch = $batches[0];
        assert($firstBatch instanceof StockBatch);
        $sku = $firstBatch->getSku();
        $qualityLevel = $firstBatch->getQualityLevel();
        $locationId = $firstBatch->getLocationId();

        foreach ($batches as $batch) {
            assert($batch instanceof StockBatch);
            if ($batch->getSku() !== $sku) {
                throw new InvalidOperationException('批次不兼容：SKU不同');
            }
            if ($batch->getQualityLevel() !== $qualityLevel) {
                throw new InvalidOperationException('批次不兼容：质量等级不同');
            }
            if ($batch->getLocationId() !== $locationId) {
                throw new InvalidOperationException('批次不兼容：位置不同');
            }
        }
    }

    /**
     * 验证批次状态
     */
    public function validateBatchStatus(string $status): void
    {
        $validStatuses = ['pending', 'in_transit', 'available', 'partially_available', 'depleted', 'expired', 'damaged', 'quarantined'];

        if (!in_array($status, $validStatuses, true)) {
            throw InvalidStatusException::create($status);
        }
    }

    /**
     * 验证数量调整.
     */
    public function validateQuantityAdjustment(StockBatch $batch, int $quantityAdjustment): void
    {
        $newQuantity = $batch->getQuantity() + $quantityAdjustment;

        if ($newQuantity < 0) {
            throw InvalidQuantityException::create($newQuantity);
        }
    }
}
