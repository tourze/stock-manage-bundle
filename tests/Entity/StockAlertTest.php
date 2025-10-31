<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;

/**
 * @internal
 */
#[CoversClass(StockAlert::class)]
class StockAlertTest extends AbstractEntityTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function createEntity(): StockAlert
    {
        return new StockAlert();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'sku' => ['sku', new Sku()];
        yield 'alertType' => ['alertType', StockAlertType::LOW_STOCK];
        yield 'severity' => ['severity', StockAlertSeverity::HIGH];
        yield 'status' => ['status', StockAlertStatus::RESOLVED];
        yield 'thresholdValue' => ['thresholdValue', 10.0];
        yield 'currentValue' => ['currentValue', 5.0];
        yield 'message' => ['message', '库存低于安全库存阈值'];
        yield 'resolvedNote' => ['resolvedNote', '已补充库存100件'];
        yield 'locationId' => ['locationId', 'WH001'];
        yield 'metadata' => ['metadata', ['auto_reorder' => true, 'supplier' => 'SUP001']];
        yield 'triggeredAt' => ['triggeredAt', new \DateTimeImmutable()];
        yield 'resolvedAt' => ['resolvedAt', new \DateTimeImmutable()];
    }

    public function testInitialState(): void
    {
        /** @var StockAlert $alert */
        $alert = $this->createEntity();

        $this->assertNull($alert->getId());
        $this->assertEquals(StockAlertSeverity::MEDIUM, $alert->getSeverity());
        $this->assertEquals(StockAlertStatus::ACTIVE, $alert->getStatus());
        $this->assertNull($alert->getThresholdValue());
        $this->assertNull($alert->getCurrentValue());
        $this->assertNull($alert->getResolvedNote());
        $this->assertNull($alert->getLocationId());
        $this->assertNull($alert->getMetadata());
        $this->assertInstanceOf(\DateTimeImmutable::class, $alert->getTriggeredAt());
        $this->assertNull($alert->getResolvedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $alert->getCreateTime());
        $this->assertNull($alert->getUpdateTime());
    }

    public function testResolvedAtIsSetWhenStatusChangesToResolved(): void
    {
        /** @var StockAlert $alert */
        $alert = $this->createEntity();

        $this->assertNull($alert->getResolvedAt());

        $alert->setStatus(StockAlertStatus::RESOLVED);

        $this->assertInstanceOf(\DateTimeImmutable::class, $alert->getResolvedAt());
    }

    public function testToString(): void
    {
        /** @var StockAlert $alert */
        $alert = $this->createEntity();
        $alert->setAlertType(StockAlertType::LOW_STOCK);
        $alert->setMessage('库存不足预警');

        $this->assertEquals('[low_stock] 库存不足预警', (string) $alert);
    }
}
