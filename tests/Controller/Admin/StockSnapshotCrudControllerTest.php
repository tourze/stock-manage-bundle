<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockSnapshotCrudController;
use Tourze\StockManageBundle\Entity\StockSnapshot;

/**
 * @internal
 */
#[CoversClass(StockSnapshotCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockSnapshotCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(StockSnapshot::class, StockSnapshotCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new StockSnapshotCrudController();

        $this->assertInstanceOf(StockSnapshotCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockSnapshotCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockSnapshotCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/snapshot', $adminCrud->routePath);
        $this->assertEquals('stock_snapshot', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockSnapshot>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockSnapshotCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'snapshot_no' => ['快照号'];
        yield 'snapshot_type' => ['快照类型'];
        yield 'trigger_method' => ['触发方式'];
        yield 'total_quantity' => ['总数量'];
        yield 'total_value' => ['总价值'];
        yield 'product_count' => ['商品数量'];
        yield 'batch_count' => ['批次数量'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'snapshot_no' => ['snapshotNo'];
        yield 'type' => ['type'];
        yield 'trigger_method' => ['triggerMethod'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'snapshot_no' => ['snapshotNo'];
        yield 'type' => ['type'];
        yield 'trigger_method' => ['triggerMethod'];
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
        $this->assertStringContainsString('快照号', $content, '应该显示快照号字段');
        $this->assertStringContainsString('快照类型', $content, '应该显示快照类型字段');
        $this->assertStringContainsString('触发方式', $content, '应该显示触发方式字段');

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
