<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Param;

use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class CheckStockAvailabilityParam implements RpcParamInterface
{
    /**
     * @param array<array{productId: int, skuId: int, quantity: int}> $items
     */
    public function __construct(
        #[MethodParam(description: '库存检查项目列表')]
        public array $items = [],
    ) {
    }
}
