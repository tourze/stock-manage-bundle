<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Attribute\MenuProvider;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

#[MenuProvider]
final class AdminMenu implements MenuProviderInterface
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

        // 出入库/预占/调拨/组合商品/调整/锁定等扩展能力均已拆分到独立 Bundle，由各自 Bundle 提供菜单

        // 库存监控与分析
        $monitorMenu = $item->addChild('库存监控', [
            'icon' => 'fas fa-chart-line',
        ]);

        $monitorMenu->addChild('库存日志', [
            'route' => 'admin',
            'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockLogCrudController'],
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
