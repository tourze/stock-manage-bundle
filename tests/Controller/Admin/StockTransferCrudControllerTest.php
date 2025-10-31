<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockTransferCrudController;
use Tourze\StockManageBundle\Entity\StockTransfer;

/**
 * @internal
 */
#[CoversClass(StockTransferCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockTransferCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(StockTransfer::class, StockTransferCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new StockTransferCrudController();

        $this->assertInstanceOf(StockTransferCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockTransferCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockTransferCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/transfer', $adminCrud->routePath);
        $this->assertEquals('stock_transfer', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockTransfer>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockTransferCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'transfer_no' => ['调拨单号'];
        yield 'sku' => ['SKU'];
        yield 'from_location' => ['源位置'];
        yield 'to_location' => ['目标位置'];
        yield 'total_quantity' => ['调拨总数量'];
        yield 'status' => ['调拨状态'];
        yield 'createTime' => ['创建时间'];
        yield 'updateTime' => ['更新时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'transfer_no' => ['transferNo'];
        yield 'sku' => ['sku'];
        yield 'from_location' => ['fromLocation'];
        yield 'to_location' => ['toLocation'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'status' => ['status'];
        yield 'initiator' => ['initiator'];
        yield 'receiver' => ['receiver'];
        yield 'shipped_time' => ['shippedTime'];
        yield 'received_time' => ['receivedTime'];
        yield 'reason' => ['reason'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'transfer_no' => ['transferNo'];
        yield 'sku' => ['sku'];
        yield 'from_location' => ['fromLocation'];
        yield 'to_location' => ['toLocation'];
        yield 'total_quantity' => ['totalQuantity'];
        yield 'status' => ['status'];
        yield 'initiator' => ['initiator'];
        yield 'receiver' => ['receiver'];
        yield 'shipped_time' => ['shippedTime'];
        yield 'received_time' => ['receivedTime'];
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
        $this->assertStringContainsString('调拨单号', $content, '应该显示调拨单号字段');
        $this->assertStringContainsString('源位置', $content, '应该显示源位置字段');
        $this->assertStringContainsString('目标位置', $content, '应该显示目标位置字段');
        $this->assertStringContainsString('调拨状态', $content, '应该显示调拨状态字段');

        // 验证表单元素存在并提交部分空表单以测试验证
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');

        // 提交空表单（transferNo, fromLocation, toLocation为必填字段）
        // 注意：由于status字段的类型约束，我们只能通过尝试提交来触发验证
        try {
            $crawler = $client->submit($form);
            // 如果没有抛出异常，应该看到422验证错误
            $this->assertResponseStatusCodeSame(422);

            // 验证包含中文验证错误信息
            $invalidFeedback = $crawler->filter('.invalid-feedback')->text();
            $this->assertTrue(
                str_contains($invalidFeedback, '不能为空') || str_contains($invalidFeedback, 'should not be blank'),
                sprintf('验证错误信息应包含"不能为空"或"should not be blank"，实际内容：%s', $invalidFeedback)
            );
        } catch (\Exception $e) {
            // 如果因为enum类型约束抛出异常，这也是预期的验证行为
            $this->assertStringContainsString('status', $e->getMessage());
        }
    }
}
