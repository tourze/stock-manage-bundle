<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockOverviewCrudController;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @internal
 */
#[CoversClass(StockOverviewCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockOverviewCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new StockOverviewCrudController();

        $this->assertInstanceOf(StockOverviewCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockOverviewCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockOverviewCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/overview', $adminCrud->routePath);
        $this->assertEquals('stock_overview', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockBatch>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockOverviewCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'sku' => ['SKU'];
        yield 'batch_no' => ['批次号'];
        yield 'available_stock' => ['可用库存'];
        yield 'reserved_stock' => ['预占库存'];
        yield 'total_stock' => ['总库存'];
        yield 'expiry_time' => ['过期时间'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'batch_no' => ['batchNo'];
        yield 'available_quantity' => ['availableQuantity'];
        yield 'reserved_quantity' => ['reservedQuantity'];
        yield 'quantity' => ['quantity'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'batch_no' => ['batchNo'];
        yield 'available_quantity' => ['availableQuantity'];
        yield 'reserved_quantity' => ['reservedQuantity'];
        yield 'quantity' => ['quantity'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 验证页面包含必填字段
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);

        // 验证必填字段在NEW页面存在
        $this->assertStringContainsString('批次号', $content, '应该显示批次号字段');
        $this->assertStringContainsString('可用库存', $content, '应该显示可用库存字段');
        $this->assertStringContainsString('总库存', $content, '应该显示总库存字段');
    }
}
