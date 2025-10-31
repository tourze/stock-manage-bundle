<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Exception\InsufficientStockException;

interface OutboundServiceInterface
{
    /**
     * 销售出库.
     *
     * @param array{
     *     order_no: string,
     *     items: array<array{
     *         sku: SKU,
     *         quantity: int,
     *         allocation_strategy?: string
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     *
     * @throws InsufficientStockException
     * @throws \InvalidArgumentException
     */
    public function salesOutbound(array $data): StockOutbound;

    /**
     * 损耗出库.
     *
     * @param array{
     *     damage_no: string,
     *     items: array<array{
     *         batch_id: string,
     *         quantity: int,
     *         reason: string
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     *
     * @throws InsufficientStockException
     */
    public function damageOutbound(array $data): StockOutbound;

    /**
     * 调拨出库.
     *
     * @param array{
     *     transfer_no: string,
     *     to_location: string,
     *     items: array<array{
     *         batch_id: string,
     *         quantity: int
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     *
     * @throws InsufficientStockException
     */
    public function transferOutbound(array $data): StockOutbound;

    /**
     * 领用出库.
     *
     * @param array{
     *     pick_no: string,
     *     department: string,
     *     items: array<array{
     *         sku: SKU,
     *         quantity: int,
     *         purpose?: string
     *     }>,
     *     operator: string,
     *     location_id?: string,
     *     notes?: string
     * } $data
     *
     * @throws InsufficientStockException
     */
    public function pickOutbound(array $data): StockOutbound;

    /**
     * 调整出库.
     *
     * @param array<string, mixed> $data
     *
     * @throws InsufficientStockException
     */
    public function adjustmentOutbound(array $data): StockOutbound;
}
