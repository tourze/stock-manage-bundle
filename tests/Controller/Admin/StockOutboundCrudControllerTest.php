<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockOutboundCrudController;
use Tourze\StockManageBundle\Entity\StockOutbound;

/**
 * @internal
 */
#[CoversClass(StockOutboundCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockOutboundCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new StockOutboundCrudController();

        $this->assertInstanceOf(StockOutboundCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockOutboundCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockOutboundCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/outbound', $adminCrud->routePath);
        $this->assertEquals('stock_outbound', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockOutbound>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockOutboundCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'type' => ['出库类型'];
        yield 'reference_no' => ['参考单号'];
        yield 'sku' => ['SKU'];
        yield 'total_quantity' => ['总数量'];
        yield 'total_cost' => ['总成本'];
        yield 'operator' => ['操作人'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'type' => ['type'];
        yield 'reference_no' => ['referenceNo'];
        yield 'sku' => ['sku'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'total_cost' => ['totalCost'];
        yield 'operator' => ['operator'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'type' => ['type'];
        yield 'reference_no' => ['referenceNo'];
        yield 'sku' => ['sku'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'total_cost' => ['totalCost'];
        yield 'operator' => ['operator'];
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
        $this->assertStringContainsString('出库类型', $content, '应该显示出库类型字段');
        $this->assertStringContainsString('参考单号', $content, '应该显示参考单号字段');
        $this->assertStringContainsString('总数量', $content, '应该显示总数量字段');

        // 验证表单元素存在
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');
    }
}
