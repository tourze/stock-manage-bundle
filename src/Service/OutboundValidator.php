<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\StockManageBundle\Exception\InvalidArgumentException;

/**
 * 出库验证器 - 负责出库数据验证
 */
class OutboundValidator
{
    /**
     * 验证销售出库数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateSalesOutboundData(array $data): void
    {
        if (!isset($data['order_no']) || '' === $data['order_no']) {
            throw new InvalidArgumentException('销售单号不能为空');
        }

        if (!isset($data['operator']) || '' === $data['operator']) {
            throw new InvalidArgumentException('操作员不能为空');
        }

        if (!isset($data['items']) || !is_array($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('出库明细不能为空');
        }

        foreach ($data['items'] as $item) {
            assert(is_array($item));
            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $this->validateSalesOutboundItem($typedItem);
        }
    }

    /**
     * 验证损耗出库数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateDamageOutboundData(array $data): void
    {
        if (!isset($data['damage_no']) || '' === $data['damage_no']) {
            throw new InvalidArgumentException('损耗单号不能为空');
        }

        if (!isset($data['operator']) || '' === $data['operator']) {
            throw new InvalidArgumentException('操作员不能为空');
        }

        if (!isset($data['items']) || !is_array($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('损耗明细不能为空');
        }

        foreach ($data['items'] as $item) {
            assert(is_array($item));
            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $this->validateDamageOutboundItem($typedItem);
        }
    }

    /**
     * 验证调拨出库数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateTransferOutboundData(array $data): void
    {
        if (!isset($data['transfer_no']) || '' === $data['transfer_no']) {
            throw new InvalidArgumentException('调拨单号不能为空');
        }

        if (!isset($data['to_location']) || '' === $data['to_location']) {
            throw new InvalidArgumentException('目标位置不能为空');
        }

        if (!isset($data['operator']) || '' === $data['operator']) {
            throw new InvalidArgumentException('操作员不能为空');
        }

        if (!isset($data['items']) || !is_array($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('调拨明细不能为空');
        }

        foreach ($data['items'] as $item) {
            assert(is_array($item));
            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $this->validateTransferOutboundItem($typedItem);
        }
    }

    /**
     * 验证销售出库项目.
     *
     * @param array<string, mixed> $item
     */
    private function validateSalesOutboundItem(array $item): void
    {
        if (!isset($item['sku'])) {
            throw new InvalidArgumentException('出库明细必须包含SKU');
        }

        if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException('出库数量必须是大于0的整数');
        }
    }

    /**
     * 验证损耗出库项目.
     *
     * @param array<string, mixed> $item
     */
    private function validateDamageOutboundItem(array $item): void
    {
        if (!isset($item['batch_id']) || !is_int($item['batch_id']) || $item['batch_id'] <= 0) {
            throw new InvalidArgumentException('批次ID必须是大于0的整数');
        }

        if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException('损耗数量必须是大于0的整数');
        }

        if (!isset($item['reason']) || '' === $item['reason']) {
            throw new InvalidArgumentException('损耗原因不能为空');
        }
    }

    /**
     * 验证领用出库数据.
     *
     * @param array<string, mixed> $data
     */
    public function validatePickOutboundData(array $data): void
    {
        if (!isset($data['pick_no']) || '' === $data['pick_no']) {
            throw new InvalidArgumentException('领用单号不能为空');
        }

        if (!isset($data['department']) || '' === $data['department']) {
            throw new InvalidArgumentException('部门不能为空');
        }

        if (!isset($data['operator']) || '' === $data['operator']) {
            throw new InvalidArgumentException('操作员不能为空');
        }

        if (!isset($data['items']) || !is_array($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('领用明细不能为空');
        }

        foreach ($data['items'] as $item) {
            assert(is_array($item));
            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $this->validatePickOutboundItem($typedItem);
        }
    }

    /**
     * 验证调整出库数据.
     *
     * @param array<string, mixed> $data
     */
    public function validateAdjustmentOutboundData(array $data): void
    {
        if (!isset($data['adjustment_no']) || '' === $data['adjustment_no']) {
            throw new InvalidArgumentException('调整单号不能为空');
        }

        if (!isset($data['operator']) || '' === $data['operator']) {
            throw new InvalidArgumentException('操作员不能为空');
        }

        if (!isset($data['items']) || !is_array($data['items']) || [] === $data['items']) {
            throw new InvalidArgumentException('调整明细不能为空');
        }

        foreach ($data['items'] as $item) {
            assert(is_array($item));
            /** @var array<string, mixed> $typedItem */
            $typedItem = $item;
            $this->validateAdjustmentOutboundItem($typedItem);
        }
    }

    /**
     * 验证调拨出库项目.
     *
     * @param array<string, mixed> $item
     */
    private function validateTransferOutboundItem(array $item): void
    {
        if (!isset($item['batch_id']) || !is_string($item['batch_id']) || '' === $item['batch_id']) {
            throw new InvalidArgumentException('批次ID不能为空');
        }

        if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException('调拨数量必须是大于0的整数');
        }
    }

    /**
     * 验证领用出库项目.
     *
     * @param array<string, mixed> $item
     */
    private function validatePickOutboundItem(array $item): void
    {
        if (!isset($item['sku'])) {
            throw new InvalidArgumentException('领用明细必须包含SKU');
        }

        if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException('领用数量必须是大于0的整数');
        }
    }

    /**
     * 验证调整出库项目.
     *
     * @param array<string, mixed> $item
     */
    private function validateAdjustmentOutboundItem(array $item): void
    {
        if (!isset($item['sku'])) {
            throw new InvalidArgumentException('调整明细必须包含SKU');
        }

        if (!isset($item['quantity']) || !is_int($item['quantity']) || $item['quantity'] <= 0) {
            throw new InvalidArgumentException('调整数量必须是大于0的整数');
        }

        if (!isset($item['reason']) || '' === $item['reason']) {
            throw new InvalidArgumentException('调整原因不能为空');
        }
    }
}
