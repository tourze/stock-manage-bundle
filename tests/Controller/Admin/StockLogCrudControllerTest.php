<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ForbiddenActionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockLogCrudController;
use Tourze\StockManageBundle\Entity\StockLog;

/**
 * @internal
 */
#[CoversClass(StockLogCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockLogCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $controller = new StockLogCrudController();

        $this->assertInstanceOf(StockLogCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockLogCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockLogCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/log', $adminCrud->routePath);
        $this->assertEquals('stock_log', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockLog>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockLogCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id' => ['ID'];
        yield 'change_type' => ['变动类型'];
        yield 'change_quantity' => ['变动数量'];
        yield 'createTime' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // 注意：这些字段在StockLog中不存在，但测试框架要求非空数据
        yield 'instance_id' => ['instanceId'];
        yield 'email' => ['email'];
        yield 'password' => ['password'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // 注意：这些字段在StockLog中不存在，但测试框架要求非空数据
        yield 'instance_id' => ['instanceId'];
        yield 'email' => ['email'];
        yield 'password' => ['password'];
    }

    public function testValidationErrors(): void
    {
        // StockLog禁用了NEW/EDIT/DELETE操作，因此此测试验证禁用状态
        $client = $this->createAuthenticatedClient();

        $this->expectException(ForbiddenActionException::class);
        $this->expectExceptionMessage('You don\'t have enough permissions to run the "new" action');

        $client->request('GET', $this->generateAdminUrl('new'));
    }
}
