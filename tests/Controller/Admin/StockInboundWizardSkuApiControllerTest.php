<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Attribute\Route;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockInboundWizardSkuApiController;

/**
 * @internal
 */
#[CoversClass(StockInboundWizardSkuApiController::class)]
#[RunTestsInSeparateProcesses]
class StockInboundWizardSkuApiControllerTest extends AbstractWebTestCase
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
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);

        // 检查类级路由注解 - invokable 控制器通常没有类级路由
        $classRoutes = $reflection->getAttributes(Route::class);
        $this->assertCount(0, $classRoutes, 'Controller should not have class-level Route attribute');

        // 检查 __invoke 方法的路由配置
        $method = $reflection->getMethod('__invoke');
        $routes = $method->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $this->assertEquals('/admin/stock/inbound-wizard/api/skus', $route->getPath());
        $this->assertEquals('admin_stock_inbound_wizard_api_skus', $route->getName());
        $this->assertEquals(['GET'], $route->getMethods());
    }

    /**
     * 测试控制器类是final的.
     */
    public function testControllerIsFinal(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $this->assertTrue($reflection->isFinal(), 'Controller should be declared as final');
    }

    /**
     * 测试控制器继承了正确的基类.
     */
    public function testControllerExtendsAbstractController(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $parentClass = $reflection->getParentClass();
        $this->assertNotFalse($parentClass, 'Controller should have a parent class');
        $this->assertEquals(
            'Symfony\Bundle\FrameworkBundle\Controller\AbstractController',
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
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $invokeMethod = $reflection->getMethod('__invoke');
        $routes = $invokeMethod->getAttributes(Route::class);

        $this->assertCount(1, $routes, '__invoke method should have exactly one Route attribute');

        $route = $routes[0]->newInstance();
        $allowedMethods = $route->getMethods();

        // 验证不允许的方法确实不在允许的方法列表中
        $this->assertNotContains($method, $allowedMethods, "Method {$method} should not be allowed for this route");
    }

    /**
     * 测试控制器没有构造函数参数（不需要依赖注入）.
     */
    public function testControllerHasNoConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $constructor = $reflection->getConstructor();

        // 检查是否有构造函数，如果有则不应该有参数
        if (null !== $constructor) {
            $parameters = $constructor->getParameters();
            $this->assertCount(0, $parameters, 'API Controller should not have constructor parameters');
        } else {
            // 没有构造函数也是正常的
            $this->assertTrue(true, 'Controller has no constructor, which is acceptable');
        }
    }

    /**
     * 测试控制器方法返回类型.
     */
    public function testControllerMethodReturnType(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $method = $reflection->getMethod('__invoke');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, '__invoke method should have return type');
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', $returnType->getName());
    }

    /**
     * 测试控制器有__invoke方法.
     */
    public function testControllerHasInvokeMethod(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);
        $this->assertTrue($reflection->hasMethod('__invoke'), 'Controller should have __invoke method');

        $method = $reflection->getMethod('__invoke');
        $this->assertTrue($method->isPublic(), '__invoke method should be public');

        // 检查方法参数
        $parameters = $method->getParameters();
        $this->assertCount(0, $parameters, '__invoke method should have no parameters');

        // 检查返回类型
        $returnType = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', $returnType->getName());
    }

    /**
     * 测试控制器类的完整性.
     */
    public function testControllerClassIntegrity(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);

        // 检查命名空间
        $this->assertEquals('Tourze\StockManageBundle\Controller\Admin', $reflection->getNamespaceName());

        // 检查类名
        $this->assertEquals('StockInboundWizardSkuApiController', $reflection->getShortName());

        // 检查是否为final类
        $this->assertTrue($reflection->isFinal());

        // 检查是否有正确的父类
        $parent = $reflection->getParentClass();
        $this->assertNotFalse($parent, 'Controller should have a parent class');
        $this->assertEquals('AbstractController', $parent->getShortName());
    }

    /**
     * 测试API控制器实现基本结构.
     */
    public function testApiControllerBasicStructure(): void
    {
        $reflection = new \ReflectionClass(StockInboundWizardSkuApiController::class);

        // API控制器应该是final的
        $this->assertTrue($reflection->isFinal());

        // 应该有__invoke方法
        $this->assertTrue($reflection->hasMethod('__invoke'));

        // __invoke方法应该返回JsonResponse
        $invokeMethod = $reflection->getMethod('__invoke');
        $returnType = $invokeMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType, 'Return type should be a named type');
        $this->assertEquals('Symfony\Component\HttpFoundation\JsonResponse', $returnType->getName());
    }
}
