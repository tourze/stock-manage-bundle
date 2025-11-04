<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\DuplicateBatchException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Exception\InvalidQuantityException;
use Tourze\StockManageBundle\Exception\InvalidStatusException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\StockValidator;

/**
 * @internal
 */
#[CoversClass(StockValidator::class)]
final class StockValidatorTest extends TestCase
{
    private StockValidator $stockValidator;

    private StockBatchRepository&MockStockBatchRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new class($this->createMock(ManagerRegistry::class)) extends StockBatchRepository implements MockStockBatchRepository {
            private bool $batchExists = false;

            public function __construct(ManagerRegistry $registry)
            {
                parent::__construct($registry);
            }

            public function existsByBatchNo(string $batchNo): bool
            {
                return $this->batchExists;
            }

            public function setBatchExists(bool $exists): void
            {
                $this->batchExists = $exists;
            }

            public function expectsOnce(string $method, string $with, bool $willReturn): void
            {
                if ('existsByBatchNo' === $method) {
                    $this->batchExists = $willReturn;
                }
            }
        };
        $this->stockValidator = new StockValidator($this->repository);
    }

    private function createMockSku(string $skuId): SKU
    {
        return new class($skuId) implements SKU {
            public function __construct(private string $id)
            {
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getGtin(): ?string
            {
                return null;
            }

            public function getMpn(): ?string
            {
                return null;
            }

            public function getRemark(): ?string
            {
                return null;
            }

            public function isValid(): ?bool
            {
                return true;
            }
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatch(array $data): StockBatch
    {
        return $this->createStockBatchInstance($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatchInstance(array $data): StockBatch
    {
        return new class($data) extends StockBatch {
            /**
             * @param array<string, mixed> $data
             */
            public function __construct(private array $data)
            {
                parent::__construct();
            }

            public function getId(): int
            {
                return $this->extractInt('id', 1);
            }

            public function getBatchNo(): string
            {
                return $this->extractString('batch_no', 'BATCH001');
            }

            public function getSku(): SKU
            {
                if (isset($this->data['sku']) && $this->data['sku'] instanceof SKU) {
                    return $this->data['sku'];
                }

                return $this->createDefaultSku();
            }

            public function getQuantity(): int
            {
                return $this->extractInt('quantity', 100);
            }

            public function getAvailableQuantity(): int
            {
                return $this->extractInt('available_quantity', $this->getQuantity());
            }

            public function getUnitCost(): float
            {
                return $this->extractFloat('unit_cost', 10.50);
            }

            public function getQualityLevel(): string
            {
                return $this->extractString('quality_level', 'A');
            }

            public function getLocationId(): ?string
            {
                $value = array_key_exists('location_id', $this->data) ? $this->data['location_id'] : 'WH001';

                return null === $value ? null : $this->ensureString($value);
            }

            public function getStatus(): string
            {
                return $this->extractString('status', 'available');
            }

            private function extractInt(string $key, int $default): int
            {
                $value = $this->data[$key] ?? $default;

                return match (true) {
                    is_int($value) => $value,
                    is_numeric($value) => (int) $value,
                    default => $default,
                };
            }

            private function extractFloat(string $key, float $default): float
            {
                $value = $this->data[$key] ?? $default;

                return match (true) {
                    is_float($value) => $value,
                    is_numeric($value) => (float) $value,
                    default => $default,
                };
            }

            private function extractString(string $key, string $default): string
            {
                $value = $this->data[$key] ?? $default;

                return match (true) {
                    is_string($value) => $value,
                    is_scalar($value) => (string) $value,
                    default => $default,
                };
            }

            private function ensureString(mixed $value): string
            {
                return match (true) {
                    is_string($value) => $value,
                    is_scalar($value) => (string) $value,
                    default => '',
                };
            }

            private function createDefaultSku(): SKU
            {
                return new class implements SKU {
                    // @phpstan-ignore tip.anonymousTestClass
                    public function getId(): string
                    {
                        return 'SKU001';
                    }

                    public function getGtin(): ?string
                    {
                        return null;
                    }

                    public function getMpn(): ?string
                    {
                        return null;
                    }

                    public function getRemark(): ?string
                    {
                        return null;
                    }

                    public function isValid(): ?bool
                    {
                        return true;
                    }
                };
            }
        };
    }

    public function testValidateCreateBatchDataSuccess(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.50,
        ];

        $this->repository->setBatchExists(false);

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
        $sku = $this->createMockSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
        ];

        $this->repository->setBatchExists(true);

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
        $sku = $this->createMockSku('SKU001');

        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
        ];

        $this->expectException(InvalidQuantityException::class);

        $this->stockValidator->validateCreateBatchData($data);
    }

    public function testValidateCreateBatchDataInvalidQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku1 = $this->createMockSku('SKU001');
        $sku2 = $this->createMockSku('SKU002');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

        $data = [
            'batch_no' => '',
            'sku' => $sku,
            'quantity' => 100,
        ];

        // 空批次号会被检查（因为isset()返回true），但不会重复
        $this->repository->setBatchExists(false);

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
        $sku = $this->createMockSku('SKU001');

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
        $sku = $this->createMockSku('SKU001');

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
