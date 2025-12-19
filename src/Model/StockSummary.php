<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Model;

final class StockSummary
{
    private string $spuId;

    private int $totalQuantity = 0;

    private int $availableQuantity = 0;

    private int $reservedQuantity = 0;

    private int $lockedQuantity = 0;

    private int $totalBatches = 0;

    private float $totalValue = 0.00;

    /**
     * @var array<int, mixed>
     */
    private array $batches = [];

    public function __construct(string $spuId)
    {
        $this->spuId = $spuId;
    }

    public function getSpuId(): string
    {
        return $this->spuId;
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
    }

    public function setTotalQuantity(int $totalQuantity): void
    {
        $this->totalQuantity = $totalQuantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }

    public function setAvailableQuantity(int $availableQuantity): void
    {
        $this->availableQuantity = $availableQuantity;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(int $reservedQuantity): void
    {
        $this->reservedQuantity = $reservedQuantity;
    }

    public function getLockedQuantity(): int
    {
        return $this->lockedQuantity;
    }

    public function setLockedQuantity(int $lockedQuantity): void
    {
        $this->lockedQuantity = $lockedQuantity;
    }

    public function getTotalBatches(): int
    {
        return $this->totalBatches;
    }

    public function setTotalBatches(int $totalBatches): void
    {
        $this->totalBatches = $totalBatches;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    public function setTotalValue(float $totalValue): void
    {
        $this->totalValue = $totalValue;
    }

    /** @return array<int, mixed> */
    public function getBatches(): array
    {
        return $this->batches;
    }

    /** @param array<int, mixed> $batches */
    public function setBatches(array $batches): void
    {
        $this->batches = $batches;
    }

    /** @param array<string, mixed> $batch */
    public function addBatch(array $batch): void
    {
        $this->batches[] = $batch;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'spuId' => $this->spuId,
            'totalQuantity' => $this->totalQuantity,
            'availableQuantity' => $this->availableQuantity,
            'reservedQuantity' => $this->reservedQuantity,
            'lockedQuantity' => $this->lockedQuantity,
            'totalBatches' => $this->totalBatches,
            'totalValue' => $this->totalValue,
            'averageUnitCost' => $this->totalQuantity > 0 ? $this->totalValue / $this->totalQuantity : 0,
            'batches' => $this->batches,
        ];
    }
}
