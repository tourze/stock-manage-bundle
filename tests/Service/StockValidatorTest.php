<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SKU as SKUInterface;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Exception\InvalidQuantityException;
use Tourze\StockManageBundle\Exception\InvalidStatusException;
use Tourze\StockManageBundle\Service\StockValidator;

/**
 * @internal
 */
#[CoversClass(StockValidator::class)]
#[RunTestsInSeparateProcesses]
final class StockValidatorTest extends AbstractIntegrationTestCase
{
    private StockValidator $stockValidator;

    protected function onSetUp(): void
    {
        $this->stockValidator = self::getService(StockValidator::class);
    }

    private function createSku(string $gtin): Sku
    {
        // 创建 SPU（Sku 的必填关联）
        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $gtin);

        // 创建 Sku 并关联 SPU
        $sku = new Sku();
        $sku->setGtin($gtin);
        $sku->setUnit('个'); // Required NOT NULL field
        $sku->setSpu($spu); // Required NOT NULL foreign key

        return $sku;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatch(array $data): StockBatch
    {
        $batch = new StockBatch();

        $batch->setBatchNo($data['batch_no'] ?? 'BATCH001');
        $batch->setSku((isset($data['sku']) && $data['sku'] instanceof SKUInterface) ? $data['sku'] : $this->createSku('SKU001'));
        $batch->setQuantity($data['quantity'] ?? 100);
        $batch->setAvailableQuantity($data['available_quantity'] ?? $batch->getQuantity());
        $batch->setUnitCost($data['unit_cost'] ?? 10.50);
        $batch->setQualityLevel($data['quality_level'] ?? 'A');
        $batch->setLocationId(array_key_exists('location_id', $data) ? $data['location_id'] : 'WH001');
        $batch->setStatus($data['status'] ?? 'available');

        return $batch;
    }

    public function testValidateCreateBatchDataSuccess(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.50,
        ];

        // 验证方法成功执行而不抛出异常
        $result = null;
        try {
            $this->stockValidator->validateCreateBatchData($data);
            $result = 'success';
        } catch (\Exception $e) {
            self::fail('不应该抛出异常: ' . $e->getMessage());
        }

