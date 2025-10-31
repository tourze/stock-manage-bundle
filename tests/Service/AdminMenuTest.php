<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;
use Tourze\StockManageBundle\Service\AdminMenu;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊设置，AdminMenu是无状态的服务类
    }

    public function testInvokeCreatesMenuStructure(): void
    {
        $menu = self::getService(AdminMenu::class);

        // Create mock menu items
        $stockMenu = $this->createMock(ItemInterface::class);
        $bundleMenu = $this->createMock(ItemInterface::class);
        $lockMenu = $this->createMock(ItemInterface::class);
        $monitorMenu = $this->createMock(ItemInterface::class);
        $virtualMenu = $this->createMock(ItemInterface::class);
        $rootItem = $this->createMock(ItemInterface::class);

        // Track which menu we're adding to
        $menuCall = 0;

        // Set up expectations for root item - 5 top-level menus
        $rootItem->expects($this->exactly(5))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($stockMenu, $bundleMenu, $lockMenu, $monitorMenu, $virtualMenu, &$menuCall) {
                ++$menuCall;
                switch ($menuCall) {
                    case 1:
                        $this->assertEquals('库存管理', $name);
                        $this->assertEquals(['icon' => 'fas fa-boxes'], $options);

                        return $stockMenu;
                    case 2:
                        $this->assertEquals('组合商品', $name);
                        $this->assertEquals(['icon' => 'fas fa-layer-group'], $options);

                        return $bundleMenu;
                    case 3:
                        $this->assertEquals('库存锁定', $name);
                        $this->assertEquals(['icon' => 'fas fa-lock'], $options);

                        return $lockMenu;
                    case 4:
                        $this->assertEquals('库存监控', $name);
                        $this->assertEquals(['icon' => 'fas fa-chart-line'], $options);

                        return $monitorMenu;
                    case 5:
                        $this->assertEquals('虚拟库存', $name);
                        $this->assertEquals(['icon' => 'fas fa-cloud'], $options);

                        return $virtualMenu;
                    default:
                        self::fail('Unexpected menu: ' . (string) $name);
                }
            })
        ;

        // Set up expectations for stock menu - 9 items
        $stockMenu->expects($this->exactly(9))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($stockMenu) {
                $expectedItems = [
                    '库存总览' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockOverviewCrudController'],
                    ],
                    '库存批次' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockBatchCrudController'],
                    ],
                    '库存入库' => [
                        'route' => 'admin_stock_inbound_wizard',
                        'icon' => 'fas fa-plus-circle',
                    ],
                    '入库记录' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockInboundCrudController'],
                    ],
                    '出库记录' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockOutboundCrudController'],
                    ],
                    '库存调拨' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockTransferCrudController'],
                    ],
                    '库存调整' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockAdjustmentCrudController'],
                    ],
                    '库存预占' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockReservationCrudController'],
                    ],
                    '库存预警' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockAlertCrudController'],
                    ],
                ];
                $this->assertArrayHasKey($name, $expectedItems);
                $this->assertEquals($expectedItems[$name], $options);

                return $stockMenu;
            })
        ;

        // Set up expectations for bundle menu - 2 items
        $bundleMenu->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($bundleMenu) {
                $expectedItems = [
                    '组合商品' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BundleStockCrudController'],
                    ],
                    '组合项目' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BundleItemCrudController'],
                    ],
                ];
                $this->assertArrayHasKey($name, $expectedItems);
                $this->assertEquals($expectedItems[$name], $options);

                return $bundleMenu;
            })
        ;

        // Set up expectations for lock menu - 2 items
        $lockMenu->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($lockMenu) {
                $expectedItems = [
                    '业务锁定' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\BusinessStockLockCrudController'],
                    ],
                    '操作锁定' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\OperationalStockLockCrudController'],
                    ],
                ];
                $this->assertArrayHasKey($name, $expectedItems);
                $this->assertEquals($expectedItems[$name], $options);

                return $lockMenu;
            })
        ;

        // Set up expectations for monitor menu - 2 items
        $monitorMenu->expects($this->exactly(2))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($monitorMenu) {
                $expectedItems = [
                    '库存日志' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockLogCrudController'],
                    ],
                    '库存快照' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\StockSnapshotCrudController'],
                    ],
                ];
                $this->assertArrayHasKey($name, $expectedItems);
                $this->assertEquals($expectedItems[$name], $options);

                return $monitorMenu;
            })
        ;

        // Set up expectations for virtual menu - 1 item
        $virtualMenu->expects($this->exactly(1))
            ->method('addChild')
            ->willReturnCallback(function ($name, $options) use ($virtualMenu) {
                $expectedItems = [
                    '虚拟库存' => [
                        'route' => 'admin',
                        'routeParameters' => ['crudAction' => 'index', 'crudControllerFqcn' => 'Tourze\StockManageBundle\Controller\Admin\VirtualStockCrudController'],
                    ],
                ];
                $this->assertArrayHasKey($name, $expectedItems);
                $this->assertEquals($expectedItems[$name], $options);

                return $virtualMenu;
            })
        ;

        // Execute the menu creation
        $menu($rootItem);
    }

    public function testMenuCanBeInstantiated(): void
    {
        $menu = self::getService(AdminMenu::class);
        $this->assertInstanceOf(AdminMenu::class, $menu);
    }

    public function testMenuIsCallable(): void
    {
        $menu = self::getService(AdminMenu::class);
        $this->assertIsCallable($menu);
    }
}
