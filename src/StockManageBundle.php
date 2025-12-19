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
        $dependencies = [
            DoctrineBundle::class => ['all' => true],
            ProductCoreBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
        ];

        // 可插拔扩展：仅在安装对应包后才自动启用
        $optionalBundles = [
            \Tourze\StockInboundBundle\StockInboundBundle::class,
            \Tourze\StockOutboundBundle\StockOutboundBundle::class,
            \Tourze\StockReservationBundle\StockReservationBundle::class,
            \Tourze\StockTransferBundle\StockTransferBundle::class,
            \Tourze\StockBundleBundle\StockBundleBundle::class,
            \Tourze\StockAdjustmentBundle\StockAdjustmentBundle::class,
            \Tourze\StockLockBundle\StockLockBundle::class,
            \Tourze\StockAlertBundle\StockAlertBundle::class,
            \Tourze\StockSnapshotBundle\StockSnapshotBundle::class,
            \Tourze\StockCostBundle\StockCostBundle::class,
        ];

        foreach ($optionalBundles as $bundleClass) {
            if (class_exists($bundleClass)) {
                $dependencies[$bundleClass] = ['all' => true];
            }
        }

        return $dependencies;
    }
}
