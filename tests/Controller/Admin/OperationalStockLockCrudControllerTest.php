<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\OperationalStockLockCrudController;
use Tourze\StockManageBundle\Entity\OperationalStockLock;

/**
 * @internal
 */
#[CoversClass(OperationalStockLockCrudController::class)]
#[RunTestsInSeparateProcesses]
class OperationalStockLockCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(OperationalStockLock::class, OperationalStockLockCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new OperationalStockLockCrudController();

        $this->assertInstanceOf(OperationalStockLockCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new OperationalStockLockCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(OperationalStockLockCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/operational-lock', $adminCrud->routePath);
        $this->assertEquals('stock_operational_lock', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<OperationalStockLock>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new OperationalStockLockCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        return [
            '操作类型' => ['操作类型'],
            '操作人' => ['操作人'],
            '优先级' => ['优先级'],
            '状态' => ['状态'],
            '锁定原因' => ['锁定原因'],
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
            'operator' => ['operator'],
            'reason' => ['reason'],
            'department' => ['department'],
            'locationId' => ['locationId'],
            'estimatedDuration' => ['estimatedDuration'],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        return [
            'operator' => ['operator'],
            'reason' => ['reason'],
            'department' => ['department'],
            'locationId' => ['locationId'],
            'estimatedDuration' => ['estimatedDuration'],
            'completedBy' => ['completedBy'],
            'completionNotes' => ['completionNotes'],
            'releaseReason' => ['releaseReason'],
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
        $this->assertStringContainsString('操作类型', $content, '应该显示操作类型字段');
        $this->assertStringContainsString('操作人', $content, '应该显示操作人字段');
        $this->assertStringContainsString('锁定原因', $content, '应该显示锁定原因字段');

        // 验证表单元素存在
        $form = $crawler->selectButton('Create')->form();
        $this->assertNotNull($form, '应该有Create表单按钮');

        // 提交空表单验证必填字段
        $client->submit($form, [
            'OperationalStockLock[operator]' => '',
            'OperationalStockLock[reason]' => '',
        ]);
        $this->assertResponseStatusCodeSame(422); // 表单验证失败返回422状态码

        $errorContent = $client->getResponse()->getContent();
        $this->assertIsString($errorContent);

        // 验证必填字段的错误信息
        $this->assertStringContainsString('操作人不能为空', $errorContent, '应该显示operator必填验证错误');
        $this->assertStringContainsString('原因不能为空', $errorContent, '应该显示reason必填验证错误');
    }
}
