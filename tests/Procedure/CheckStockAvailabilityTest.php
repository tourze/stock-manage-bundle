<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Procedure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Param\CheckStockAvailabilityParam;
use Tourze\StockManageBundle\Procedure\CheckStockAvailability;

/**
 * @internal
 */
#[CoversClass(CheckStockAvailability::class)]
#[RunTestsInSeparateProcesses]
class CheckStockAvailabilityTest extends AbstractProcedureTestCase
{
    private CheckStockAvailability $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(CheckStockAvailability::class);
    }

    private function createSkuWithSpu(string $gtin): Sku
    {
        $entityManager = self::getEntityManager();

        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $gtin);
        $spu->setGtin('SPU-' . $gtin);
        $entityManager->persist($spu);
        $entityManager->flush();

        $sku = new Sku();
        $sku->setGtin($gtin);
        $sku->setUnit('个');
        $sku->setSpu($spu);

        $entityManager->persist($sku);
        $entityManager->flush();

        return $sku;
    }

    private function createStockBatch(Sku $sku, int $quantity, ?string $batchNo = null, ?int $availableQuantity = null): StockBatch
    {
        $entityManager = self::getEntityManager();

        $batch = new StockBatch();
        // 确保 batch_no 唯一性
        $batch->setBatchNo($batchNo ?? 'BATCH-' . uniqid() . '-' . mt_rand(1000, 9999));
        $batch->setSku($sku);
        $batch->setQuantity($quantity);
        $batch->setAvailableQuantity($availableQuantity ?? $quantity);
        $batch->setReservedQuantity(0);
        $batch->setLockedQuantity(0);
        $batch->setUnitCost(10.0);
        $batch->setStatus('available');

        $entityManager->persist($batch);
        $entityManager->flush();

        return $batch;
    }

    public function testExecuteWithValidItems(): void
    {
        // 创建测试数据
        $sku1 = $this->createSkuWithSpu('sku-100');
        $sku2 = $this->createSkuWithSpu('sku-200');

        // 创建库存批次（使用唯一批次号）
        $this->createStockBatch($sku1, 10);
        $this->createStockBatch($sku2, 5);

        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'skuId' => (int) $sku1->getId(), 'quantity' => 5],
            ['productId' => 2, 'skuId' => (int) $sku2->getId(), 'quantity' => 3],
        ]);

        $result = $this->procedure->execute($param);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['totalCount']);
        $this->assertSame(2, $result['successCount']);
        $this->assertSame(0, $result['failedCount']);
        $this->assertCount(2, $result['results']);
    }

    public function testValidateInputWithEmptyItems(): void
    {
        $param = new CheckStockAvailabilityParam([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('检查项目列表不能为空');

        $this->procedure->execute($param);
    }

    public function testValidateInputWithTooManyItems(): void
    {
        $param = new CheckStockAvailabilityParam(
            array_fill(0, 1001, ['productId' => 1, 'skuId' => 1, 'quantity' => 1])
        );

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('单次批量检查不能超过1000个项目');

        $this->procedure->execute($param);
    }

    public function testValidateInputWithInvalidFormat(): void
    {
        // 使用类型安全的方式传入错误格式数据来测试验证
        $param = new CheckStockAvailabilityParam(['invalid']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('第1个项目数据格式错误');

        $this->procedure->execute($param);
    }

    public function testValidateInputWithMissingFields(): void
    {
        // 使用类型安全的方式传入缺少字段的数据来测试验证
        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'quantity' => 5], // missing skuId
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('第1个项目缺少必需字段: skuId');

        $this->procedure->execute($param);
    }

    public function testValidateInputWithInvalidFieldType(): void
    {
        // 使用类型安全的方式传入错误类型字段来测试验证
        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'skuId' => 'invalid', 'quantity' => 5],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('第1个项目的skuId必须为正整数');

        $this->procedure->execute($param);
    }

    public function testValidateInputWithDuplicateSkuIds(): void
    {
        // 使用固定的正整数SKU ID
        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'skuId' => 100, 'quantity' => 5],
            ['productId' => 2, 'skuId' => 100, 'quantity' => 3],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('检查项目列表包含重复的SKU ID');

        $this->procedure->execute($param);
    }

    public function testExecuteWithInsufficientStock(): void
    {
        // 创建测试数据
        $sku = $this->createSkuWithSpu('sku-insufficient');

        // 创建库存批次：总量5，但可用量只有3（模拟被预留/锁定占用）
        $this->createStockBatch($sku, 5, availableQuantity: 3);

        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'skuId' => (int) $sku->getId(), 'quantity' => 10],
        ]);

        $result = $this->procedure->execute($param);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['totalCount']);
        $this->assertSame(0, $result['successCount']);
        $this->assertSame(1, $result['failedCount']);

        $firstResult = $result['results'][0];
        $this->assertFalse($firstResult['available']);
        $this->assertSame(3, $firstResult['currentStock']);
        $this->assertNotNull($firstResult['message']);
        $this->assertStringContainsString('库存不足', $firstResult['message']);
    }

    public function testExecuteWithException(): void
    {
        // 使用一个不存在的SKU ID，这会导致查询失败
        $param = new CheckStockAvailabilityParam([
            ['productId' => 1, 'skuId' => 999999, 'quantity' => 5],
        ]);

        $result = $this->procedure->execute($param);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['totalCount']);
        $this->assertSame(0, $result['successCount']);
        $this->assertSame(1, $result['failedCount']);

        $firstResult = $result['results'][0];
        $this->assertFalse($firstResult['available']);
        $this->assertSame(0, $firstResult['currentStock']);
        // Note: 这个测试可能需要根据实际的异常处理逻辑调整
    }
}
