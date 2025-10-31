<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;

/**
 * 出库服务 - 重构后的主要接口，协调各个专门的组件.
 *
 * 按照 Linus "Good Taste" 原则重构：
 * 1. 消除了深层嵌套和复杂循环
 * 2. 每个方法职责单一
 * 3. 数据流清晰，无特殊情况
 */
class OutboundService implements OutboundServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OutboundValidator $validator,
        private OutboundProcessor $processor,
    ) {
    }

    /**
     * 销售出库.
     */
    public function salesOutbound(array $data): StockOutbound
    {
        $this->validator->validateSalesOutboundData($data);

        $outbound = $this->createOutbound(StockOutboundType::SALES, $data);
        $result = $this->processor->processSalesOutbound($data);

        $this->finalizeOutbound($outbound, $result);

        return $outbound;
    }

    /**
     * 损耗出库.
     */
    public function damageOutbound(array $data): StockOutbound
    {
        $this->validator->validateDamageOutboundData($data);

        $outbound = $this->createOutbound(StockOutboundType::DAMAGE, $data);
        $result = $this->processor->processDamageOutbound($data);

        $this->finalizeOutbound($outbound, $result);

        return $outbound;
    }

    /**
     * 调拨出库.
     */
    public function transferOutbound(array $data): StockOutbound
    {
        $this->validator->validateTransferOutboundData($data);

        $outbound = $this->createOutbound(StockOutboundType::TRANSFER, $data);

        // 设置调拨特定的元数据
        if (isset($data['to_location'])) {
            $outbound->setMetadata(['to_location' => $data['to_location']]);
        }

        $result = $this->processor->processTransferOutbound($data);

        $this->finalizeOutbound($outbound, $result);

        return $outbound;
    }

    /**
     * 领用出库.
     */
    public function pickOutbound(array $data): StockOutbound
    {
        $this->validator->validatePickOutboundData($data);

        $outbound = $this->createOutbound(StockOutboundType::PICK, $data);

        // 设置领用特定的元数据
        if (isset($data['department'])) {
            $outbound->setMetadata(['department' => $data['department']]);
        }

        $result = $this->processor->processPickOutbound($data);

        $this->finalizeOutbound($outbound, $result);

        return $outbound;
    }

    /**
     * 调整出库.
     *
     * @param array<string, mixed> $data
     */
    public function adjustmentOutbound(array $data): StockOutbound
    {
        $this->validator->validateAdjustmentOutboundData($data);

        $outbound = $this->createOutbound(StockOutboundType::ADJUSTMENT, $data);
        $result = $this->processor->processAdjustmentOutbound($data);

        $this->finalizeOutbound($outbound, $result);

        return $outbound;
    }

    /**
     * 创建出库单基础信息.
     *
     * @param array<string, mixed> $data
     */
    private function createOutbound(StockOutboundType $type, array $data): StockOutbound
    {
        $outbound = new StockOutbound();
        $outbound->setType($type);

        $operator = $data['operator'] ?? null;
        assert(is_string($operator) || null === $operator);
        $outbound->setOperator($operator);

        $locationId = $data['location_id'] ?? null;
        assert(is_string($locationId) || null === $locationId);
        $outbound->setLocationId($locationId);

        $notes = $data['notes'] ?? null;
        assert(is_string($notes) || null === $notes);
        $outbound->setRemark($notes);

        // 根据类型设置参考号
        $referenceNo = match ($type) {
            StockOutboundType::SALES => $data['order_no'],
            StockOutboundType::DAMAGE => $data['damage_no'],
            StockOutboundType::TRANSFER => $data['transfer_no'],
            StockOutboundType::PICK => $data['pick_no'],
            StockOutboundType::ADJUSTMENT => $data['adjustment_no'],
            StockOutboundType::SAMPLE => $data['sample_no'],
        };

        assert(is_string($referenceNo));
        $outbound->setReferenceNo($referenceNo);

        return $outbound;
    }

    /**
     * 完成出库单处理.
     *
     * @param array<string, mixed> $result
     */
    private function finalizeOutbound(StockOutbound $outbound, array $result): void
    {
        $outbound->setRequestedItems(['items' => $result['requestedItems']]);
        $outbound->setAllocatedItems(['items' => $result['allocatedItems']]);

        assert(isset($result['totalQuantity']) && is_int($result['totalQuantity']));
        $outbound->setTotalQuantity($result['totalQuantity']);

        assert(isset($result['totalCost']) && (is_float($result['totalCost']) || is_int($result['totalCost'])));
        $outbound->setTotalCost((string) $result['totalCost']);

        $this->entityManager->persist($outbound);
        $this->entityManager->flush();
    }
}
