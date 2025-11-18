<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\BundleItemCrudController;
use Tourze\StockManageBundle\Entity\BundleItem;

/**
 * @internal
 */
#[CoversClass(BundleItemCrudController::class)]
#[RunTestsInSeparateProcesses]
class BundleItemCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new BundleItemCrudController();

        $this->assertInstanceOf(BundleItemCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new BundleItemCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(BundleItemCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/bundle-item', $adminCrud->routePath);
        $this->assertEquals('stock_bundle_item', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<BundleItem>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new BundleItemCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            '组合商品' => ['组合商品'],
            'SKU' => ['SKU'],
            '数量' => ['数量'],
            '可选项目' => ['可选项目'],
            '创建时间' => ['创建时间'],
            '更新时间' => ['更新时间'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        return [
            'quantity' => ['quantity'],
            'optional' => ['optional'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        return [
            'quantity' => ['quantity'],
            'optional' => ['optional'],
        ];
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
        $this->assertStringContainsString('组合商品', $content, '应该显示组合商品字段');
        $this->assertStringContainsString('SKU', $content, '应该显示SKU字段');
        $this->assertStringContainsString('数量', $content, '应该显示数量字段');

        // 验证表单元素存在
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');

        // 验证必填字段验证: 提交包含quantity=0的表单以测试Positive验证器
        // 注意：quantity字段必须有值（即使是0）以避免数据库NOT NULL约束违规
        $client->submit($form, [
            'BundleItem[quantity]' => '0',
        ]);
        $this->assertResponseStatusCodeSame(422); // 表单验证失败返回422状态码

        $errorContent = $client->getResponse()->getContent();
        $this->assertIsString($errorContent);

        // 验证quantity字段的Positive验证错误消息
        $this->assertStringContainsString('数量必须大于0', $errorContent, '应该显示quantity验证错误');
    }
}
