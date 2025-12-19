<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

final class StockManageExtension extends AutoExtension
{
    public function getAlias(): string
    {
        return 'stock_manage';
    }

    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
