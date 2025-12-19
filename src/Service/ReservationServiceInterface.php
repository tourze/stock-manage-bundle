<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Tourze\ProductServiceContracts\SKU;
use Tourze\StockReservationBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\ReservationExpiredException;
use Tourze\StockManageBundle\Exception\ReservationNotFoundException;

interface ReservationServiceInterface
{
    /**
     * 创建库存预留.
     *
     * @param array{
     *     sku: SKU,
     *     quantity: int,
     *     type: string,
     *     business_id: string,
     *     expires_time?: \DateTimeInterface,
     *     batch_ids?: string[],
     *     operator?: string,
     *     notes?: string
     * } $data
     *
     * @throws InsufficientStockException
     */
    public function reserve(array $data): StockReservation;

    /**
     * 确认预留.
     *
     * @throws ReservationNotFoundException
     * @throws ReservationExpiredException
     */
    public function confirm(string $reservationId): void;

    /**
     * 释放预留.
     *
     * @throws ReservationNotFoundException
     */
    public function release(string $reservationId, string $reason = ''): void;

    /**
     * 延长预留过期时间.
     *
     * @throws ReservationNotFoundException
     */
    public function extend(string $reservationId, \DateTimeInterface $newExpiryDate): void;

    /**
     * 释放所有过期预留.
     *
     * @return int 释放的预留数量
     */
    public function releaseExpiredReservations(): int;

    /**
     * 获取指定SKU的活跃预留.
     *
     * @return StockReservation[]
     */
    public function getActiveReservations(SKU $sku): array;

    /**
     * 获取指定SKU的总预留数量.
     */
    public function getReservedQuantity(SKU $sku): int;
}
