<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 库存操作器 - 负责库存的锁定、解锁、扣减、退回等操作.
 */
class StockOperator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockBatchRepository $repository,
    ) {
    }

    /**
     * 批量处理库存日志.
     *
     * @param array<StockLog> $logs
     */
    public function batchProcess(array $logs): void
    {
        foreach ($logs as $log) {
            $this->process($log);
        }
    }

    /**
     * 处理单个库存日志.
     */
    public function process(StockLog $log): void
    {
        $sku = $log->getSku();
        if (null === $sku) {
            throw new InvalidOperationException('StockLog必须包含有效的SKU信息');
        }

        $quantity = $log->getQuantity();
        $type = $log->getType();

        match ($type) {
            StockChange::LOCK => $this->lockStock($sku, $quantity),
            StockChange::UNLOCK => $this->unlockStock($sku, $quantity),
            StockChange::DEDUCT => $this->deductStock($sku, $quantity),
            StockChange::RETURN => $this->returnStock($sku, $quantity),
            StockChange::PUT => $this->putStock($sku, $quantity),
            default => throw new InvalidOperationException('不支持的库存操作类型: ' . $type->value),
        };
    }

    /**
     * 锁定库存.
     */
    public function lockStock(SKU $sku, int $quantity): void
    {
        $batches = $this->repository->findAvailableBySku($sku);
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $remaining = $this->lockBatchStock($batch, $remaining);
        }

        if ($remaining > 0) {
            throw InsufficientStockException::create($sku->getId(), $quantity, $quantity - $remaining);
        }

        $this->entityManager->flush();
    }

    /**
     * 解锁库存.
     */
    public function unlockStock(SKU $sku, int $quantity): void
    {
        $batches = $this->repository->findBySku($sku);
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $remaining = $this->unlockBatchStock($batch, $remaining);
        }

        $this->entityManager->flush();
    }

    /**
     * 扣减库存.
     */
    public function deductStock(SKU $sku, int $quantity): void
    {
        $batches = $this->repository->findAvailableBySku($sku);
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $remaining = $this->deductBatchStock($batch, $remaining);
        }

        if ($remaining > 0) {
            throw new InsufficientStockException('商品库存不足');
        }

        $this->entityManager->flush();
    }

    /**
     * 退回库存.
     */
    public function returnStock(SKU $sku, int $quantity): void
    {
        $batches = $this->repository->findBySku($sku);

        if ([] === $batches) {
            throw new InvalidOperationException('未找到可退回库存的批次');
        }

        // 退回到最后一个可用批次
        $lastBatch = end($batches);
        $lastBatch->setQuantity($lastBatch->getQuantity() + $quantity);
        $lastBatch->setAvailableQuantity($lastBatch->getAvailableQuantity() + $quantity);

        $this->entityManager->flush();
    }

    /**
     * 入库.
     */
    public function putStock(SKU $sku, int $quantity): void
    {
        $batches = $this->repository->findBySku($sku);

        if ([] === $batches) {
            throw new InvalidOperationException('未找到可入库的批次');
        }

        // 入库到第一个可用批次
        $firstBatch = $batches[0];
        $firstBatch->setQuantity($firstBatch->getQuantity() + $quantity);
        $firstBatch->setAvailableQuantity($firstBatch->getAvailableQuantity() + $quantity);

        $this->entityManager->flush();
    }

    /**
     * 锁定批次库存.
     */
    private function lockBatchStock(StockBatch $batch, int $remaining): int
    {
        $availableInBatch = $batch->getAvailableQuantity();
        $toLock = min($remaining, $availableInBatch);

        if ($toLock > 0) {
            $batch->setAvailableQuantity($availableInBatch - $toLock);
            $batch->setLockedQuantity($batch->getLockedQuantity() + $toLock);
            $remaining -= $toLock;
        }

        return $remaining;
    }

    /**
     * 解锁批次库存.
     */
    private function unlockBatchStock(StockBatch $batch, int $remaining): int
    {
        $lockedInBatch = $batch->getLockedQuantity();
        $toUnlock = min($remaining, $lockedInBatch);

        if ($toUnlock > 0) {
            $batch->setLockedQuantity($lockedInBatch - $toUnlock);
            $batch->setAvailableQuantity($batch->getAvailableQuantity() + $toUnlock);
            $remaining -= $toUnlock;
        }

        return $remaining;
    }

    /**
     * 扣减批次库存.
     */
    private function deductBatchStock(StockBatch $batch, int $remaining): int
    {
        $availableInBatch = $batch->getAvailableQuantity();
        $toDeduct = min($remaining, $availableInBatch);

        if ($toDeduct > 0) {
            $batch->setQuantity($batch->getQuantity() - $toDeduct);
            $batch->setAvailableQuantity($availableInBatch - $toDeduct);
            $remaining -= $toDeduct;

            // 如果批次数量为0，标记为已耗尽
            if (0 === $batch->getQuantity()) {
                $batch->setStatus('depleted');
            }
        }

        return $remaining;
    }
}
