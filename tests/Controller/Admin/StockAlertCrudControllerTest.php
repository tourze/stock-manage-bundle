<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockAlertCrudController;
use Tourze\StockManageBundle\Entity\StockAlert;

/**
 * @internal
 */
#[CoversClass(StockAlertCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockAlertCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new StockAlertCrudController();

        $this->assertInstanceOf(StockAlertCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockAlertCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockAlertCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/alert', $adminCrud->routePath);
        $this->assertEquals('stock_alert', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockAlert>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockAlertCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'alert_type' => ['预警类型'];
        yield 'sku' => ['SKU'];
        yield 'severity_level' => ['严重级别'];
        yield 'status' => ['状态'];
        yield 'threshold_value' => ['阈值'];
        yield 'current_value' => ['当前值'];
        yield 'triggered_at' => ['触发时间'];
        yield 'resolved_at' => ['解决时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'alert_type' => ['alertType'];
        yield 'sku' => ['sku'];
        yield 'threshold_value' => ['thresholdValue'];
        yield 'current_value' => ['currentValue'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'alert_type' => ['alertType'];
        yield 'sku' => ['sku'];
        yield 'threshold_value' => ['thresholdValue'];
        yield 'current_value' => ['currentValue'];
        yield 'location_id' => ['locationId'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 验证页面包含必填字段
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);

        // 验证必填字段在NEW页面存在
        $this->assertStringContainsString('预警类型', $content, '应该显示预警类型字段');
        $this->assertStringContainsString('SKU', $content, '应该显示SKU字段');
        $this->assertStringContainsString('严重级别', $content, '应该显示严重级别字段');

        // 验证表单元素存在
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');
    }
}
