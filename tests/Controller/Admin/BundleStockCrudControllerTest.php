<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\BundleStockCrudController;
use Tourze\StockManageBundle\Entity\BundleStock;

/**
 * @internal
 */
#[CoversClass(BundleStockCrudController::class)]
#[RunTestsInSeparateProcesses]
class BundleStockCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new BundleStockCrudController();

        $this->assertInstanceOf(BundleStockCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new BundleStockCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(BundleStockCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/bundle-stock', $adminCrud->routePath);
        $this->assertEquals('stock_bundle_stock', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<BundleStock>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new BundleStockCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'bundle_code' => ['组合商品编码'];
        yield 'bundle_name' => ['组合商品名称'];
        yield 'type' => ['组合类型'];
        yield 'status' => ['状态'];
        yield 'items_count' => ['项目总数'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'bundle_code' => ['bundleCode'];
        yield 'bundle_name' => ['bundleName'];
        yield 'type' => ['type'];
        yield 'status' => ['status'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'bundle_code' => ['bundleCode'];
        yield 'bundle_name' => ['bundleName'];
        yield 'type' => ['type'];
        yield 'status' => ['status'];
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
        $this->assertStringContainsString('组合商品编码', $content, '应该显示组合商品编码字段');
        $this->assertStringContainsString('组合商品名称', $content, '应该显示组合商品名称字段');
        $this->assertStringContainsString('组合类型', $content, '应该显示组合类型字段');

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
