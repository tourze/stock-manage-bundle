<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Event\StockAdjustedEvent;
use Tourze\StockManageBundle\Event\StockOutboundEvent;
use Tourze\StockManageBundle\EventListener\StockAlertListener;
use Tourze\StockManageBundle\Service\AlertServiceInterface;

/**
 * @internal
 */
#[CoversClass(StockAlertListener::class)]
#[RunTestsInSeparateProcesses]
class StockAlertListenerTest extends AbstractEventSubscriberTestCase
{
    private StockAlertListener $listener;

    private AlertServiceInterface&MockObject $alertService;

    protected function onSetUp(): void
    {
        $this->alertService = $this->createMock(AlertServiceInterface::class);
        self::getContainer()->set(AlertServiceInterface::class, $this->alertService);
        $this->listener = self::getService(StockAlertListener::class);
    }

    private function createSku(string $spuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($spuId);

        return $sku;
    }

    public function testHandleStockEvent(): void
    {
        $mockSku = $this->createSku('SPU001');
        $batch = new StockBatch();
        $batch->setSku($mockSku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(5); // 低于阈值10
        $batch->setAvailableQuantity(5);

        $event = new StockOutboundEvent($batch, 'sale', 10, 'SO001');

        // 正常调用，不应该抛出异常
        $this->expectNotToPerformAssertions(); // 这个测试验证方法能正常调用无异常
        $this->listener->handleStockEvent($event);
    }

    public function testOnStockOutbound(): void
    {
        $mockSku = $this->createSku('SPU002');
        $batch = new StockBatch();
        $batch->setSku($mockSku);
        $batch->setBatchNo('BATCH002');
        $batch->setQuantity(8);
        $batch->setAvailableQuantity(8);

        $event = new StockOutboundEvent($batch, 'sale', 5, 'SO002');

        $this->expectNotToPerformAssertions(); // 这个测试验证方法能正常调用无异常
        $this->listener->onStockOutbound($event);
    }

    public function testOnStockAdjusted(): void
    {
        $mockSku = $this->createSku('SPU003');
        $batch = new StockBatch();
        $batch->setSku($mockSku);
        $batch->setBatchNo('BATCH003');
        $batch->setQuantity(15);
        $batch->setAvailableQuantity(15);

        $event = new StockAdjustedEvent($batch, 'damage', -5, 'Damaged goods');

        $this->expectNotToPerformAssertions(); // 这个测试验证方法能正常调用无异常
        $this->listener->onStockAdjusted($event);
    }
}
