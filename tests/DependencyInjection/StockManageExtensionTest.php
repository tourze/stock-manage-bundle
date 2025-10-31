<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;
use Tourze\StockManageBundle\DependencyInjection\StockManageExtension;

/**
 * @internal
 */
#[CoversClass(StockManageExtension::class)]
class StockManageExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private StockManageExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension = new StockManageExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.environment', 'test');
    }

    public function testLoadWithEmptyConfig(): void
    {
        $this->extension->load([], $this->container);

        // 验证主要服务接口别名是否已设置
        $this->assertTrue($this->container->hasAlias('Tourze\StockManageBundle\Service\StockServiceInterface'));
        $this->assertTrue($this->container->hasAlias('Tourze\StockManageBundle\Service\ReservationServiceInterface'));
        $this->assertTrue($this->container->hasAlias('Tourze\StockManageBundle\Service\AlertServiceInterface'));

        // 验证核心服务已加载
        $this->assertTrue($this->container->hasDefinition('Tourze\StockManageBundle\Service\StockService'));
    }

    public function testLoadWithCustomConfig(): void
    {
        $config = [];
        $this->extension->load($config, $this->container);

        // 基本测试，验证配置加载成功
        $this->assertTrue($this->container->hasDefinition('Tourze\StockManageBundle\Service\StockService'));
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('stock_manage', $this->extension->getAlias());
    }

    public function testEnvironmentConfiguration(): void
    {
        // 测试不同环境下的配置加载
        $environments = ['dev', 'test', 'prod'];

        foreach ($environments as $env) {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.environment', $env);

            $this->extension->load([], $container);

            // 基本验证 - 配置文件加载成功
            $this->assertTrue($container->hasDefinition('Tourze\StockManageBundle\Service\StockService'));
        }
    }
}
