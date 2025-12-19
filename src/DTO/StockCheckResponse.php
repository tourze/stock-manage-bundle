<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DTO;

final readonly class StockCheckResponse
{
    public function __construct(
        public int $productId,
        public int $skuId,
        public bool $available,
        public int $currentStock,
        public int $requestedQuantity = 0,
        public ?string $message = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'skuId' => $this->skuId,
            'available' => $this->available,
            'currentStock' => $this->currentStock,
            'requestedQuantity' => $this->requestedQuantity,
            'message' => $this->message,
            'shortage' => $this->getShortage(),
        ];
    }

    public function getShortage(): int
    {
        if ($this->available) {
            return 0;
        }

        return max(0, $this->requestedQuantity - $this->currentStock);
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function getAvailabilityMessage(): string
    {
        if ($this->available) {
            return '库存充足';
        }

        if ($this->currentStock <= 0) {
            return '库存不足，当前库存为0';
        }

        return sprintf(
            '库存不足，需要%d个，当前仅有%d个，还缺%d个',
            $this->requestedQuantity,
            $this->currentStock,
            $this->getShortage()
        );
    }
}
