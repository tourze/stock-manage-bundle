<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Event;

use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * 库存事件接口
 * 定义所有库存相关事件的基础结构.
 */
interface StockEventInterface
{
    /**
     * 获取事件类型.
     */
    public function getEventType(): string;

    /**
     * 获取库存批次
     */
    public function getStockBatch(): StockBatch;

    /**
     * 获取SPU ID.
     */
    public function getSpuId(): string;

    /**
     * 获取数量.
     */
    public function getQuantity(): int;

    /**
     * 获取操作人.
     */
    public function getOperator(): ?string;

    /**
     * 获取发生时间.
     */
    public function getOccurredTime(): \DateTimeInterface;

    /**
     * 获取元数据.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array;

    /**
     * 转换为数组.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
