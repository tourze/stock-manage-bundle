<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockInboundBundle\Entity\StockInbound;

interface InboundServiceInterface
{
    /**
     * 采购入库.
     *
     * @param array{
     *     purchase_order_no: string,
     *     items: array<array{
     *         sku: SKU,
     *         batch_no: string,
     *         quantity: int,
     *         unit_cost: float,
     *         quality_level: string,
     *         production_date?: \DateTimeInterface,
     *         expiry_date?: \DateTimeInterface
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function purchaseInbound(array $data): StockInbound;

    /**
     * 退货入库.
     *
     * @param array{
     *     return_order_no: string,
     *     items: array<array{
     *         sku: SKU,
     *         batch_no: string,
     *         quantity: int,
     *         quality_level: string
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     */
    public function returnInbound(array $data): StockInbound;

    /**
     * 调拨入库.
     *
     * @param array{
     *     transfer_no: string,
     *     from_location: string,
     *     items: array<array{
     *         batch_id: string,
     *         quantity: int
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     */
    public function transferInbound(array $data): StockInbound;

    /**
     * 生产入库.
     *
     * @param array{
     *     production_order_no: string,
     *     items: array<array{
     *         sku: SKU,
     *         batch_no: string,
     *         quantity: int,
     *         unit_cost: float,
     *         quality_level: string,
     *         production_date?: \DateTimeInterface
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     */
    public function productionInbound(array $data): StockInbound;

    /**
     * 调整入库.
     *
     * @param array<string, mixed> $data
     */
    public function adjustmentInbound(array $data): StockInbound;
}
