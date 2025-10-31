<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\BusinessStockLockCrudController;
use Tourze\StockManageBundle\Entity\BusinessStockLock;

/**
 * @internal
 */
#[CoversClass(BusinessStockLockCrudController::class)]
#[RunTestsInSeparateProcesses]
class BusinessStockLockCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(BusinessStockLock::class, BusinessStockLockCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new BusinessStockLockCrudController();

        $this->assertInstanceOf(BusinessStockLockCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new BusinessStockLockCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(BusinessStockLockCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/business-lock', $adminCrud->routePath);
        $this->assertEquals('stock_business_lock', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<BusinessStockLock>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new BusinessStockLockCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'type' => ['锁定类型'];
        yield 'business_id' => ['业务ID'];
        yield 'status' => ['锁定状态'];
        yield 'reason' => ['锁定原因'];
        yield 'total_quantity' => ['锁定总数量'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'type' => ['type'];
        yield 'business_id' => ['businessId'];
        yield 'status' => ['status'];
        yield 'reason' => ['reason'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'type' => ['type'];
        yield 'business_id' => ['businessId'];
        yield 'status' => ['status'];
        yield 'reason' => ['reason'];
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
        $this->assertStringContainsString('锁定类型', $content, '应该显示锁定类型字段');
        $this->assertStringContainsString('业务ID', $content, '应该显示业务ID字段');
        $this->assertStringContainsString('锁定原因', $content, '应该显示锁定原因字段');

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
