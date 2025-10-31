<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;

/**
 * @internal
 */
#[CoversClass(StockLog::class)]
class StockLogTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new StockLog();
    }

    /**
     * 创建 StockLog 实体的辅助方法.
     */
    private function createStockLog(): StockLog
    {
        return new StockLog();
    }

    public function testCanCreateStockLog(): void
    {
        $stockLog = $this->createEntity();

        // 测试基本属性 - 新实体ID为null（未持久化）
        self::assertNull($stockLog->getId());
    }

    public function testCanSetAndGetType(): void
    {
        $stockLog = $this->createStockLog();
        $type = StockChange::INBOUND;

        $stockLog->setType($type);

        self::assertSame($type, $stockLog->getType());
    }

    public function testCanSetAndGetQuantity(): void
    {
        $stockLog = $this->createStockLog();
        $quantity = 100;

        $stockLog->setQuantity($quantity);

        self::assertSame($quantity, $stockLog->getQuantity());
    }

    public function testCanSetAndGetSkuData(): void
    {
        $stockLog = $this->createStockLog();
        $skuData = ['id' => 'sku-001', 'code' => 'PROD-001'];

        $stockLog->setSkuData($skuData);

        self::assertSame('sku-001', $stockLog->getSkuId());
    }

    public function testCanSetAndGetRemark(): void
    {
        $stockLog = $this->createStockLog();
        $remark = 'Test remark';

        $stockLog->setRemark($remark);

        self::assertSame($remark, $stockLog->getRemark());
    }

    public function testRemarkCanBeNull(): void
    {
        $stockLog = $this->createStockLog();

        $stockLog->setRemark(null);

        self::assertNull($stockLog->getRemark());
    }

    public function testGetSkuIdReturnsNullWhenSkuDataIsEmpty(): void
    {
        $stockLog = $this->createStockLog();
        $stockLog->setSkuData([]);

        self::assertNull($stockLog->getSkuId());
    }

    public function testGetSkuIdReturnsNullWhenIdNotInSkuData(): void
    {
        $stockLog = $this->createStockLog();
        $stockLog->setSkuData(['code' => 'PROD-001']);

        self::assertNull($stockLog->getSkuId());
    }

    public function testGetSkuReturnsNull(): void
    {
        $stockLog = $this->createStockLog();
        $stockLog->setSkuData(['id' => 'sku-001', 'code' => 'PROD-001']);

        // getSku() always returns null in current implementation
        self::assertNull($stockLog->getSku());
    }

    public function testToString(): void
    {
        $stockLog = $this->createStockLog();
        $stockLog->setType(StockChange::INBOUND);
        $stockLog->setQuantity(100);

        $expected = 'StockLog#new: inbound 100';
        self::assertSame($expected, $stockLog->__toString());
    }

    public function testToStringWithNegativeQuantity(): void
    {
        $stockLog = $this->createStockLog();
        $stockLog->setType(StockChange::OUTBOUND);
        $stockLog->setQuantity(-50);

        $expected = 'StockLog#new: outbound -50';
        self::assertSame($expected, $stockLog->__toString());
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        return [
            'type' => ['type', StockChange::INBOUND],
            'quantity' => ['quantity', 100],
            'skuData' => ['skuData', ['id' => 'sku-001', 'code' => 'PROD-001']],
            'remark' => ['remark', 'Test remark'],
        ];
    }
}
