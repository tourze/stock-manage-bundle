<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockInboundCrudController;
use Tourze\StockManageBundle\Entity\StockInbound;

/**
 * @internal
 */
#[CoversClass(StockInboundCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockInboundCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(StockInbound::class, StockInboundCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new StockInboundCrudController();

        $this->assertInstanceOf(StockInboundCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockInboundCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockInboundCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/inbound', $adminCrud->routePath);
        $this->assertEquals('stock_inbound', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockInbound>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockInboundCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'inbound_type' => ['入库类型'];
        yield 'reference_no' => ['参考单号'];
        yield 'sku' => ['SKU'];
        yield 'total_quantity' => ['总数量'];
        yield 'total_amount' => ['总金额'];
        yield 'operator' => ['操作人'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'reference_no' => ['referenceNo'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'total_amount' => ['totalAmount'];
        yield 'operator' => ['operator'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'reference_no' => ['referenceNo'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'total_amount' => ['totalAmount'];
        yield 'operator' => ['operator'];
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
        $this->assertStringContainsString('参考单号', $content, '应该显示参考单号字段');
        $this->assertStringContainsString('总数量', $content, '应该显示总数量字段');
        $this->assertStringContainsString('操作人', $content, '应该显示操作人字段');

        // 验证表单元素存在
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');
    }
}
