<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;
use Tourze\StockManageBundle\Controller\Admin\StockReservationCrudController;
use Tourze\StockManageBundle\Entity\StockReservation;

/**
 * @internal
 */
#[CoversClass(StockReservationCrudController::class)]
#[RunTestsInSeparateProcesses]
class StockReservationCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(StockReservation::class, StockReservationCrudController::getEntityFqcn());
    }

    public function testControllerIsInstantiable(): void
    {
        $controller = new StockReservationCrudController();

        $this->assertInstanceOf(StockReservationCrudController::class, $controller);
    }

    public function testControllerExtendCorrectBaseClass(): void
    {
        $controller = new StockReservationCrudController();

        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasCorrectRoute(): void
    {
        $reflection = new \ReflectionClass(StockReservationCrudController::class);
        $attributes = $reflection->getAttributes(AdminCrud::class);

        $this->assertCount(1, $attributes);

        $adminCrud = $attributes[0]->newInstance();
        $this->assertEquals('/stock/reservation', $adminCrud->routePath);
        $this->assertEquals('stock_reservation', $adminCrud->routeName);
    }

    /**
     * @return AbstractCrudController<StockReservation>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return new StockReservationCrudController();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'sku' => ['SKU'];
        yield 'reserved_quantity' => ['预占数量'];
        yield 'reservation_type' => ['预占类型'];
        yield 'business_id' => ['业务ID'];
        yield 'status' => ['状态'];
        yield 'operator' => ['操作人'];
        yield 'expires_time' => ['过期时间'];
        yield 'confirmed_time' => ['确认时间'];
        yield 'released_time' => ['释放时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'sku' => ['sku'];
        yield 'quantity' => ['quantity'];
        yield 'type' => ['type'];
        yield 'business_id' => ['businessId'];
        yield 'status' => ['status'];
        yield 'operator' => ['operator'];
        yield 'expires_time' => ['expiresTime'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'sku' => ['sku'];
        yield 'quantity' => ['quantity'];
        yield 'type' => ['type'];
        yield 'business_id' => ['businessId'];
        yield 'status' => ['status'];
        yield 'operator' => ['operator'];
        yield 'expires_time' => ['expiresTime'];
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 验证页面包含必填字段
        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);

        // 验证必填字段在NEW页面存在
        $this->assertStringContainsString('业务ID', $content, '应该显示业务ID字段');
        $this->assertStringContainsString('过期时间', $content, '应该显示过期时间字段');
        $this->assertStringContainsString('预占数量', $content, '应该显示预占数量字段');
        $this->assertStringContainsString('预占类型', $content, '应该显示预占类型字段');
    }
}