        $this->assertEquals('success', $result);
    }

    public function testValidateCreateBatchDataDuplicateBatchNo(): void
    {
        $sku = $this->createSku('SKU001');

        // 先创建一个批次使批次号已存在
        $existingBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
        ]);
        $this->persistAndFlush($existingBatch);

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
        ];

        $this->expectException(DuplicateBatchException::class);

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataMissingSku(): void
    {
        $data = [
            'batch_no' => 'BATCH001',
            'quantity' => 100,
        ];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('SKU不能为空');

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataInvalidSku(): void
    {
        $data = [
            'batch_no' => 'BATCH001',
            'sku' => 'not_a_sku_object',
            'quantity' => 100,
        ];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('SKU不能为空');

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataMissingQuantity(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
        ];

        $this->expectException(InvalidQuantityException::class);

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataInvalidQuantity(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 0,
        ];

        $this->expectException(InvalidQuantityException::class);

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataNegativeQuantity(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => -50,
        ];

        $this->expectException(InvalidQuantityException::class);

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataWithoutBatchNo(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'sku' => $sku,
            'quantity' => 100,
        ];

        // 没有提供批次号时不需要检查重复
        // 使用匿名类实现，无需设置期望

        // 验证方法成功执行而不抛出异常
        $result = null;
        try {
            $this->stockValidator->validateCreateBatchData($data);
            $result = 'success';
        } catch (\Exception $e) {
            self::fail('不应该抛出异常: ' . $e->getMessage());
        }

        $this->assertEquals('success', $result);
    }

    public function testValidateBatchCompatibilitySuccess(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchCompatibilityInsufficientBatches(): void
    {
        $batch = $this->createStockBatch([]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('至少需要2个批次才能合并');

        $this->stockValidator->validateBatchCompatibility([$batch]);
    }

    public function testValidateBatchCompatibilityDifferentSku(): void
    {
        $sku1 = $this->createSku('SKU001');
        $sku2 = $this->createSku('SKU002');

        $batch1 = $this->createStockBatch([
            'sku' => $sku1,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku2,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：SKU不同');

        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchCompatibilityDifferentQualityLevel(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'B',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：质量等级不同');

        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchCompatibilityDifferentLocation(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH002',
        ]);

        $batches = [$batch1, $batch2];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：位置不同');

        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchStatusSuccess(): void
    {
        $validStatuses = ['pending', 'in_transit', 'available', 'partially_available', 'depleted', 'expired', 'damaged', 'quarantined'];

        $this->expectNotToPerformAssertions();

        foreach ($validStatuses as $status) {
            // 不应该抛出异常
            $this->stockValidator->validateBatchStatus($status);
        }
    }

    public function testValidateBatchStatusInvalid(): void
    {
        $this->expectException(InvalidStatusException::class);

        $this->stockValidator->validateBatchStatus('invalid_status');
    }

    public function testValidateQuantityAdjustmentSuccess(): void
    {
        $batch = $this->createStockBatch([
            'quantity' => 100,
        ]);

        // 正向调整
        $this->stockValidator->validateQuantityAdjustment($batch, 50);

        // 负向调整但不会导致负数
        $this->stockValidator->validateQuantityAdjustment($batch, -80);

        // 调整到刚好为0
        $this->stockValidator->validateQuantityAdjustment($batch, -100);

        $this->expectNotToPerformAssertions();
    }

    public function testValidateQuantityAdjustmentResultingInNegative(): void
    {
        $batch = $this->createStockBatch([
            'quantity' => 100,
        ]);

        $this->expectException(InvalidQuantityException::class);

        // 调整会导致负数
        $this->stockValidator->validateQuantityAdjustment($batch, -150);
    }

    public function testValidateQuantityAdjustmentLargeNegative(): void
    {
        $batch = $this->createStockBatch([
            'quantity' => 50,
        ]);

        $this->expectException(InvalidQuantityException::class);

        // 大幅负向调整
        $this->stockValidator->validateQuantityAdjustment($batch, -100);
    }

    public function testValidateBatchCompatibilityWithThreeBatches(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch3 = $this->createStockBatch([
            'batch_no' => 'BATCH003',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2, $batch3];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchCompatibilityWithThirdBatchDifferent(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch3 = $this->createStockBatch([
            'batch_no' => 'BATCH003',
            'sku' => $sku,
            'quality_level' => 'B', // 不同的质量等级
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2, $batch3];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：质量等级不同');

        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateCreateBatchDataWithEmptyBatchNo(): void
    {
        $sku = $this->createSku('SKU001');

        $data = [
            'batch_no' => '',
            'sku' => $sku,
            'quantity' => 100,
        ];

        // 验证方法成功执行而不抛出异常
        $result = null;
        try {
            $this->stockValidator->validateCreateBatchData($data);
            $result = 'success';
        } catch (\Exception $e) {
            self::fail('不应该抛出异常: ' . $e->getMessage());
        }

        $this->assertEquals('success', $result);
    }

    public function testValidateBatchCompatibilityWithNullLocation(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => null,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => null,
        ]);

        $batches = [$batch1, $batch2];

        // 不应该抛出异常 - null值应该匹配
        $this->expectNotToPerformAssertions();
        $this->stockValidator->validateBatchCompatibility($batches);
    }

    public function testValidateBatchCompatibilityMixedNullAndLocationId(): void
    {
        $sku = $this->createSku('SKU001');

        $batch1 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => null,
        ]);

        $batch2 = $this->createStockBatch([
            'sku' => $sku,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不兼容：位置不同');

        $this->stockValidator->validateBatchCompatibility($batches);
    }
}
