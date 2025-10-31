<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\VirtualStockCrudController;
use Tourze\StockManageBundle\Entity\VirtualStock;

/**
 * @internal
 */
#[CoversClass(VirtualStockCrudController::class)]
#[RunTestsInSeparateProcesses]
class VirtualStockCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(VirtualStock::class, VirtualStockCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new VirtualStockCrudController();

        $this->assertInstanceOf(VirtualStockCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new VirtualStockCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(VirtualStockCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/virtual', $adminCrud->routePath);
        $this->assertEquals('stock_virtual', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<VirtualStock>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new VirtualStockCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id' => ['ID'];
        yield 'sku' => ['SKU'];
        yield 'virtual_type' => ['虚拟库存类型'];
        yield 'virtual_quantity' => ['虚拟库存数量'];
        yield 'status' => ['状态'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'sku' => ['sku'];
        yield 'virtual_type' => ['virtualType'];
        yield 'quantity' => ['quantity'];
        yield 'status' => ['status'];
        yield 'expected_date' => ['expectedDate'];
        yield 'business_id' => ['businessId'];
        yield 'location_id' => ['locationId'];
        yield 'description' => ['description'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'virtual_type' => ['virtualType'];
        yield 'quantity' => ['quantity'];
        yield 'status' => ['status'];
        yield 'expected_date' => ['expectedDate'];
        yield 'business_id' => ['businessId'];
        yield 'location_id' => ['locationId'];
        yield 'description' => ['description'];
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
        $this->assertStringContainsString('虚拟库存类型', $content, '应该显示虚拟库存类型字段');
        $this->assertStringContainsString('虚拟库存数量', $content, '应该显示虚拟库存数量字段');
        $this->assertStringContainsString('状态', $content, '应该显示状态字段');

        // 验证表单元素存在并提交空表单验证错误
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');

        // 提交空表单验证错误信息
        $crawler = $client->submit($form);
        $this->assertResponseStatusCodeSame(422);

        // 验证包含中文验证错误信息（该项目使用中文验证消息）
        $invalidFeedback = $crawler->filter('.invalid-feedback')->text();
        $this->assertTrue(
            str_contains($invalidFeedback, '不能为空') || str_contains($invalidFeedback, 'should not be blank'),
            sprintf('验证错误信息应包含"不能为空"或"should not be blank"，实际内容：%s', $invalidFeedback)
        );
    }
}
