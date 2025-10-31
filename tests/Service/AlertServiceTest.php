<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertType;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\AlertService;

/**
 * @internal
 */
#[CoversClass(AlertService::class)]
class AlertServiceTest extends TestCase
{
    private AlertService $service;

    private MockObject $entityManager;

    private MockObject $batchRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->batchRepository = $this->createMock(StockBatchRepository::class);

        $this->service = new AlertService(
            $this->entityManager,
            $this->batchRepository
        );
    }

    private function createSku(string $skuCode): Sku
    {
        $sku = new Sku();
        $sku->setGtin($skuCode);

        return $sku;
    }

    public function testCreateAlert(): void
    {
        $sku = $this->createSku('SPU001');

        $data = [
            'sku' => $sku,
            'alert_type' => StockAlertType::LOW_STOCK,
            'threshold_value' => 50,
            'current_value' => 20,
            'message' => 'Low stock warning',
        ];

        // 设置 EntityManager 期望
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(StockAlert::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $alert = $this->service->createAlert($data);

        $this->assertInstanceOf(StockAlert::class, $alert);
        $this->assertEquals($sku, $alert->getSku());
        $this->assertEquals(StockAlertType::LOW_STOCK, $alert->getAlertType());
        $this->assertEquals(50, $alert->getThresholdValue());
    }

    public function testCreateAlertWithInvalidData(): void
    {
        $data = [
            'alert_type' => StockAlertType::LOW_STOCK,
            'threshold_value' => 50,
            'message' => 'Test message',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SKU is required');

        $this->service->createAlert($data);
    }

    public function testCheckLowStockAlerts(): void
    {
        // Mock batchRepository 返回库存数据
        $this->batchRepository->expects($this->once())
            ->method('getTotalAvailableQuantity')
            ->with('SPU001')
            ->willReturn(30)
        ;

        $alerts = $this->service->checkLowStockAlerts(['SPU001' => 50]); // 阈值50，实际30，应该触发警报

        $this->assertIsArray($alerts);
        $this->assertCount(1, $alerts);
        $this->assertEquals(StockAlertType::LOW_STOCK, $alerts[0]['alert_type']);
        $this->assertEquals('SPU001', $alerts[0]['spu_id']);
        $this->assertEquals(30, $alerts[0]['current_stock']);
        $this->assertEquals(50, $alerts[0]['threshold']);
    }

    public function testCheckHighStockAlerts(): void
    {
        // Mock batchRepository 返回高库存数据
        $this->batchRepository->expects($this->once())
            ->method('getTotalAvailableQuantity')
            ->with('SPU002')
            ->willReturn(1500)
        ;

        $alerts = $this->service->checkHighStockAlerts(['SPU002' => 1000]); // 阈值1000，实际1500，应该触发警报

        $this->assertIsArray($alerts);
        $this->assertCount(1, $alerts);
        $this->assertEquals(StockAlertType::HIGH_STOCK, $alerts[0]['alert_type']);
        $this->assertEquals('SPU002', $alerts[0]['spu_id']);
        $this->assertEquals(1500, $alerts[0]['current_stock']);
        $this->assertEquals(1000, $alerts[0]['threshold']);
    }

    public function testGenerateRestockSuggestion(): void
    {
        // Mock batchRepository 返回低库存数据
        $this->batchRepository->expects($this->once())
            ->method('getTotalAvailableQuantity')
            ->with('SPU003')
            ->willReturn(20)
        ;

        $suggestion = $this->service->generateRestockSuggestion('SPU003', [
            'target_days' => 30,
            'daily_usage' => 5,
            'safety_stock' => 10,
        ]);

        $this->assertIsArray($suggestion);
        $this->assertEquals('SPU003', $suggestion['spuId']);
        $this->assertArrayHasKey('suggestedQuantity', $suggestion);
        $this->assertArrayHasKey('urgency', $suggestion);
        $this->assertArrayHasKey('reason', $suggestion);
    }

    public function testGetAlertSummary(): void
    {
        // Mock 方法调用
        $mockSummary = [
            'totalAlerts' => 5,
            'activeAlerts' => 3,
            'byType' => ['low_stock' => 2, 'high_stock' => 1],
            'bySeverity' => ['critical' => 1, 'high' => 2],
        ];

        // 这里正常情况下需要 mock 更复杂的逻辑，但为了简化测试，直接测试结果结构
        $summary = $this->service->getAlertSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('totalAlerts', $summary);
        $this->assertArrayHasKey('activeAlerts', $summary);
        $this->assertArrayHasKey('byType', $summary);
        $this->assertArrayHasKey('bySeverity', $summary);
    }

    public function testGetSupportedAlertTypes(): void
    {
        $types = $this->service->getSupportedAlertTypes();

        $this->assertIsArray($types);
        $this->assertContains(StockAlertType::LOW_STOCK, $types);
        $this->assertContains(StockAlertType::HIGH_STOCK, $types);
        $this->assertContains(StockAlertType::EXPIRY_WARNING, $types);
        $this->assertContains(StockAlertType::OUT_OF_STOCK, $types);
    }

    public function testCalculateAlertSeverity(): void
    {
        // Test critical severity (very low stock)
        $criticalSeverity = $this->service->calculateAlertSeverity(StockAlertType::LOW_STOCK, 5, 100);
        $this->assertEquals('critical', $criticalSeverity);

        // Test high severity (moderately low stock)
        $highSeverity = $this->service->calculateAlertSeverity(StockAlertType::LOW_STOCK, 15, 100);
        $this->assertEquals('high', $highSeverity);

        // Test medium severity
        $mediumSeverity = $this->service->calculateAlertSeverity(StockAlertType::LOW_STOCK, 30, 100);
        $this->assertEquals('medium', $mediumSeverity);
    }

    public function testIsAlertEnabled(): void
    {
        // Test with alert enabled
        $_ENV['STOCK_ALERT_ENABLED'] = '1';
        $this->assertTrue($this->service->isAlertEnabled());

        // Test with alert disabled
        $_ENV['STOCK_ALERT_ENABLED'] = '0';
        $this->assertFalse($this->service->isAlertEnabled());

        // Test with default (enabled)
        unset($_ENV['STOCK_ALERT_ENABLED']);
        $this->assertTrue($this->service->isAlertEnabled());
    }

    public function testGetDefaultThresholds(): void
    {
        $thresholds = $this->service->getDefaultThresholds();

        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('low_stock', $thresholds);
        $this->assertArrayHasKey('high_stock', $thresholds);
        $this->assertArrayHasKey('expiry_warning_days', $thresholds);
    }

    public function testSendLowStockAlert(): void
    {
        $sku = $this->createSku('SPU006');
        $currentQuantity = 20;
        $threshold = 50;

        // 设置 EntityManager 期望
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(StockAlert::class))
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->service->sendLowStockAlert($sku, $currentQuantity, $threshold);
    }

    public function testCheckExpiryAlerts(): void
    {
        $warningDays = 7;
        $alerts = $this->service->checkExpiryAlerts($warningDays);

        $this->assertIsArray($alerts);
    }

    public function testUpdateAlert(): void
    {
        $updateData = [
            'threshold_value' => 30,
            'resolved_note' => 'Updated alert note',
        ];

        $alert = new StockAlert();
        $alert->setSku($this->createSku('SPU007'));
        $alert->setThresholdValue(50);
        $alert->setMessage('Original message');

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $updatedAlert = $this->service->updateAlert($alert, $updateData);

        $this->assertInstanceOf(StockAlert::class, $updatedAlert);
        $this->assertEquals(30, $updatedAlert->getThresholdValue());
        $this->assertEquals('Updated alert note', $updatedAlert->getResolvedNote());
    }

    public function testDeleteAlert(): void
    {
        $alert = new StockAlert();
        $alert->setSku($this->createSku('SPU008'));

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($alert)
        ;

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $this->service->deleteAlert($alert);
    }
}
