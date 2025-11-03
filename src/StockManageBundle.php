<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;

class StockManageBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            ProductCoreBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
        ];
    }
}
