<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

class InboundService implements InboundServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockBatchRepository $batchRepository,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    /**
     * 采购入库.
     *
     * @param array<string, mixed> $data
     */
    public function purchaseInbound(array $data): StockInbound
    {
        $this->validateInboundData($data, ['purchase_order_no', 'items', 'operator']);

        assert(is_string($data['purchase_order_no']));
        assert(is_string($data['operator']) || null === $data['operator']);
        assert(is_string($data['location_id'] ?? null) || null === ($data['location_id'] ?? null));
        assert(is_string($data['notes'] ?? null) || null === ($data['notes'] ?? null));
        assert(is_array($data['items']));

        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::PURCHASE);
        $inbound->setReferenceNo($data['purchase_order_no']);
        $inbound->setOperator($data['operator']);
        $inbound->setLocationId($data['location_id'] ?? null);
        $inbound->setRemark($data['notes'] ?? null);

        $items = [];
        $totalAmount = 0.0;
        $totalQuantity = 0;

        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $this->validateItem($item, ['sku', 'quantity', 'unit_cost', 'quality_level']);

            // 创建或更新批次
            $batch = $this->createOrUpdateBatch($item, 'purchase');

            assert(is_int($item['quantity']));
            assert(is_float($item['unit_cost']) || is_int($item['unit_cost']));
            assert(is_string($item['quality_level']));

            $itemData = [
                'sku' => $item['sku'],
                'batch_id' => $batch->getId(),
                'batch_no' => $batch->getBatchNo(),
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'amount' => $item['quantity'] * $item['unit_cost'],
                'quality_level' => $item['quality_level'],
            ];

            $items[] = $itemData;
            $totalAmount += $itemData['amount'];
            $totalQuantity += $item['quantity'];
        }

        $inbound->setItems($items);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        // 触发入库事件
        if (null !== $this->eventDispatcher) {
            // 这里可以触发 StockInboundEvent
        }

        return $inbound;
    }

    /**
     * 退货入库.
     *
     * @param array<string, mixed> $data
     */
    public function returnInbound(array $data): StockInbound
    {
        $this->validateInboundData($data, ['return_order_no', 'items', 'operator']);

        assert(is_string($data['return_order_no']));
        assert(is_string($data['operator']) || null === $data['operator']);
        assert(is_string($data['location_id'] ?? null) || null === ($data['location_id'] ?? null));
        assert(is_string($data['notes'] ?? null) || null === ($data['notes'] ?? null));
        assert(is_array($data['items']));

        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::RETURN);
        $inbound->setReferenceNo($data['return_order_no']);
        $inbound->setOperator($data['operator']);
        $inbound->setLocationId($data['location_id'] ?? null);
        $inbound->setRemark($data['notes'] ?? null);

        $items = [];
        $totalAmount = 0.0;
        $totalQuantity = 0;

        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $this->validateItem($item, ['sku', 'batch_no', 'quantity', 'quality_level']);

            assert(is_string($item['batch_no']));
            assert(is_int($item['quantity']));
            assert(is_string($item['quality_level']));

            // 查找原批次或创建新批次
            $batch = $this->batchRepository->findOneBy(['batchNo' => $item['batch_no']]);
            if (null === $batch) {
                // 退货创建新批次，使用原批次号
                $batch = new StockBatch();
                assert($item['sku'] instanceof SKU || null === $item['sku']);
                $batch->setSku($item['sku']);
                $batch->setBatchNo($item['batch_no']);
                $batch->setQuantity(0);
                $batch->setAvailableQuantity(0);
                $batch->setUnitCost(0.0); // 退货成本需要另外计算
                $batch->setQualityLevel($item['quality_level']);
                $batch->setStatus('available');

                $this->entityManager->persist($batch);
            }

            // 增加批次库存
            $batch->setQuantity($batch->getQuantity() + $item['quantity']);
            $batch->setAvailableQuantity($batch->getAvailableQuantity() + $item['quantity']);

            $itemData = [
                'sku' => $item['sku'],
                'batch_id' => $batch->getId(),
                'batch_no' => $batch->getBatchNo(),
                'quantity' => $item['quantity'],
                'unit_cost' => $batch->getUnitCost(),
                'amount' => $item['quantity'] * $batch->getUnitCost(),
                'quality_level' => $item['quality_level'],
            ];

            $items[] = $itemData;
            $totalAmount += $itemData['amount'];
            $totalQuantity += $item['quantity'];
        }

        $inbound->setItems($items);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        return $inbound;
    }

    /**
     * 调拨入库.
     *
     * @param array<string, mixed> $data
     */
    public function transferInbound(array $data): StockInbound
    {
        $this->validateInboundData($data, ['transfer_no', 'from_location', 'items', 'operator']);

        assert(is_string($data['transfer_no']));
        assert(is_string($data['operator']) || null === $data['operator']);
        assert(is_string($data['location_id'] ?? null) || null === ($data['location_id'] ?? null));
        assert(is_string($data['notes'] ?? null) || null === ($data['notes'] ?? null));
        assert(is_array($data['items']));

        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::TRANSFER);
        $inbound->setReferenceNo($data['transfer_no']);
        $inbound->setOperator($data['operator']);
        $inbound->setLocationId($data['location_id'] ?? null);
        $inbound->setRemark($data['notes'] ?? null);

        if (isset($data['from_location'])) {
            $metadata = $inbound->getMetadata() ?? [];
            $metadata['from_location'] = $data['from_location'];
            $inbound->setMetadata($metadata);
        }

        $items = [];
        $totalAmount = 0.0;
        $totalQuantity = 0;

        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $this->validateItem($item, ['batch_id', 'quantity']);

            assert(is_int($item['quantity']));
            assert(is_int($item['batch_id']) || is_string($item['batch_id']));

            $batch = $this->batchRepository->find($item['batch_id']);
            if (null === $batch) {
                $batchIdStr = is_int($item['batch_id']) ? (string) $item['batch_id'] : $item['batch_id'];
                throw new InvalidArgumentException(sprintf('Batch not found: %s', $batchIdStr));
            }

            // 更新批次位置
            if (isset($data['location_id']) && is_string($data['location_id'])) {
                $batch->setLocationId($data['location_id']);
            }

            $itemData = [
                'sku' => $batch->getSku(),
                'batch_id' => $batch->getId(),
                'batch_no' => $batch->getBatchNo(),
                'quantity' => $item['quantity'],
                'unit_cost' => $batch->getUnitCost(),
                'amount' => $item['quantity'] * $batch->getUnitCost(),
                'quality_level' => $batch->getQualityLevel(),
            ];

            $items[] = $itemData;
            $totalAmount += $itemData['amount'];
            $totalQuantity += $item['quantity'];
        }

        $inbound->setItems($items);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        return $inbound;
    }

    /**
     * 生产入库.
     *
     * @param array<string, mixed> $data
     */
    public function productionInbound(array $data): StockInbound
    {
        $this->validateInboundData($data, ['production_order_no', 'items', 'operator']);

        assert(is_string($data['production_order_no']));
        assert(is_string($data['operator']) || null === $data['operator']);
        assert(is_string($data['location_id'] ?? null) || null === ($data['location_id'] ?? null));
        assert(is_string($data['notes'] ?? null) || null === ($data['notes'] ?? null));
        assert(is_array($data['items']));

        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::PRODUCTION);
        $inbound->setReferenceNo($data['production_order_no']);
        $inbound->setOperator($data['operator']);
        $inbound->setLocationId($data['location_id'] ?? null);
        $inbound->setRemark($data['notes'] ?? null);

        $items = [];
        $totalAmount = 0.0;
        $totalQuantity = 0;

        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $this->validateItem($item, ['sku', 'quantity', 'unit_cost', 'quality_level']);

            // 生产入库创建新批次
            $batch = $this->createOrUpdateBatch($item, 'production');

            assert(is_int($item['quantity']));
            assert(is_float($item['unit_cost']) || is_int($item['unit_cost']));
            assert(is_string($item['quality_level']));

            $itemData = [
                'sku' => $item['sku'],
                'batch_id' => $batch->getId(),
                'batch_no' => $batch->getBatchNo(),
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'amount' => $item['quantity'] * $item['unit_cost'],
                'quality_level' => $item['quality_level'],
            ];

            if (isset($item['production_date'])) {
                assert($item['production_date'] instanceof \DateTimeInterface);
                $itemData['production_date'] = $item['production_date']->format('Y-m-d');
            }

            $items[] = $itemData;
            $totalAmount += $itemData['amount'];
            $totalQuantity += $item['quantity'];
        }

        $inbound->setItems($items);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        return $inbound;
    }

    /**
     * 调整入库.
     *
     * @param array<string, mixed> $data
     */
    public function adjustmentInbound(array $data): StockInbound
    {
        $this->validateInboundData($data, ['adjustment_no', 'items', 'operator']);

        assert(is_string($data['adjustment_no']));
        assert(is_string($data['operator']) || null === $data['operator']);
        assert(is_string($data['location_id'] ?? null) || null === ($data['location_id'] ?? null));
        assert(is_string($data['notes'] ?? null) || null === ($data['notes'] ?? null));
        assert(is_array($data['items']));

        $inbound = new StockInbound();
        $inbound->setType(StockInboundType::ADJUSTMENT);
        $inbound->setReferenceNo($data['adjustment_no']);
        $inbound->setOperator($data['operator']);
        $inbound->setLocationId($data['location_id'] ?? null);
        $inbound->setRemark($data['notes'] ?? null);

        $items = [];
        $totalAmount = 0.0;
        $totalQuantity = 0;

        /** @var array<string, mixed> $item */
        foreach ($data['items'] as $item) {
            $this->validateItem($item, ['sku', 'quantity', 'reason']);

            // 调整入库创建新批次
            $batch = $this->createOrUpdateBatch($item, 'adjustment');

            assert(is_int($item['quantity']));
            assert(is_string($item['quality_level'] ?? 'A'));
            assert(is_string($item['reason']));

            $unitCost = $item['unit_cost'] ?? 0.00;
            assert(is_float($unitCost) || is_int($unitCost));

            $itemData = [
                'sku' => $item['sku'],
                'batch_id' => $batch->getId(),
                'batch_no' => $batch->getBatchNo(),
                'quantity' => $item['quantity'],
                'unit_cost' => $unitCost,
                'amount' => $item['quantity'] * $unitCost,
                'quality_level' => $item['quality_level'] ?? 'A',
                'reason' => $item['reason'],
            ];

            $items[] = $itemData;
            $totalAmount += $itemData['amount'];
            $totalQuantity += $item['quantity'];
        }

        $inbound->setItems($items);

        $this->entityManager->persist($inbound);
        $this->entityManager->flush();

        return $inbound;
    }

    /**
     * 创建或更新批次.
     *
     * @param array<string, mixed> $item
     */
    public function createOrUpdateBatch(array $item, string $inboundType = 'purchase'): StockBatch
    {
        // 如果没有提供批次号，自动生成
        if (!isset($item['batch_no']) || '' === $item['batch_no']) {
            $item['batch_no'] = $this->generateUniqueBatchNo($inboundType);
        }

        assert(is_string($item['batch_no']));
        assert(is_int($item['quantity']));

        // 检查批次是否已存在
        $batch = $this->batchRepository->findOneBy(['batchNo' => $item['batch_no']]);

        if (null !== $batch) {
            // 更新现有批次
            $batch->setQuantity($batch->getQuantity() + $item['quantity']);
            $batch->setAvailableQuantity($batch->getAvailableQuantity() + $item['quantity']);

            // 重新计算加权平均成本
            if (isset($item['unit_cost'])) {
                assert(is_float($item['unit_cost']) || is_int($item['unit_cost']));
                $totalValue = ($batch->getQuantity() - $item['quantity']) * $batch->getUnitCost() +
                             $item['quantity'] * $item['unit_cost'];
                $batch->setUnitCost($totalValue / $batch->getQuantity());
            }
        } else {
            // 创建新批次
            $batch = new StockBatch();

            $unitCost = $item['unit_cost'] ?? 0.0;
            assert(is_float($unitCost) || is_int($unitCost));

            $qualityLevel = $item['quality_level'] ?? 'A';
            assert(is_string($qualityLevel));

            assert($item['sku'] instanceof SKU || null === $item['sku']);
            $batch->setSku($item['sku']);
            $batch->setBatchNo($item['batch_no']);
            $batch->setQuantity($item['quantity']);
            $batch->setAvailableQuantity($item['quantity']);
            $batch->setUnitCost($unitCost);
            $batch->setQualityLevel($qualityLevel);
            $batch->setStatus('available');

            if (isset($item['production_date'])) {
                assert($item['production_date'] instanceof \DateTimeImmutable);
                $batch->setProductionDate($item['production_date']);
            }

            if (isset($item['expiry_date'])) {
                assert($item['expiry_date'] instanceof \DateTimeImmutable);
                $batch->setExpiryDate($item['expiry_date']);
            }

            $this->entityManager->persist($batch);
        }

        // 确保批次有 ID，某些操作需要使用 ID
        $this->entityManager->flush();

        return $batch;
    }

    /**
     * 验证入库数据.
     *
     * @param array<string, mixed> $data
     * @param array<string>        $requiredFields
     */
    private function validateInboundData(array $data, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException(sprintf('Missing required field: %s', $field));
            }
        }

        if (isset($data['items']) && !is_array($data['items'])) {
            throw new InvalidArgumentException('Items must be an array');
        }

        if (isset($data['items']) && [] === $data['items']) {
            throw new InvalidArgumentException('Items cannot be empty');
        }
    }

    /**
     * 验证入库项目.
     *
     * @param array<string, mixed> $item
     * @param array<string>        $requiredFields
     */
    private function validateItem(array $item, array $requiredFields): void
    {
        foreach ($requiredFields as $field) {
            if (!isset($item[$field])) {
                throw new InvalidArgumentException(sprintf('Missing required field in item: %s', $field));
            }
        }

        if (isset($item['quantity']) && $item['quantity'] <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than 0');
        }

        if (isset($item['unit_cost']) && $item['unit_cost'] < 0) {
            throw new InvalidArgumentException('Unit cost cannot be negative');
        }
    }

    /**
     * 生成批次号.
     */
    public function generateBatchNo(string $type): string
    {
        $prefix = match ($type) {
            'purchase', 'PURCHASE' => 'PUR',
            'production', 'PRODUCTION' => 'PROD',
            'return', 'RETURN' => 'RET',
            'transfer', 'TRANSFER' => 'TRF',
            'adjustment', 'ADJUSTMENT' => 'ADJ',
            default => 'BATCH',
        };

        $date = date('Ymd');
        $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

        return sprintf('%s-%s-%s', $prefix, $date, $random);
    }

    /**
     * 检查批次号是否存在.
     */
    public function isBatchNoExists(string $batchNo): bool
    {
        $batch = $this->batchRepository->findOneBy(['batchNo' => $batchNo]);

        return null !== $batch;
    }

    /**
     * 生成唯一批次号.
     */
    public function generateUniqueBatchNo(string $type, int $maxAttempts = 10): string
    {
        for ($i = 0; $i < $maxAttempts; ++$i) {
            $batchNo = $this->generateBatchNo($type);

            if (!$this->isBatchNoExists($batchNo)) {
                return $batchNo;
            }
        }

        throw new InvalidArgumentException('Unable to generate unique batch number after multiple attempts');
    }
}
