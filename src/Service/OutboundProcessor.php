<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 出库处理器 - 负责具体的出库业务处理逻辑.
 */
class OutboundProcessor
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockBatchRepository $batchRepository,
        private AllocationService $allocationService,
    ) {
    }

    /**
     * 处理销售出库.
     *
     * @param array<string, mixed> $data
     *
     * @return array{requestedItems: array<mixed>, allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    public function processSalesOutbound(array $data): array
    {
        $requestedItems = [];
        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        assert(isset($data['items']) && is_array($data['items']));
        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $result = $this->processSalesOutboundItem($item, $data);
            assert(isset($result['requested'], $result['allocated'], $result['totalQuantity'], $result['totalCost']));
            assert(is_int($result['totalQuantity']) && is_float($result['totalCost']));

            $requestedItems[] = $result['requested'];
            $allocatedItems = array_merge($allocatedItems, (array) $result['allocated']);
            $totalQuantity += $result['totalQuantity'];
            $totalCost += $result['totalCost'];
        }

        return [
            'requestedItems' => $requestedItems,
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理损耗出库.
     *
     * @param array<string, mixed> $data
     *
     * @return array{requestedItems: array<mixed>, allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    public function processDamageOutbound(array $data): array
    {
        $requestedItems = [];
        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        assert(isset($data['items']) && is_array($data['items']));
        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $result = $this->processDamageOutboundItem($item);
            assert(isset($result['requested'], $result['allocated'], $result['totalQuantity'], $result['totalCost']));
            assert(is_int($result['totalQuantity']) && is_float($result['totalCost']));

            $requestedItems[] = $result['requested'];
            $allocatedItems[] = $result['allocated'];
            $totalQuantity += $result['totalQuantity'];
            $totalCost += $result['totalCost'];
        }

        return [
            'requestedItems' => $requestedItems,
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理调拨出库.
     *
     * @param array<string, mixed> $data
     *
     * @return array{requestedItems: array<mixed>, allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    public function processTransferOutbound(array $data): array
    {
        $requestedItems = [];
        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        assert(isset($data['items']) && is_array($data['items']));
        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $result = $this->processTransferOutboundItem($item);
            assert(isset($result['requested'], $result['allocated'], $result['totalQuantity'], $result['totalCost']));
            assert(is_int($result['totalQuantity']) && is_float($result['totalCost']));

            $requestedItems[] = $result['requested'];
            $allocatedItems[] = $result['allocated'];
            $totalQuantity += $result['totalQuantity'];
            $totalCost += $result['totalCost'];
        }

        return [
            'requestedItems' => $requestedItems,
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个销售出库项目.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function processSalesOutboundItem(array $item, array $data): array
    {
        $sku = $this->validateAndGetSku($item);
        $quantity = $this->validateAndGetQuantity($item, 'Invalid quantity provided');
        $strategy = $this->validateAndGetStrategy($item, 'Invalid allocation strategy provided');

        $requested = [
            'sku' => $sku,
            'quantity' => $quantity,
            'strategy' => $strategy,
        ];

        $criteria = $this->buildLocationCriteria($data);
        $allocation = $this->allocationService->calculateAllocation($sku, $quantity, $strategy, $criteria);
        $allocationResult = $this->processAllocationBatches($allocation, $sku);

        return [
            'requested' => $requested,
            'allocated' => $allocationResult['allocatedItems'],
            'totalQuantity' => $allocationResult['totalQuantity'],
            'totalCost' => $allocationResult['totalCost'],
        ];
    }

    /**
     * 验证并获取SKU.
     *
     * @param array<string, mixed> $item
     */
    private function validateAndGetSku(array $item): SKU
    {
        if (!isset($item['sku'])) {
            throw new InvalidOperationException('SKU is required');
        }

        $sku = $item['sku'];
        if (!$sku instanceof SKU) {
            throw new InvalidOperationException('Invalid SKU type');
        }

        return $sku;
    }

    /**
     * 验证并获取数量.
     *
     * @param array<string, mixed> $item
     */
    private function validateAndGetQuantity(array $item, string $errorMessage): int
    {
        if (!isset($item['quantity']) || (!is_int($item['quantity']) && !is_numeric($item['quantity']))) {
            throw new InvalidOperationException($errorMessage);
        }

        return (int) $item['quantity'];
    }

    /**
     * 验证并获取分配策略.
     *
     * @param array<string, mixed> $item
     */
    private function validateAndGetStrategy(array $item, string $errorMessage): string
    {
        $strategyValue = $item['allocation_strategy'] ?? 'fifo';
        if (!is_string($strategyValue)) {
            throw new InvalidOperationException($errorMessage);
        }

        return $strategyValue;
    }

    /**
     * 构建位置筛选条件.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildLocationCriteria(array $data): array
    {
        $criteria = [];
        if (isset($data['location_id'])) {
            $criteria['location_id'] = $data['location_id'];
        }

        return $criteria;
    }

    /**
     * 处理分配的批次.
     *
     * @param array<string, mixed> $allocation
     *
     * @return array{allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    private function processAllocationBatches(array $allocation, SKU $sku): array
    {
        assert(isset($allocation['batches']) && is_array($allocation['batches']));

        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        foreach ($allocation['batches'] as $allocBatch) {
            assert(is_array($allocBatch));
            /** @var array<string, mixed> $typedAllocBatch */
            $typedAllocBatch = $allocBatch;
            $batchResult = $this->processSingleAllocationBatch($typedAllocBatch, $sku);
            $allocatedItems[] = $batchResult['allocatedItem'];
            $totalQuantity += $batchResult['quantity'];
            $totalCost += $batchResult['cost'];
        }

        return [
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个分配批次.
     *
     * @param array<string, mixed> $allocBatch
     *
     * @return array{allocatedItem: array<string, mixed>, quantity: int, cost: float}
     */
    private function processSingleAllocationBatch(array $allocBatch, SKU $sku): array
    {
        assert(isset($allocBatch['batchId'], $allocBatch['quantity'], $allocBatch['unitCost'], $allocBatch['batchNo']));

        $batchId = $allocBatch['batchId'];
        assert(is_int($batchId) || is_string($batchId));
        $batch = $this->findAndValidateBatch($batchId);

        assert(is_int($allocBatch['quantity']));
        $allocQuantity = $allocBatch['quantity'];
        $this->deductBatchStock($batch, $allocQuantity);

        assert(is_float($allocBatch['unitCost']) || is_int($allocBatch['unitCost']));
        $unitCost = (float) $allocBatch['unitCost'];
        $cost = $allocQuantity * $unitCost;

        $allocatedItem = [
            'batch_id' => $allocBatch['batchId'],
            'batch_no' => $allocBatch['batchNo'],
            'sku' => $sku,
            'quantity' => $allocQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => $cost,
        ];

        return [
            'allocatedItem' => $allocatedItem,
            'quantity' => $allocQuantity,
            'cost' => $cost,
        ];
    }

    /**
     * 查找并验证批次.
     *
     * @param int|string $batchId
     */
    private function findAndValidateBatch($batchId): StockBatch
    {
        $batch = $this->batchRepository->find($batchId);
        if (null === $batch) {
            throw new InvalidOperationException(sprintf('批次不存在: %s', $batchId));
        }

        return $batch;
    }

    /**
     * 处理单个损耗出库项目.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function processDamageOutboundItem(array $item): array
    {
        assert(isset($item['batch_id']) && (is_int($item['batch_id']) || is_string($item['batch_id'])));
        assert(isset($item['quantity']) && is_int($item['quantity']));
        assert(isset($item['reason']) && is_string($item['reason']));

        $batchId = (int) $item['batch_id'];
        $quantity = $item['quantity'];
        $reason = $item['reason'];

        $batch = $this->batchRepository->find($batchId);
        if (null === $batch) {
            throw new InvalidOperationException(sprintf('批次不存在: %s', $batchId));
        }

        if ($batch->getAvailableQuantity() < $quantity) {
            $spuId = $batch->getSpuId() ?? 'unknown';
            throw InsufficientStockException::create($spuId, $quantity, $batch->getAvailableQuantity());
        }

        $requested = [
            'batch_id' => $batchId,
            'quantity' => $quantity,
            'reason' => $reason,
        ];

        $this->deductBatchStock($batch, $quantity);

        $unitCost = $batch->getUnitCost();
        $allocated = [
            'batch_id' => $batchId,
            'batch_no' => $batch->getBatchNo(),
            'sku' => $batch->getSku(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'reason' => $reason,
        ];

        return [
            'requested' => $requested,
            'allocated' => $allocated,
            'totalQuantity' => $quantity,
            'totalCost' => (float) ($quantity * $unitCost),
        ];
    }

    /**
     * 处理单个调拨出库项目.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function processTransferOutboundItem(array $item): array
    {
        assert(isset($item['batch_id']) && (is_int($item['batch_id']) || is_string($item['batch_id'])));
        assert(isset($item['quantity']) && is_int($item['quantity']));

        $batchId = (int) $item['batch_id'];
        $quantity = $item['quantity'];

        $batch = $this->batchRepository->find($batchId);
        if (null === $batch) {
            throw new InvalidOperationException(sprintf('批次不存在: %s', $batchId));
        }

        if ($batch->getAvailableQuantity() < $quantity) {
            $spuId = $batch->getSpuId() ?? 'unknown';
            throw InsufficientStockException::create($spuId, $quantity, $batch->getAvailableQuantity());
        }

        $requested = [
            'batch_id' => $batchId,
            'quantity' => $quantity,
        ];

        $this->deductBatchStock($batch, $quantity);

        $unitCost = $batch->getUnitCost();
        $allocated = [
            'batch_id' => $batchId,
            'batch_no' => $batch->getBatchNo(),
            'sku' => $batch->getSku(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ];

        return [
            'requested' => $requested,
            'allocated' => $allocated,
            'totalQuantity' => $quantity,
            'totalCost' => (float) ($quantity * $unitCost),
        ];
    }

    /**
     * 处理领用出库.
     *
     * @param array<string, mixed> $data
     *
     * @return array{requestedItems: array<mixed>, allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    public function processPickOutbound(array $data): array
    {
        $requestedItems = [];
        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        assert(isset($data['items']) && is_array($data['items']));
        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $result = $this->processPickOutboundItem($item, $data);
            assert(isset($result['requested'], $result['allocated'], $result['totalQuantity'], $result['totalCost']));
            assert(is_int($result['totalQuantity']) && is_float($result['totalCost']));

            $requestedItems[] = $result['requested'];
            $allocatedItems = array_merge($allocatedItems, (array) $result['allocated']);
            $totalQuantity += $result['totalQuantity'];
            $totalCost += $result['totalCost'];
        }

        return [
            'requestedItems' => $requestedItems,
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个领用出库项目.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function processPickOutboundItem(array $item, array $data): array
    {
        $sku = $this->validateAndGetSku($item);
        $quantity = $this->validateAndGetQuantity($item, 'Invalid quantity provided for pick outbound');
        $purpose = $this->validateAndGetPurpose($item);

        $requested = $this->buildPickRequested($sku, $quantity, $purpose, $data);
        $criteria = $this->buildLocationCriteria($data);

        $allocation = $this->allocationService->calculateAllocation($sku, $quantity, 'fifo', $criteria);
        $allocationResult = $this->processPickAllocationBatches($allocation, $sku, $purpose, $data);

        return [
            'requested' => $requested,
            'allocated' => $allocationResult['allocatedItems'],
            'totalQuantity' => $allocationResult['totalQuantity'],
            'totalCost' => $allocationResult['totalCost'],
        ];
    }

    /**
     * 验证并获取用途.
     *
     * @param array<string, mixed> $item
     */
    private function validateAndGetPurpose(array $item): string
    {
        $purposeValue = $item['purpose'] ?? '';
        if (!is_string($purposeValue)) {
            throw new InvalidOperationException('Invalid purpose provided');
        }

        return $purposeValue;
    }

    /**
     * 构建领用请求数据.
     *
     * @param array<string, mixed> $data
     * @return array{sku: SKU, quantity: int, purpose: string, department: mixed}
     */
    private function buildPickRequested(SKU $sku, int $quantity, string $purpose, array $data): array
    {
        return [
            'sku' => $sku,
            'quantity' => $quantity,
            'purpose' => $purpose,
            'department' => $data['department'],
        ];
    }

    /**
     * 处理领用分配的批次.
     *
     * @param array<string, mixed> $allocation
     * @param array<string, mixed> $data
     *
     * @return array{allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    private function processPickAllocationBatches(array $allocation, SKU $sku, string $purpose, array $data): array
    {
        assert(isset($allocation['batches']) && is_array($allocation['batches']));

        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        foreach ($allocation['batches'] as $allocBatch) {
            assert(is_array($allocBatch));
            /** @var array<string, mixed> $typedAllocBatch */
            $typedAllocBatch = $allocBatch;
            $batchResult = $this->processSinglePickAllocationBatch($typedAllocBatch, $sku, $purpose, $data);
            $allocatedItems[] = $batchResult['allocatedItem'];
            $totalQuantity += $batchResult['quantity'];
            $totalCost += $batchResult['cost'];
        }

        return [
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个领用分配批次.
     *
     * @param array<string, mixed> $allocBatch
     * @param array<string, mixed> $data
     *
     * @return array{allocatedItem: array<string, mixed>, quantity: int, cost: float}
     */
    private function processSinglePickAllocationBatch(array $allocBatch, SKU $sku, string $purpose, array $data): array
    {
        assert(isset($allocBatch['batchId'], $allocBatch['quantity'], $allocBatch['unitCost'], $allocBatch['batchNo']));

        $batchId = $allocBatch['batchId'];
        assert(is_int($batchId) || is_string($batchId));
        $batch = $this->findAndValidateBatch($batchId);

        assert(is_int($allocBatch['quantity']));
        $allocQuantity = $allocBatch['quantity'];
        $this->deductBatchStock($batch, $allocQuantity);

        assert(is_float($allocBatch['unitCost']) || is_int($allocBatch['unitCost']));
        $unitCost = (float) $allocBatch['unitCost'];
        $cost = $allocQuantity * $unitCost;

        $allocatedItem = [
            'batch_id' => $allocBatch['batchId'],
            'batch_no' => $allocBatch['batchNo'],
            'sku' => $sku,
            'quantity' => $allocQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => $cost,
            'purpose' => $purpose,
            'department' => $data['department'],
        ];

        return [
            'allocatedItem' => $allocatedItem,
            'quantity' => $allocQuantity,
            'cost' => $cost,
        ];
    }

    /**
     * 处理调整出库.
     *
     * @param array<string, mixed> $data
     *
     * @return array{requestedItems: array<mixed>, allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    public function processAdjustmentOutbound(array $data): array
    {
        $requestedItems = [];
        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        assert(isset($data['items']) && is_array($data['items']));
        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $result = $this->processAdjustmentOutboundItem($item, $data);
            assert(isset($result['requested'], $result['allocated'], $result['totalQuantity'], $result['totalCost']));
            assert(is_int($result['totalQuantity']) && is_float($result['totalCost']));

            $requestedItems[] = $result['requested'];
            $allocatedItems = array_merge($allocatedItems, (array) $result['allocated']);
            $totalQuantity += $result['totalQuantity'];
            $totalCost += $result['totalCost'];
        }

        return [
            'requestedItems' => $requestedItems,
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个调整出库项目.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function processAdjustmentOutboundItem(array $item, array $data): array
    {
        $sku = $this->validateAndGetSku($item);
        $quantity = $this->validateAndGetQuantity($item, 'Invalid quantity provided for adjustment outbound');
        $reason = $this->validateAndGetReason($item);

        $requested = [
            'sku' => $sku,
            'quantity' => $quantity,
            'reason' => $reason,
        ];

        $criteria = $this->buildLocationCriteria($data);

        $allocation = $this->allocationService->calculateAllocation($sku, $quantity, 'fifo', $criteria);
        $allocationResult = $this->processAdjustmentAllocationBatches($allocation, $sku, $reason);

        return [
            'requested' => $requested,
            'allocated' => $allocationResult['allocatedItems'],
            'totalQuantity' => $allocationResult['totalQuantity'],
            'totalCost' => $allocationResult['totalCost'],
        ];
    }

    /**
     * 验证并获取原因.
     *
     * @param array<string, mixed> $item
     */
    private function validateAndGetReason(array $item): string
    {
        if (!isset($item['reason']) || !is_string($item['reason'])) {
            throw new InvalidOperationException('Invalid reason provided for adjustment outbound');
        }

        return $item['reason'];
    }

    /**
     * 处理调整分配的批次.
     *
     * @param array<string, mixed> $allocation
     *
     * @return array{allocatedItems: array<mixed>, totalQuantity: int, totalCost: float}
     */
    private function processAdjustmentAllocationBatches(array $allocation, SKU $sku, string $reason): array
    {
        assert(isset($allocation['batches']) && is_array($allocation['batches']));

        $allocatedItems = [];
        $totalQuantity = 0;
        $totalCost = 0.0;

        foreach ($allocation['batches'] as $allocBatch) {
            assert(is_array($allocBatch));
            /** @var array<string, mixed> $typedAllocBatch */
            $typedAllocBatch = $allocBatch;
            $batchResult = $this->processSingleAdjustmentAllocationBatch($typedAllocBatch, $sku, $reason);
            $allocatedItems[] = $batchResult['allocatedItem'];
            $totalQuantity += $batchResult['quantity'];
            $totalCost += $batchResult['cost'];
        }

        return [
            'allocatedItems' => $allocatedItems,
            'totalQuantity' => $totalQuantity,
            'totalCost' => $totalCost,
        ];
    }

    /**
     * 处理单个调整分配批次.
     *
     * @param array<string, mixed> $allocBatch
     *
     * @return array{allocatedItem: array<string, mixed>, quantity: int, cost: float}
     */
    private function processSingleAdjustmentAllocationBatch(array $allocBatch, SKU $sku, string $reason): array
    {
        assert(isset($allocBatch['batchId'], $allocBatch['quantity'], $allocBatch['unitCost'], $allocBatch['batchNo']));

        $batchId = $allocBatch['batchId'];
        assert(is_int($batchId) || is_string($batchId));
        $batch = $this->findAndValidateBatch($batchId);

        assert(is_int($allocBatch['quantity']));
        $allocQuantity = $allocBatch['quantity'];
        $this->deductBatchStock($batch, $allocQuantity);

        assert(is_float($allocBatch['unitCost']) || is_int($allocBatch['unitCost']));
        $unitCost = (float) $allocBatch['unitCost'];
        $cost = $allocQuantity * $unitCost;

        $allocatedItem = [
            'batch_id' => $allocBatch['batchId'],
            'batch_no' => $allocBatch['batchNo'],
            'sku' => $sku,
            'quantity' => $allocQuantity,
            'unit_cost' => $unitCost,
            'total_cost' => $cost,
            'reason' => $reason,
        ];

        return [
            'allocatedItem' => $allocatedItem,
            'quantity' => $allocQuantity,
            'cost' => $cost,
        ];
    }

    /**
     * 扣减批次库存.
     */
    private function deductBatchStock(StockBatch $batch, int $quantity): void
    {
        $newAvailable = $batch->getAvailableQuantity() - $quantity;
        $batch->setAvailableQuantity($newAvailable);
        $batch->setUpdateTime(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
