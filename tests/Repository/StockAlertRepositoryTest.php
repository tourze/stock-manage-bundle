<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;
use Tourze\StockManageBundle\Repository\StockAlertRepository;

/**
 * @internal
 */
#[CoversClass(StockAlertRepository::class)]
#[RunTestsInSeparateProcesses]
class StockAlertRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepositoryClass(): string
    {
        return StockAlertRepository::class;
    }

    protected function getEntityClass(): string
    {
        return StockAlert::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    private function createSku(string $spuId): Sku
    {
        // Create SPU first
        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $spuId);
        $spu->setGtin($spuId);

        // Create SKU with SPU relationship
        $sku = new Sku();
        $sku->setGtin($spuId);
        $sku->setSpu($spu);
        $sku->setUnit('个');

        $em = self::getService(EntityManagerInterface::class);
        $em->persist($spu);
        $em->persist($sku);
        $em->flush();

        return $sku;
    }

    protected function createNewEntity(): object
    {
        $stockAlert = new StockAlert();
        $stockAlert->setAlertType(StockAlertType::LOW_STOCK);
        $stockAlert->setSeverity(StockAlertSeverity::MEDIUM);
        $stockAlert->setMessage('测试库存预警');
        $stockAlert->setThresholdValue(10.0);
        $stockAlert->setCurrentValue(5.0);

        return $stockAlert;
    }

    protected function getRepository(): StockAlertRepository
    {
        return self::getService(StockAlertRepository::class);
    }

    public function testCanSaveAndRetrieveStockAlert(): void
    {
        $repository = $this->getRepository();
        $stockAlert = new StockAlert();

        // 设置必要的字段以满足验证要求
        $stockAlert->setAlertType(StockAlertType::LOW_STOCK);
        $stockAlert->setSeverity(StockAlertSeverity::HIGH);
        $stockAlert->setMessage('库存不足预警');
        $stockAlert->setThresholdValue(20.0);
        $stockAlert->setCurrentValue(5.0);

        $repository->save($stockAlert);

        self::assertGreaterThan(0, $stockAlert->getId());

        $found = $repository->find($stockAlert->getId());
        self::assertInstanceOf(StockAlert::class, $found);
        self::assertSame($stockAlert->getId(), $found->getId());
        self::assertSame(StockAlertType::LOW_STOCK, $found->getAlertType());
        self::assertSame('库存不足预警', $found->getMessage());
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();

        // 创建一个真实的SKU对象
        $sku = $this->createSku('TEST_SKU_001');

        // 创建与SKU相关的预警
        $alert1 = new StockAlert();
        $alert1->setSku($sku);
        $alert1->setAlertType(StockAlertType::LOW_STOCK);
        $alert1->setSeverity(StockAlertSeverity::HIGH);
        $alert1->setMessage('SKU库存不足');
        $repository->save($alert1);

        $alert2 = new StockAlert();
        $alert2->setSku($sku);
        $alert2->setAlertType(StockAlertType::EXPIRY_WARNING);
        $alert2->setSeverity(StockAlertSeverity::MEDIUM);
        $alert2->setMessage('SKU即将过期');
        $repository->save($alert2);

        $results = $repository->findBySku($sku);

        self::assertGreaterThanOrEqual(2, count($results));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $results);

        foreach ($results as $result) {
            self::assertSame($sku, $result->getSku());
        }
    }

    public function testFindActiveAlerts(): void
    {
        $repository = $this->getRepository();

        // 创建活跃预警
        $activeAlert = new StockAlert();
        $activeAlert->setAlertType(StockAlertType::LOW_STOCK);
        $activeAlert->setSeverity(StockAlertSeverity::CRITICAL);
        $activeAlert->setStatus(StockAlertStatus::ACTIVE);
        $activeAlert->setMessage('严重库存不足');
        $repository->save($activeAlert);

        // 创建已解决预警
        $resolvedAlert = new StockAlert();
        $resolvedAlert->setAlertType(StockAlertType::LOW_STOCK);
        $resolvedAlert->setSeverity(StockAlertSeverity::HIGH);
        $resolvedAlert->setStatus(StockAlertStatus::RESOLVED);
        $resolvedAlert->setMessage('已解决的预警');
        $repository->save($resolvedAlert);

        $activeAlerts = $repository->findActiveAlerts();

        self::assertGreaterThanOrEqual(1, count($activeAlerts));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $activeAlerts);

        foreach ($activeAlerts as $alert) {
            self::assertSame(StockAlertStatus::ACTIVE, $alert->getStatus());
        }
    }

    public function testFindBySeverity(): void
    {
        $repository = $this->getRepository();
        $targetSeverity = StockAlertSeverity::CRITICAL;

        // 创建指定严重程度的预警
        $criticalAlert = new StockAlert();
        $criticalAlert->setAlertType(StockAlertType::OUT_OF_STOCK);
        $criticalAlert->setSeverity($targetSeverity);
        $criticalAlert->setMessage('严重缺货');
        $repository->save($criticalAlert);

        $results = $repository->findBySeverity($targetSeverity);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $results);

        foreach ($results as $result) {
            self::assertSame($targetSeverity, $result->getSeverity());
        }
    }

    public function testFindByType(): void
    {
        $repository = $this->getRepository();
        $targetType = StockAlertType::EXPIRY_WARNING;

        // 创建指定类型的预警
        $expiryAlert = new StockAlert();
        $expiryAlert->setAlertType($targetType);
        $expiryAlert->setSeverity(StockAlertSeverity::MEDIUM);
        $expiryAlert->setMessage('商品即将过期');
        $repository->save($expiryAlert);

        $results = $repository->findByType($targetType);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $results);

        foreach ($results as $result) {
            self::assertSame($targetType, $result->getAlertType());
        }
    }

    public function testFindByLocation(): void
    {
        $repository = $this->getRepository();
        $locationId = 'WAREHOUSE_A';

        // 创建指定位置的预警
        $locationAlert = new StockAlert();
        $locationAlert->setAlertType(StockAlertType::LOW_STOCK);
        $locationAlert->setSeverity(StockAlertSeverity::HIGH);
        $locationAlert->setMessage('仓库A库存不足');
        $locationAlert->setLocationId($locationId);
        $repository->save($locationAlert);

        $results = $repository->findByLocation($locationId);

        self::assertGreaterThanOrEqual(1, count($results));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $results);

        foreach ($results as $result) {
            self::assertSame($locationId, $result->getLocationId());
        }
    }

    public function testFindCriticalAndHighSeverityActiveAlerts(): void
    {
        $repository = $this->getRepository();

        // 创建高严重程度的活跃预警
        $highAlert = new StockAlert();
        $highAlert->setAlertType(StockAlertType::LOW_STOCK);
        $highAlert->setSeverity(StockAlertSeverity::HIGH);
        $highAlert->setStatus(StockAlertStatus::ACTIVE);
        $highAlert->setMessage('高严重程度预警');
        $repository->save($highAlert);

        // 创建严重程度的活跃预警
        $criticalAlert = new StockAlert();
        $criticalAlert->setAlertType(StockAlertType::OUT_OF_STOCK);
        $criticalAlert->setSeverity(StockAlertSeverity::CRITICAL);
        $criticalAlert->setStatus(StockAlertStatus::ACTIVE);
        $criticalAlert->setMessage('严重程度预警');
        $repository->save($criticalAlert);

        // 创建低严重程度的活跃预警（不应该被包含）
        $lowAlert = new StockAlert();
        $lowAlert->setAlertType(StockAlertType::LOW_STOCK);
        $lowAlert->setSeverity(StockAlertSeverity::LOW);
        $lowAlert->setStatus(StockAlertStatus::ACTIVE);
        $lowAlert->setMessage('低严重程度预警');
        $repository->save($lowAlert);

        $results = $repository->findCriticalAndHighSeverityActiveAlerts();

        self::assertGreaterThanOrEqual(2, count($results));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $results);

        foreach ($results as $result) {
            self::assertSame(StockAlertStatus::ACTIVE, $result->getStatus());
            self::assertTrue(
                StockAlertSeverity::HIGH === $result->getSeverity()
                || StockAlertSeverity::CRITICAL === $result->getSeverity()
            );
        }
    }

    public function testGetAlertStatistics(): void
    {
        $repository = $this->getRepository();

        // 创建多种状态和严重程度的预警
        $alert1 = new StockAlert();
        $alert1->setAlertType(StockAlertType::LOW_STOCK);
        $alert1->setSeverity(StockAlertSeverity::HIGH);
        $alert1->setStatus(StockAlertStatus::ACTIVE);
        $alert1->setMessage('统计测试1');
        $repository->save($alert1);

        $alert2 = new StockAlert();
        $alert2->setAlertType(StockAlertType::OUT_OF_STOCK);
        $alert2->setSeverity(StockAlertSeverity::CRITICAL);
        $alert2->setStatus(StockAlertStatus::ACTIVE);
        $alert2->setMessage('统计测试2');
        $repository->save($alert2);

        $stats = $repository->getAlertStatistics();

        self::assertIsArray($stats);
        self::assertArrayHasKey('total', $stats);
        self::assertArrayHasKey('by_status', $stats);
        self::assertArrayHasKey('by_severity', $stats);
        self::assertGreaterThanOrEqual(2, $stats['total']);
    }

    public function testFindExpiredAlerts(): void
    {
        $repository = $this->getRepository();

        // 创建一个过期的已解决预警
        $expiredAlert = new StockAlert();
        $expiredAlert->setAlertType(StockAlertType::LOW_STOCK);
        $expiredAlert->setSeverity(StockAlertSeverity::MEDIUM);
        $expiredAlert->setStatus(StockAlertStatus::RESOLVED);
        $expiredAlert->setMessage('过期预警测试');
        $expiredAlert->setResolvedAt(new \DateTimeImmutable('-45 days'));
        $repository->save($expiredAlert);

        $expiredAlerts = $repository->findExpiredAlerts(30);

        self::assertGreaterThanOrEqual(1, count($expiredAlerts));
        self::assertContainsOnlyInstancesOf(StockAlert::class, $expiredAlerts);

        foreach ($expiredAlerts as $alert) {
            self::assertTrue(
                StockAlertStatus::RESOLVED === $alert->getStatus()
                || StockAlertStatus::DISMISSED === $alert->getStatus()
            );
            self::assertNotNull($alert->getResolvedAt());
        }
    }

    public function testGetActiveAlertCountBySku(): void
    {
        $repository = $this->getRepository();

        // 创建一个真实的SKU对象
        $sku = $this->createSku('COUNT_TEST_SKU');

        // 创建活跃预警
        $activeAlert = new StockAlert();
        $activeAlert->setSku($sku);
        $activeAlert->setAlertType(StockAlertType::LOW_STOCK);
        $activeAlert->setSeverity(StockAlertSeverity::HIGH);
        $activeAlert->setStatus(StockAlertStatus::ACTIVE);
        $activeAlert->setMessage('计数测试预警');
        $repository->save($activeAlert);

        $count = $repository->getActiveAlertCountBySku($sku);

        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testHasDuplicateActiveAlert(): void
    {
        $repository = $this->getRepository();

        // 创建一个真实的SKU对象
        $sku = $this->createSku('DUPLICATE_TEST_SKU');

        $alertType = StockAlertType::LOW_STOCK;

        // 初始应该没有重复
        self::assertFalse($repository->hasDuplicateActiveAlert($sku, $alertType));

        // 创建一个活跃预警
        $alert = new StockAlert();
        $alert->setSku($sku);
        $alert->setAlertType($alertType);
        $alert->setSeverity(StockAlertSeverity::HIGH);
        $alert->setStatus(StockAlertStatus::ACTIVE);
        $alert->setMessage('重复测试预警');
        $repository->save($alert);

        // 现在应该检测到重复
        self::assertTrue($repository->hasDuplicateActiveAlert($sku, $alertType));
    }

    public function testRemoveStockAlert(): void
    {
        $repository = $this->getRepository();
        $stockAlert = new StockAlert();

        $stockAlert->setAlertType(StockAlertType::LOW_STOCK);
        $stockAlert->setSeverity(StockAlertSeverity::MEDIUM);
        $stockAlert->setMessage('删除测试预警');

        $repository->save($stockAlert);
        $id = $stockAlert->getId();

        self::assertNotNull($repository->find($id));

        $repository->remove($stockAlert);

        self::assertNull($repository->find($id));
    }

    public function testEntityStatusChange(): void
    {
        $stockAlert = new StockAlert();
        $stockAlert->setStatus(StockAlertStatus::ACTIVE);

        self::assertNull($stockAlert->getResolvedAt());

        // 设置为已解决状态应该自动设置解决时间
        $stockAlert->setStatus(StockAlertStatus::RESOLVED);

        self::assertNotNull($stockAlert->getResolvedAt());
        self::assertSame(StockAlertStatus::RESOLVED, $stockAlert->getStatus());
    }

    public function testEntityToString(): void
    {
        $stockAlert = new StockAlert();
        $stockAlert->setAlertType(StockAlertType::LOW_STOCK);
        $stockAlert->setMessage('测试预警消息');

        $expectedString = '[low_stock] 测试预警消息';
        self::assertSame($expectedString, (string) $stockAlert);
    }
}
