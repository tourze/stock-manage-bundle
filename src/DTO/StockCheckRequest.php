<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class StockCheckRequest
{
    #[Assert\NotNull(message: '商品ID不能为空')]
    #[Assert\Positive(message: '商品ID必须为正整数')]
    public int $productId;

    #[Assert\NotNull(message: 'SKU ID不能为空')]
    #[Assert\Positive(message: 'SKU ID必须为正整数')]
    public int $skuId;

    #[Assert\NotNull(message: '数量不能为空')]
    #[Assert\Positive(message: '数量必须为正整数')]
    public int $quantity;

    public function __construct(int $productId = 0, int $skuId = 0, int $quantity = 0)
    {
        $this->productId = $productId;
        $this->skuId = $skuId;
        $this->quantity = $quantity;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'skuId' => $this->skuId,
            'quantity' => $this->quantity,
        ];
    }

    public function isValid(): bool
    {
        return $this->productId > 0 && $this->skuId > 0 && $this->quantity > 0;
    }
}
