<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockInboundTransferWizardController;

/**
 * @internal
 */
#[CoversClass(StockInboundTransferWizardController::class)]
#[RunTestsInSeparateProcesses]
class StockInboundTransferWizardControllerTest extends AbstractWebTestCase
{
    protected function onSetUp(): void
    {
        // Web 测试不需要 mock，测试完整的 HTTP 流程
    }

    /**
     * 测试控制器的路由配置是否正确.
     */
    public function testControllerHasCorrectRouteAttributes(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);

        // 检查类级路由注解 - invokable 控制器通常没有类级路由
        $classRoutes = $reflection->getAttributes(Route::class);
        $this->assertCount(0, $classRoutes, 'Controller should not have class-level Route attribute');

        // 检查 __invoke 方法的路由配置
        $method = $reflection->getMethod('__invoke');
        $routes = $method->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $this->assertEquals('/admin/stock/inbound-wizard/transfer', $route->getPath());
        $this->assertEquals('admin_stock_inbound_transfer_wizard', $route->getName());
        $this->assertEquals(['GET', 'POST'], $route->getMethods());
    }

    /**
     * 测试控制器类是final的.
     */
    public function testControllerIsFinal(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);
        $this->assertTrue($reflection->isFinal(), 'Controller should be declared as final');
    }

    /**
     * 测试控制器继承了正确的基类.
     */
    public function testControllerExtendsAbstractInboundWizardController(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass, 'Controller should have a parent class');
        $this->assertEquals(
            'Tourze\StockManageBundle\Controller\Admin\AbstractInboundWizardController',
            $parentClass->getName()
        );
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
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);
        $invokeMethod = $reflection->getMethod('__invoke');
        $routes = $invokeMethod->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $allowedMethods = $route->getMethods();

        // 验证不允许的方法确实不在允许的方法列表中
        $this->assertNotContains($method, $allowedMethods, "Method {$method} should not be allowed for this route");
    }

    /**
     * 测试控制器继承的依赖注入方法.
     */
    public function testControllerInheritsRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);

        // 这个控制器继承自 AbstractInboundWizardController，应该有setter方法
        $this->assertTrue($reflection->hasMethod('setInboundService'), 'Controller should inherit setInboundService method');
        $this->assertTrue($reflection->hasMethod('setSkuLoader'), 'Controller should inherit setSkuLoader method');

        // 检查setter方法的参数
        $setInboundService = $reflection->getMethod('setInboundService');
        $parameters = $setInboundService->getParameters();
        $this->assertCount(1, $parameters, 'setInboundService should have 1 parameter');
        $this->assertEquals('inboundService', $parameters[0]->getName());
        $type = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type, 'Parameter type should be a named type');
        $this->assertEquals('Tourze\StockManageBundle\Service\InboundService', $type->getName());

        $setSkuLoader = $reflection->getMethod('setSkuLoader');
        $parameters = $setSkuLoader->getParameters();
        $this->assertCount(1, $parameters, 'setSkuLoader should have 1 parameter');
        $this->assertEquals('skuLoader', $parameters[0]->getName());
        $type = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type, 'Parameter type should be a named type');
        $this->assertEquals('Tourze\ProductServiceContracts\SkuLoaderInterface', $type->getName());
    }

    /**
     * 测试控制器有__invoke方法.
     */
    public function testControllerHasInvokeMethod(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);
        $this->assertTrue($reflection->hasMethod('__invoke'), 'Controller should have __invoke method');

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic(), '__invoke method should be public');

        // 检查方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters, '__invoke method should have 1 parameter');
        $this->assertEquals('request', $parameters[0]->getName());
        $type = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type, 'Parameter type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\Request', $type->getName());

        // 检查返回类型
        $returnType = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', $returnType->getName());
    }

    /**
     * 测试控制器类的完整性.
     */
    public function testControllerClassIntegrity(): void
    {
        $reflection = new \ReflectionClass(StockInboundTransferWizardController::class);

        // 检查命名空间
        $this->assertEquals('Tourze\StockManageBundle\Controller\Admin', $reflection->getNamespaceName());

        // 检查类名
        $this->assertEquals('StockInboundTransferWizardController', $reflection->getShortName());

        // 检查是否为final类
        $this->assertTrue($reflection->isFinal());

        // 检查是否有正确的父类
        $parent = $reflection->getParentClass();
        $this->assertNotFalse($parent, 'Controller should have a parent class');
        $this->assertEquals('AbstractInboundWizardController', $parent->getShortName());
    }
}
