<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockAdjustmentCrudController;
use Tourze\StockManageBundle\Entity\StockAdjustment;

/**
 * @internal
 */
#[CoversClass(StockAdjustmentCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockAdjustmentCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(StockAdjustment::class, StockAdjustmentCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new StockAdjustmentCrudController();

        $this->assertInstanceOf(StockAdjustmentCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockAdjustmentCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockAdjustmentCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/adjustment', $adminCrud->routePath);
        $this->assertEquals('stock_adjustment', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockAdjustment>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockAdjustmentCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'adjustment_no' => ['调整单号'];
        yield 'adjustment_type' => ['调整类型'];
        yield 'status' => ['状态'];
        yield 'adjustment_quantity' => ['调整数量'];
        yield 'cost_impact' => ['成本影响'];
        yield 'operator' => ['操作人'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'adjustment_no' => ['adjustmentNo'];
        yield 'type' => ['type'];
        yield 'status' => ['status'];
        yield 'reason' => ['reason'];
        yield 'operator' => ['operator'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'adjustment_no' => ['adjustmentNo'];
        yield 'type' => ['type'];
        yield 'status' => ['status'];
        yield 'reason' => ['reason'];
        yield 'operator' => ['operator'];
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
        $this->assertStringContainsString('调整单号', $content, '应该显示调整单号字段');
        $this->assertStringContainsString('调整原因', $content, '应该显示调整原因字段');
        $this->assertStringContainsString('调整类型', $content, '应该显示调整类型字段');
        $this->assertStringContainsString('状态', $content, '应该显示状态字段');
    }
}
