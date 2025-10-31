<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Attribute\MenuProvider;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

#[MenuProvider]
class AdminMenu implements MenuProviderInterface
{
    public function __invoke(ItemInterface $item): void
    {
        $stockMenu = $item->addChild('库存管理', [
            'icon' => 'fas fa-boxes',
        ]);

        // 基础库存管理
        $stockMenu->addChild('库存总览', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockOverviewCrudController'],
        ]);

        $stockMenu->addChild('库存批次', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockBatchCrudController'],
        ]);

        // 出入库管理
        $stockMenu->addChild('库存入库', [
            'route' => 'admin_stock_inbound_wizard',
            'icon' => 'fas fa-plus-circle',
        ]);

        $stockMenu->addChild('入库记录', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockInboundCrudController'],
        ]);

        $stockMenu->addChild('出库记录', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockOutboundCrudController'],
        ]);

        $stockMenu->addChild('库存调拨', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockTransferCrudController'],
        ]);

        // 库存调整与预警
        $stockMenu->addChild('库存调整', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockAdjustmentCrudController'],
        ]);

        $stockMenu->addChild('库存预占', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockReservationCrudController'],
        ]);

        $stockMenu->addChild('库存预警', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockAlertCrudController'],
        ]);

        // 组合商品管理
        $bundleMenu = $item->addChild('组合商品', [
            'icon' => 'fas fa-layer-group',
        ]);

        $bundleMenu->addChild('组合商品', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BundleStockCrudController'],
        ]);

        $bundleMenu->addChild('组合项目', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BundleItemCrudController'],
        ]);

        // 库存锁定管理
        $lockMenu = $item->addChild('库存锁定', [
            'icon' => 'fas fa-lock',
        ]);

        $lockMenu->addChild('业务锁定', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BusinessStockLockCrudController'],
        ]);

        $lockMenu->addChild('操作锁定', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\OperationalStockLockCrudController'],
        ]);

        // 库存监控与分析
        $monitorMenu = $item->addChild('库存监控', [
            'icon' => 'fas fa-chart-line',
        ]);

        $monitorMenu->addChild('库存日志', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockLogCrudController'],
        ]);

        $monitorMenu->addChild('库存快照', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockSnapshotCrudController'],
        ]);

        // 虚拟库存管理
        $virtualMenu = $item->addChild('虚拟库存', [
            'icon' => 'fas fa-cloud',
        ]);

        $virtualMenu->addChild('虚拟库存', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\VirtualStockCrudController'],
        ]);
    }
}
