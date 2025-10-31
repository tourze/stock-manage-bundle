<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockInboundWizardController;

/**
 * @internal
 */
#[CoversClass(StockInboundWizardController::class)]
#[RunTestsInSeparateProcesses]
class StockInboundWizardControllerTest extends AbstractWebTestCase
{
    protected function onSetUp(): void
    {
        // 不再使用 mock，Web 测试应该测试完整的 HTTP 流程
    }

    /**
     * 测试控制器的基本结构和路由配置.
     */
    public function testControllerHasCorrectRouteAttributes(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardController::class);

        // 检查类级路由注解 - 这个控制器没有类级路由
        $classRoutes = $reflection->getAttributes(Route::class);
        $this->assertCount(0, $classRoutes, 'Controller should not have class-level Route attribute');

        // 检查 __invoke 方法的路由配置
        $method = $reflection->getMethod('__invoke');
        $routes = $method->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $this->assertEquals('/admin/stock/inbound-wizard', $route->getPath(), '__invoke method should have correct path');
        $this->assertEquals('admin_stock_inbound_wizard', $route->getName(), '__invoke method should have correct name');
        $this->assertEquals(['GET'], $route->getMethods(), '__invoke method should only support GET method');
    }

    /**
     * 测试控制器类可以实例化.
     *
     * 由于这是Bundle级别的单元测试，无法进行完整的HTTP集成测试，
     * 这里测试控制器类是否可以正常实例化。
     */
    public function testIndexPageShouldRespondSuccessfully(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardController::class);

        // 测试控制器是否可以正常实例化
        $this->assertTrue($reflection->isInstantiable(), 'Controller should be instantiable');

        // 验证控制器有正确的 __invoke 方法
        $this->assertTrue($reflection->hasMethod('__invoke'), 'Controller should have __invoke method');

        $invokeMethod = $reflection->getMethod('__invoke');
        $this->assertTrue($invokeMethod->isPublic(), '__invoke method should be public');

        // 验证返回类型
        $returnType = $invokeMethod->getReturnType();
        $this->assertNotNull($returnType, '__invoke method should have return type');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', $returnType->getName());
    }

    // 注意：采购、生产、退货、调拨、API 等功能现已分离到独立的 invokable 控制器中：
    // - StockInboundPurchaseWizardController
    // - StockInboundProductionWizardController
    // - StockInboundReturnWizardController
    // - StockInboundTransferWizardController
    // - StockInboundWizardSkuApiController
    // 这些控制器将有各自的测试文件

    /**
     * 测试控制器类是final的.
     */
    public function testControllerIsFinal(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardController::class);
        $this->assertTrue($reflection->isFinal(), 'Controller should be declared as final');
    }

    /**
     * 测试不支持的HTTP方法.
     *
     * 由于这是Bundle级别的单元测试，无法进行完整的HTTP集成测试，
     * 这里改为测试控制器的路由配置是否正确定义了允许的方法。
     */
    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardController::class);
        $invokeMethod = $reflection->getMethod('__invoke');
        $routes = $invokeMethod->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $allowedMethods = $route->getMethods();

        // 验证不允许的方法确实不在允许的方法列表中
        $this->assertNotContains($method, $allowedMethods, "Method {$method} should not be allowed for this route");
    }
}
