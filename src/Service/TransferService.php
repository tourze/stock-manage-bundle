<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

class TransferService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly StockBatchRepository $batchRepository,
        private OutboundServiceInterface $outboundService,
        private InboundServiceInterface $inboundService,
    ) {
    }

    /**
     * 创建调拨单.
     *
     * @param array{
     *     transfer_no: string,
     *     from_location: string,
     *     to_location: string,
     *     items: array<array{
     *         batch_id: string,
     *         quantity: int
     *     }>,
     *     operator: string,
     *     notes?: string,
     *     expected_arrival?: \DateTimeInterface
     * } $data
     */
    public function createTransfer(array $data): StockTransfer
    {
        $this->validateTransferData($data);

        $transfer = $this->initializeTransfer($data);
        $transferItems = $this->processTransferItems($data['items'], $data['from_location']);

        $transfer->setItems($transferItems);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * 初始化调拨单基础信息.
     *
     * @param array<string, mixed> $data
     */
    private function initializeTransfer(array $data): StockTransfer
    {
        $transfer = new StockTransfer();

        assert(isset($data['transfer_no']) && is_string($data['transfer_no']));
        $transfer->setTransferNo($data['transfer_no']);

        assert(isset($data['from_location']) && is_string($data['from_location']));
        $transfer->setFromLocation($data['from_location']);

        assert(isset($data['to_location']) && is_string($data['to_location']));
        $transfer->setToLocation($data['to_location']);

        $operator = $data['operator'] ?? null;
        assert(is_string($operator) || null === $operator);
        $transfer->setInitiator($operator);

        $notes = $data['notes'] ?? null;
        assert(is_string($notes) || null === $notes);
        $transfer->setReason($notes);

        $transfer->setStatus(StockTransferStatus::PENDING);

        if (isset($data['expected_arrival']) && $data['expected_arrival'] instanceof \DateTimeInterface) {
            $metadata = ['expected_arrival' => $data['expected_arrival']->format('Y-m-d H:i:s')];
            $transfer->setMetadata($metadata);
        }

        return $transfer;
    }

    /**
     * 处理调拨项目.
     *
     * @param array<mixed> $items
     *
     * @return list<array<string, mixed>>
     */
    private function processTransferItems(array $items, string $fromLocation): array
    {
        $processedItems = [];
        $totalQuantity = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Transfer item must be an array');
            }
            /** @var array<string, mixed> $item */
            $validatedItem = $this->validateAndProcessTransferItem($item, $fromLocation);
            $processedItems[] = $validatedItem;
            assert(is_int($validatedItem['quantity']));
            $totalQuantity += $validatedItem['quantity'];
        }

        return $processedItems;
    }

    /**
     * 验证并处理单个调拨项目.
     *
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    private function validateAndProcessTransferItem(array $item, string $fromLocation): array
    {
        if (!isset($item['batch_id'])) {
            throw new \InvalidArgumentException('Transfer item must have batch_id');
        }
        $batchId = $this->ensureInt($item['batch_id']);

        assert(isset($item['quantity']) && is_int($item['quantity']));
        $quantity = $item['quantity'];

        $batch = $this->findAndValidateBatch($batchId);
        $this->validateBatchLocation($batch, $fromLocation);
        $sku = $this->validateBatchSku($batch);
        $this->validateStockAvailability($batch, $sku, $quantity);

        return [
            'batch_id' => $batchId,
            'batch_no' => $batch->getBatchNo(),
            'sku' => $sku,
            'quantity' => $quantity,
            'unit_cost' => $batch->getUnitCost(),
            'quality_level' => $batch->getQualityLevel(),
        ];
    }

    /**
     * 查找并验证批次.
     */
    private function findAndValidateBatch(int $batchId): StockBatch
    {
        $batch = $this->batchRepository->find($batchId);
        if (null === $batch) {
            throw new InvalidOperationException(sprintf('批次不存在: %s', $batchId));
        }

        return $batch;
    }

    /**
     * 验证批次位置.
     */
    private function validateBatchLocation(StockBatch $batch, string $expectedLocation): void
    {
        if ($batch->getLocationId() !== $expectedLocation) {
            throw new InvalidOperationException(sprintf('批次 %s 不在源位置 %s', $batch->getBatchNo(), $expectedLocation));
        }
    }

    /**
     * 验证批次SKU.
     */
    private function validateBatchSku(StockBatch $batch): SKU
    {
        $sku = $batch->getSku();
        if (null === $sku) {
            throw new InvalidOperationException(sprintf('批次 %s 的SKU不存在', $batch->getBatchNo()));
        }

        return $sku;
    }

    /**
     * 验证库存可用性.
     */
    private function validateStockAvailability(StockBatch $batch, SKU $sku, int $quantity): void
    {
        if ($batch->getAvailableQuantity() < $quantity) {
            throw InsufficientStockException::create($sku->getId(), $quantity, $batch->getAvailableQuantity());
        }
    }

    /**
     * 执行调拨（发出）.
     */
    public function executeTransfer(StockTransfer $transfer): StockTransfer
    {
        $this->validateTransferForExecution($transfer);

        $outboundItems = $this->prepareOutboundItems($transfer);
        $outboundData = $this->buildOutboundData($transfer, $outboundItems);

        $this->outboundService->transferOutbound($outboundData);
        $this->updateTransferAsShipped($transfer);

        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * 验证调拨单是否可以执行.
     */
    private function validateTransferForExecution(StockTransfer $transfer): void
    {
        if (!$transfer->isPending()) {
            throw new InvalidOperationException(sprintf('调拨单%s状态为%s，不能执行', $transfer->getTransferNo(), $transfer->getStatus()->value));
        }
    }

    /**
     * 准备出库商品列表.
     *
     * @return array<array{batch_id: string, quantity: int}>
     */
    private function prepareOutboundItems(StockTransfer $transfer): array
    {
        $transferItems = $transfer->getItems();
        $outboundItems = [];

        foreach ($transferItems as $item) {
            assert(is_array($item));
            if (isset($item['batch_id'], $item['quantity'])) {
                assert(is_int($item['batch_id']) || is_string($item['batch_id']));
                assert(is_int($item['quantity']));
                $outboundItems[] = [
                    'batch_id' => (string) $item['batch_id'],
                    'quantity' => $item['quantity'],
                ];
            }
        }

        return $outboundItems;
    }

    /**
     * 构建出库数据.
     *
     * @param array<array{batch_id: string, quantity: int}> $outboundItems
     * @return array{transfer_no: string, to_location: string, items: array<array{batch_id: string, quantity: int}>, operator: string, location_id: string, notes: string}
     */
    private function buildOutboundData(StockTransfer $transfer, array $outboundItems): array
    {
        return [
            'transfer_no' => $transfer->getTransferNo(),
            'to_location' => $transfer->getToLocation(),
            'items' => $outboundItems,
            'operator' => $transfer->getInitiator() ?? 'system',
            'location_id' => $transfer->getFromLocation(),
            'notes' => sprintf('调拨出库到%s', $transfer->getToLocation()),
        ];
    }

    /**
     * 更新调拨单为已发货状态.
     */
    private function updateTransferAsShipped(StockTransfer $transfer): void
    {
        $transfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $transfer->setShippedTime(new \DateTimeImmutable());
    }

    /**
     * 接收调拨.
     *
     * @param array{
     *     received_items: array<array{
     *         batch_id: int,
     *         received_quantity: int
     *     }>,
     *     receiver: string,
     *     notes?: string
     * } $actualReceived
     */
    public function receiveTransfer(StockTransfer $transfer, array $actualReceived): StockTransfer
    {
        $this->validateTransferCanReceive($transfer);

        $inboundItems = $this->prepareInboundItems($transfer, $actualReceived);

        $this->processInboundIfNeeded($transfer, $inboundItems, $actualReceived);
        $this->updateTransferAfterReceive($transfer, $actualReceived);

        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * 如果需要则处理入库.
     *
     * @param array<array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}> $inboundItems
     * @param array{received_items: array<array{batch_id: int, received_quantity: int}>, receiver: string, notes?: string} $actualReceived
     */
    private function processInboundIfNeeded(StockTransfer $transfer, array $inboundItems, array $actualReceived): void
    {
        if ([] !== $inboundItems) {
            $this->executeInboundProcess($transfer, $inboundItems, $actualReceived);
        }
    }

    /**
     * 取消调拨.
     */
    public function cancelTransfer(StockTransfer $transfer, string $reason): StockTransfer
    {
        $this->validateTransferCanCancel($transfer);

        if ($transfer->isInTransit()) {
            $this->processTransferCancellation($transfer);
        }

        $this->updateTransferAsCancelled($transfer, $reason);
        $this->entityManager->flush();

        return $transfer;
    }

    /**
     * 验证调拨单是否可以取消.
     */
    private function validateTransferCanCancel(StockTransfer $transfer): void
    {
        if (!$transfer->isPending() && !$transfer->isInTransit()) {
            throw new InvalidOperationException(sprintf('调拨单%s状态为%s，不能取消', $transfer->getTransferNo(), $transfer->getStatus()->value));
        }
    }

    /**
     * 处理调拨取消的库存恢复.
     */
    private function processTransferCancellation(StockTransfer $transfer): void
    {
        $transferItems = $transfer->getItems();
        $returnItems = $this->prepareReturnItems($transferItems);

        $returnData = [
            'production_order_no' => $transfer->getTransferNo() . '-CANCEL',
            'items' => $returnItems,
            'operator' => $transfer->getInitiator() ?? 'system',
            'location_id' => $transfer->getFromLocation(),
            'notes' => '调拨取消，恢复库存',
        ];

        $this->inboundService->productionInbound($returnData);
    }

    /**
     * 准备退货项目数据.
     *
     * @param array<mixed> $transferItems
     *
     * @return array<array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}>
     */
    private function prepareReturnItems(array $transferItems): array
    {
        $returnItems = [];

        foreach ($transferItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            if ($this->isValidTransferItemForReturn($item)) {
                /** @var array<string, mixed> $item */
                $returnItems[] = $this->createReturnItem($item);
            }
        }

        return $returnItems;
    }

    /**
     * 验证调拨项目是否可用于退货.
     *
     * @param mixed $item
     */
    private function isValidTransferItemForReturn($item): bool
    {
        if (!is_array($item)) {
            return false;
        }

        return isset($item['sku'], $item['batch_no'], $item['quantity'], $item['unit_cost'], $item['quality_level'])
            && $item['sku'] instanceof SKU
            && is_string($item['batch_no'])
            && is_int($item['quantity'])
            && (is_float($item['unit_cost']) || is_int($item['unit_cost']))
            && is_string($item['quality_level']);
    }

    /**
     * 创建退货项目.
     *
     * @param array<string, mixed> $item
     *
     * @return array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}
     */
    private function createReturnItem(array $item): array
    {
        assert(isset($item['sku']) && $item['sku'] instanceof SKU);
        /** @var SKU $sku */
        $sku = $item['sku'];

        assert(isset($item['batch_no']) && is_string($item['batch_no']));
        assert(isset($item['quantity']) && is_int($item['quantity']));
        assert(isset($item['unit_cost']) && (is_float($item['unit_cost']) || is_int($item['unit_cost'])));
        assert(isset($item['quality_level']) && is_string($item['quality_level']));

        return [
            'sku' => $sku,
            'batch_no' => $item['batch_no'],
            'quantity' => $item['quantity'],
            'unit_cost' => (float) $item['unit_cost'],
            'quality_level' => $item['quality_level'],
        ];
    }

    /**
     * 更新调拨单状态为已取消.
     */
    private function updateTransferAsCancelled(StockTransfer $transfer, string $reason): void
    {
        $transfer->setStatus(StockTransferStatus::CANCELLED);

        $metadata = $transfer->getMetadata() ?? [];
        $metadata['cancelled_at'] = (new \DateTime())->format('Y-m-d H:i:s');
        $metadata['cancel_reason'] = $reason;
        $transfer->setMetadata($metadata);
    }

    /**
     * 验证调拨单是否可以接收.
     */
    private function validateTransferCanReceive(StockTransfer $transfer): void
    {
        if (!$transfer->isInTransit()) {
            throw new InvalidOperationException(sprintf('调拨单%s状态为%s，不能接收', $transfer->getTransferNo(), $transfer->getStatus()->value));
        }
    }

    /**
     * 准备入库数据.
     *
     * @param array{received_items: array<array{batch_id: int, received_quantity: int}>, receiver: string, notes?: string} $actualReceived
     *
     * @return array<array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}>
     */
    private function prepareInboundItems(StockTransfer $transfer, array $actualReceived): array
    {
        $transferItems = $transfer->getItems();
        $inboundItems = [];

        foreach ($transferItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $receivedQuantity = $this->findReceivedQuantity($item, $actualReceived);

            if ($receivedQuantity > 0) {
                $inboundItems[] = $this->createInboundItem($item, $transfer->getToLocation(), $receivedQuantity);
            }
        }

        return $inboundItems;
    }

    /**
     * 查找实际接收数量.
     *
     * @param array<string, mixed>                                                                                         $item
     * @param array{received_items: array<array{batch_id: int, received_quantity: int}>, receiver: string, notes?: string} $actualReceived
     */
    private function findReceivedQuantity(array $item, array $actualReceived): int
    {
        foreach ($actualReceived['received_items'] as $receivedItem) {
            assert(is_array($receivedItem));

            if ($this->isBatchMatch($item, $receivedItem)) {
                assert(isset($receivedItem['received_quantity']) && is_int($receivedItem['received_quantity']));

                return $receivedItem['received_quantity'];
            }
        }

        // 默认全部接收
        assert(isset($item['quantity']) && is_int($item['quantity']));

        return $item['quantity'];
    }

    /**
     * 判断批次是否匹配.
     *
     * @param array<string, mixed>                         $item
     * @param array{batch_id: int, received_quantity: int} $receivedItem
     */
    private function isBatchMatch(array $item, array $receivedItem): bool
    {
        return isset($item['batch_id'])
            && isset($receivedItem['batch_id'])
            && $receivedItem['batch_id'] === $item['batch_id'];
    }

    /**
     * 创建入库项目.
     *
     * @param array<string, mixed> $item
     *
     * @return array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}
     */
    private function createInboundItem(array $item, string $toLocation, int $receivedQuantity): array
    {
        assert(isset($item['sku']) && $item['sku'] instanceof SKU);
        assert(isset($item['batch_no']) && is_string($item['batch_no']));
        assert(isset($item['unit_cost']) && (is_float($item['unit_cost']) || is_int($item['unit_cost'])));
        assert(isset($item['quality_level']) && is_string($item['quality_level']));

        return [
            'sku' => $item['sku'],
            'batch_no' => $item['batch_no'] . '-' . $toLocation,
            'quantity' => $receivedQuantity,
            'unit_cost' => (float) $item['unit_cost'],
            'quality_level' => $item['quality_level'],
        ];
    }

    /**
     * 执行入库过程.
     *
     * @param array<array{sku: SKU, batch_no: string, quantity: int, unit_cost: float, quality_level: string}>             $inboundItems
     * @param array{received_items: array<array{batch_id: int, received_quantity: int}>, receiver: string, notes?: string} $actualReceived
     */
    private function executeInboundProcess(StockTransfer $transfer, array $inboundItems, array $actualReceived): void
    {
        $inboundData = [
            'production_order_no' => $transfer->getTransferNo(),
            'items' => $inboundItems,
            'operator' => $actualReceived['receiver'],
            'location_id' => $transfer->getToLocation(),
            'notes' => $actualReceived['notes'] ?? sprintf('调拨入库从%s', $transfer->getFromLocation()),
        ];

        $this->inboundService->productionInbound($inboundData);
    }

    /**
     * 更新调拨单接收后的状态.
     *
     * @param array{received_items: array<array{batch_id: int, received_quantity: int}>, receiver: string, notes?: string} $actualReceived
     */
    private function updateTransferAfterReceive(StockTransfer $transfer, array $actualReceived): void
    {
        $transfer->setStatus(StockTransferStatus::RECEIVED);
        $transfer->setReceivedTime(new \DateTimeImmutable());
        $transfer->setReceiver($actualReceived['receiver']);

        $metadata = $transfer->getMetadata() ?? [];
        $metadata['received_items'] = $actualReceived['received_items'];
        $transfer->setMetadata($metadata);
    }

    /**
     * 获取调拨单状态流转历史.
     *
     * @return array<array<string, mixed>>
     */
    public function getTransferHistory(StockTransfer $transfer): array
    {
        $history = [];

        $history[] = $this->createCreationHistoryItem($transfer);

        $history[] = $this->createShipmentHistoryItem($transfer);

        $history[] = $this->createReceivingHistoryItem($transfer);

        $cancellationItem = $this->createCancellationHistoryItem($transfer);
        if (null !== $cancellationItem) {
            $history[] = $cancellationItem;
        }

        return array_filter($history, fn ($item) => null !== $item);
    }

    /**
     * 创建创建时间历史记录.
     *
     * @return array<string, mixed>
     */
    private function createCreationHistoryItem(StockTransfer $transfer): array
    {
        return [
            'status' => 'pending',
            'timestamp' => $transfer->getCreateTime(),
            'operator' => $transfer->getInitiator(),
            'notes' => '创建调拨单',
        ];
    }

    /**
     * 创建发货时间历史记录.
     *
     * @return array<string, mixed>|null
     */
    private function createShipmentHistoryItem(StockTransfer $transfer): ?array
    {
        if (null === $transfer->getShippedTime()) {
            return null;
        }

        return [
            'status' => 'in_transit',
            'timestamp' => $transfer->getShippedTime(),
            'operator' => $transfer->getInitiator(),
            'notes' => sprintf('从%s发出', $transfer->getFromLocation()),
        ];
    }

    /**
     * 创建接收时间历史记录.
     *
     * @return array<string, mixed>|null
     */
    private function createReceivingHistoryItem(StockTransfer $transfer): ?array
    {
        if (null === $transfer->getReceivedTime()) {
            return null;
        }

        return [
            'status' => 'received',
            'timestamp' => $transfer->getReceivedTime(),
            'operator' => $transfer->getReceiver(),
            'notes' => sprintf('在%s接收', $transfer->getToLocation()),
        ];
    }

    /**
     * 创建取消时间历史记录.
     *
     * @return array<string, mixed>|null
     */
    private function createCancellationHistoryItem(StockTransfer $transfer): ?array
    {
        $metadata = $transfer->getMetadata();
        if (null === $metadata || !isset($metadata['cancelled_at'])) {
            return null;
        }

        assert(is_string($metadata['cancelled_at']));
        $cancelledAt = $metadata['cancelled_at'];
        $cancelReason = isset($metadata['cancel_reason']) && is_string($metadata['cancel_reason']) ? $metadata['cancel_reason'] : '';

        return [
            'status' => 'cancelled',
            'timestamp' => new \DateTime($cancelledAt),
            'operator' => $transfer->getInitiator(),
            'notes' => sprintf('取消原因：%s', $cancelReason),
        ];
    }

    /**
     * 验证调拨数据.
     */
    /**
     * @param array<string, mixed> $data
     */
    private function validateTransferData(array $data): void
    {
        $this->validateBasicTransferFields($data);
        $this->validateTransferItems($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateBasicTransferFields(array $data): void
    {
        $this->validateRequiredField($data, 'transfer_no', '调拨单号不能为空');
        $this->validateRequiredField($data, 'from_location', '源位置不能为空');
        $this->validateRequiredField($data, 'to_location', '目标位置不能为空');
        $this->validateRequiredField($data, 'operator', '操作人不能为空');

        $this->validateLocationDifference(
            $this->ensureString($data['from_location']),
            $this->ensureString($data['to_location'])
        );
    }

    /**
     * 验证必填字段.
     *
     * @param array<string, mixed> $data
     */
    private function validateRequiredField(array $data, string $field, string $errorMessage): void
    {
        if (!isset($data[$field]) || '' === $data[$field]) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * 验证位置不同.
     */
    private function validateLocationDifference(string $fromLocation, string $toLocation): void
    {
        if ($fromLocation === $toLocation) {
            throw new InvalidArgumentException('源位置和目标位置不能相同');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function validateTransferItems(array $data): void
    {
        if (!isset($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('调拨明细不能为空');
        }

        if (!is_array($data['items'])) {
            throw new \InvalidArgumentException('Items must be an array');
        }
        foreach ($data['items'] as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each item must be an array');
            }
            $indexNum = is_int($index) ? $index : 0;
            /** @var array<string, mixed> $item */
            $this->validateSingleTransferItem($item, $indexNum);
        }
    }

    /**
     * 验证单个调拨项目.
     *
     * @param array<string, mixed> $item
     */
    private function validateSingleTransferItem(array $item, int $indexNum): void
    {
        if (!isset($item['batch_id']) || '' === $item['batch_id']) {
            throw new InvalidArgumentException(sprintf('第%d项的批次ID不能为空', $indexNum + 1));
        }

        if (!isset($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException(sprintf('第%d项的数量必须大于0', $indexNum + 1));
        }
    }

    /**
     * 确保值为整数类型.
     */
    private function ensureInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        throw new \InvalidArgumentException(sprintf('Cannot convert value "%s" to integer', gettype($value)));
    }

    /**
     * 确保值为字符串类型.
     */
    private function ensureString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw new \InvalidArgumentException(sprintf('Cannot convert value "%s" to string', gettype($value)));
    }
}
