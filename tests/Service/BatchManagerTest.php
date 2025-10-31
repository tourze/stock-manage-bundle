<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Cache;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory as OrmClassMetadataFactory;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\FilterCollection;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\BatchManager;
use Tourze\StockManageBundle\Service\StockValidator;

/**
 * @internal
 */
#[CoversClass(BatchManager::class)]
final class BatchManagerTest extends TestCase
{
    private BatchManager $batchManager;

    private EntityManagerInterface $entityManager;

    private StockValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = new class implements EntityManagerInterface {
            private int $persistCallCount = 0;

            private int $flushCallCount = 0;

            public function persist(object $object): void
            {
                ++$this->persistCallCount;
            }

            public function flush(): void
            {
                ++$this->flushCallCount;
            }

            public function getPersistCallCount(): int
            {
                return $this->persistCallCount;
            }

            public function getFlushCallCount(): int
            {
                return $this->flushCallCount;
            }

            // Required interface implementations
            public function find(string $className, mixed $id, LockMode|int|null $lockMode = null, \DateTimeInterface|int|null $lockVersion = null): ?object
            {
                return null;
            }

            public function remove(object $object): void
            {
            }

            public function merge(object $object): object
            {
                return $object;
            }

            public function clear(?string $objectName = null): void
            {
            }

            public function detach(object $object): void
            {
            }

            public function refresh(object $object, LockMode|int|null $lockMode = null): void
            {
            }

            public function getRepository(string $className): EntityRepository
            {
                throw new \LogicException('Not implemented');
            }

            public function getClassMetadata(string $className): ClassMetadata
            {
                throw new \LogicException('Not implemented');
            }

            /**
             * @return OrmClassMetadataFactory
             */
            public function getMetadataFactory(): OrmClassMetadataFactory // @phpstan-ignore method.childReturnType
            {
                throw new \LogicException('Not implemented');
            }

            public function initializeObject(object $obj): void
            {
            }

            public function contains(object $object): bool
            {
                return false;
            }

            public function getUnitOfWork(): UnitOfWork
            {
                throw new \LogicException('Not implemented');
            }

            public function getCache(): ?Cache
            {
                return null;
            }

            public function getConnection(): Connection
            {
                throw new \LogicException('Not implemented');
            }

            public function getExpressionBuilder(): Expr
            {
                throw new \LogicException('Not implemented');
            }

            public function beginTransaction(): void
            {
            }

            public function transactional(callable $func): mixed
            {
                return $func($this);
            }

            public function commit(): void
            {
            }

            public function rollback(): void
            {
            }

            public function createQuery(string $dql = ''): Query
            {
                throw new \LogicException('Not implemented');
            }

            public function createNamedQuery(string $name): Query
            {
                throw new \LogicException('Not implemented');
            }

            public function createNativeQuery(string $sql, ResultSetMapping $rsm): NativeQuery
            {
                throw new \LogicException('Not implemented');
            }

            public function createNamedNativeQuery(string $name): NativeQuery
            {
                throw new \LogicException('Not implemented');
            }

            public function createQueryBuilder(): QueryBuilder
            {
                throw new \LogicException('Not implemented');
            }

            public function getReference(string $entityName, mixed $id): ?object
            {
                return null;
            }

            public function getPartialReference(string $entityName, mixed $identifier): null
            {
                return null;
            }

            public function close(): void
            {
            }

            public function copy(object $entity, bool $deep = false): object
            {
                return $entity;
            }

            public function lock(object $entity, LockMode|int $lockMode, \DateTimeInterface|int|null $lockVersion = null): void
            {
            }

            public function getEventManager(): EventManager
            {
                throw new \LogicException('Not implemented');
            }

            public function getConfiguration(): Configuration
            {
                throw new \LogicException('Not implemented');
            }

            public function isOpen(): bool
            {
                return true;
            }

            public function getFilters(): FilterCollection
            {
                throw new \LogicException('Not implemented');
            }

            public function isFiltersStateClean(): bool
            {
                return true;
            }

            public function hasFilters(): bool
            {
                return false;
            }

            public function wrapInTransaction(callable $func): mixed
            {
                return $func($this);
            }

            public function newHydrator(string|int $hydrationMode): AbstractHydrator
            {
                throw new \LogicException('Not implemented');
            }

            public function getProxyFactory(): ProxyFactory
            {
                throw new \LogicException('Not implemented');
            }

            public function isUninitializedObject(mixed $value): bool
            {
                return false;
            }
        };

        // 创建Repository stub（使用createMock避免父构造函数问题）
        $repositoryStub = $this->createMock(StockBatchRepository::class);

        $this->validator = new class($repositoryStub) extends StockValidator {
            public function __construct(StockBatchRepository $repository)
            {
                parent::__construct($repository);
            }

            public function validateCreateBatchData(array $data): void
            {
                // Mock实现：不执行实际验证
            }

            public function validateBatchCompatibility(array $batches): void
            {
                // Mock实现：不执行实际验证
            }

            public function validateBatchStatus(string $status): void
            {
                // Mock实现：不执行实际验证
            }

            public function validateQuantityAdjustment(StockBatch $batch, int $adjustment): void
            {
                // Mock实现：不执行实际验证
            }
        };

        $this->batchManager = new BatchManager($this->entityManager, $this->validator);
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

            // 实现其他必需的接口方法
            public function getGtin(): string
            {
                return $this->id;
            }

            public function setGtin(string $gtin): void
            {
            }

            public function getName(): null
            {
                return null;
            }

            public function setName(?string $name): void
            {
            }

            public function getDescription(): null
            {
                return null;
            }

            public function setDescription(?string $description): void
            {
            }

            public function getBrand(): null
            {
                return null;
            }

            public function setBrand(?string $brand): void
            {
            }

            public function getCategory(): null
            {
                return null;
            }

            public function setCategory(?string $category): void
            {
            }

            public function getUnitOfMeasure(): null
            {
                return null;
            }

            public function setUnitOfMeasure(?string $unitOfMeasure): void
            {
            }

            public function getWeight(): null
            {
                return null;
            }

            public function setWeight(?float $weight): void
            {
            }

            public function getDimensions(): null
            {
                return null;
            }

            /** @param array<mixed>|null $dimensions */
            public function setDimensions(?array $dimensions): void
            {
            }

            public function isActive(): bool
            {
                return true;
            }

            public function setActive(bool $active): void
            {
            }

            public function getCreateTime(): null
            {
                return null;
            }

            public function getUpdateTime(): null
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

            public function isValid(): bool
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
        $batch = new StockBatch();

        $batch->setBatchNo($this->getStringValue($data, 'batch_no', 'BATCH001'));
        // 先检查类型，再设置默认值
        $sku = $data['sku'] ?? null;
        if (null !== $sku && !$sku instanceof SKU) {
            throw new \LogicException('SKU must be SKU instance or null');
        }
        $batch->setSku($sku ?? $this->createMockSku('SKU001'));
        $batch->setQuantity($this->getIntValue($data, 'quantity', 100));
        $batch->setAvailableQuantity($this->getIntValue($data, 'available_quantity', $this->getIntValue($data, 'quantity', 100)));
        $batch->setUnitCost($this->getFloatValue($data, 'unit_cost', 10.50));
        $batch->setQualityLevel($this->getStringValue($data, 'quality_level', 'A'));
        $batch->setLocationId($this->getStringValue($data, 'location_id', 'WH001'));
        $batch->setStatus($this->getStringValue($data, 'status', 'available'));

        if (isset($data['production_date']) && $data['production_date'] instanceof \DateTimeImmutable) {
            $batch->setProductionDate($data['production_date']);
        }
        if (isset($data['expiry_date']) && $data['expiry_date'] instanceof \DateTimeImmutable) {
            $batch->setExpiryDate($data['expiry_date']);
        }

        return $batch;
    }

    /** @param array<string, mixed> $data */
    private function getStringValue(array $data, string $key, string $default): string
    {
        $value = $data[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    private function getIntValue(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }

    /** @param array<string, mixed> $data */
    private function getFloatValue(array $data, string $key, float $default): float
    {
        $value = $data[$key] ?? $default;

        return is_float($value) || is_int($value) ? (float) $value : $default;
    }

    public function testCreateBatch(): void
    {
        $sku = $this->createMockSku('SKU001');
        $data = [
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ];

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('BATCH001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(100, $result->getQuantity());
        $this->assertEquals(100, $result->getAvailableQuantity());
        $this->assertEquals(0, $result->getReservedQuantity());
        $this->assertEquals(0, $result->getLockedQuantity());
        $this->assertEquals(10.50, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());
    }

    public function testCreateBatchWithDefaultValues(): void
    {
        $sku = $this->createMockSku('SKU001');
        $data = [
            'sku' => $sku,
            'quantity' => 100,
        ];

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->createBatch($data);

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertStringStartsWith('BATCH_', $result->getBatchNo());
        $this->assertEquals(0.00, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('available', $result->getStatus());
        $this->assertNull($result->getLocationId());
    }

    public function testCreateBatchWithDates(): void
    {
        $sku = $this->createMockSku('SKU001');
        $productionDate = new \DateTimeImmutable('2024-01-01');
        $expiryDate = new \DateTimeImmutable('2024-12-31');

        $data = [
            'sku' => $sku,
            'quantity' => 100,
            'production_date' => $productionDate,
            'expiry_date' => $expiryDate,
        ];

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->createBatch($data);

        $this->assertEquals($productionDate, $result->getProductionDate());
        $this->assertEquals($expiryDate, $result->getExpiryDate());
    }

    public function testMergeBatches(): void
    {
        $sku = $this->createMockSku('SKU001');

        $batch1 = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'unit_cost' => 10.00,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batch2 = $this->createStockBatch([
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'quantity' => 150,
            'unit_cost' => 12.00,
            'quality_level' => 'A',
            'location_id' => 'WH001',
        ]);

        $batches = [$batch1, $batch2];

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->mergeBatches($batches, 'MERGED001');

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('MERGED001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(250, $result->getQuantity()); // 100 + 150
        $this->assertEquals(250, $result->getAvailableQuantity());
        // 平均成本: (100*10.00 + 150*12.00) / 250 = 2800 / 250 = 11.2
        $this->assertEquals(11.2, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());

        // 验证原批次被标记为已耗尽
        $this->assertEquals('depleted', $batch1->getStatus());
        $this->assertEquals(0, $batch1->getAvailableQuantity());
        $this->assertEquals('depleted', $batch2->getStatus());
        $this->assertEquals(0, $batch2->getAvailableQuantity());
    }

    public function testSplitBatch(): void
    {
        $sku = $this->createMockSku('SKU001');
        $originalBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 200,
            'available_quantity' => 180,
            'unit_cost' => 10.50,
            'quality_level' => 'A',
            'location_id' => 'WH001',
            'production_date' => new \DateTimeImmutable('2024-01-01'),
            'expiry_date' => new \DateTimeImmutable('2024-12-31'),
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->splitBatch($originalBatch, 80, 'SPLIT001');

        $this->assertInstanceOf(StockBatch::class, $result);
        $this->assertEquals('SPLIT001', $result->getBatchNo());
        $this->assertEquals($sku, $result->getSku());
        $this->assertEquals(80, $result->getQuantity());
        $this->assertEquals(80, $result->getAvailableQuantity());
        $this->assertEquals(10.50, $result->getUnitCost());
        $this->assertEquals('A', $result->getQualityLevel());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('available', $result->getStatus());

        // 验证原批次被正确更新
        $this->assertEquals(120, $originalBatch->getQuantity()); // 200 - 80
        $this->assertEquals(100, $originalBatch->getAvailableQuantity()); // 180 - 80
    }

    public function testSplitBatchWithZeroRemainingQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');
        $originalBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $result = $this->batchManager->splitBatch($originalBatch, 100, 'SPLIT001');

        $this->assertEquals(100, $result->getQuantity());

        // 验证原批次被标记为已耗尽
        $this->assertEquals(0, $originalBatch->getQuantity());
        $this->assertEquals(0, $originalBatch->getAvailableQuantity());
        $this->assertEquals('depleted', $originalBatch->getStatus());
    }

    public function testSplitBatchWithInvalidQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');
        $originalBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('拆分数量必须大于0且小于等于原批次数量');

        $this->batchManager->splitBatch($originalBatch, 0, 'SPLIT001');
    }

    public function testSplitBatchWithQuantityExceedingTotal(): void
    {
        $sku = $this->createMockSku('SKU001');
        $originalBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 100,
        ]);

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('拆分数量必须大于0且小于等于原批次数量');

        $this->batchManager->splitBatch($originalBatch, 150, 'SPLIT001');
    }

    public function testSplitBatchWithInsufficientAvailable(): void
    {
        $sku = $this->createMockSku('SKU001');

        $originalBatch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 50, // 只有50可用，但要拆分80
        ]);

        $this->expectException(InsufficientStockException::class);

        $this->batchManager->splitBatch($originalBatch, 80, 'SPLIT001');
    }

    public function testUpdateBatchStatus(): void
    {
        $sku = $this->createMockSku('SKU001');
        $batch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'status' => 'available',
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $this->batchManager->updateBatchStatus($batch, 'quarantined');

        $this->assertEquals('quarantined', $batch->getStatus());
    }

    public function testAdjustBatchQuantityPositive(): void
    {
        $sku = $this->createMockSku('SKU001');
        $batch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $this->batchManager->adjustBatchQuantity($batch, 50);

        $this->assertEquals(150, $batch->getQuantity()); // 100 + 50
        $this->assertEquals(130, $batch->getAvailableQuantity()); // 80 + 50
    }

    public function testAdjustBatchQuantityNegative(): void
    {
        $sku = $this->createMockSku('SKU001');
        $batch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $this->batchManager->adjustBatchQuantity($batch, -30);

        $this->assertEquals(70, $batch->getQuantity()); // 100 - 30
        $this->assertEquals(50, $batch->getAvailableQuantity()); // 80 - 30
    }

    public function testAdjustBatchQuantityLargeNegativeAdjustment(): void
    {
        $sku = $this->createMockSku('SKU001');
        $batch = $this->createStockBatch([
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 50,
        ]);

        // Mock验证已在匿名类中实现，无需expects调用

        $this->batchManager->adjustBatchQuantity($batch, -80);

        $this->assertEquals(20, $batch->getQuantity()); // 100 - 80
        // 可用数量调整受限于当前可用数量
        $this->assertEquals(0, $batch->getAvailableQuantity()); // min(-80, 50) = -50, 50 + (-50) = 0
    }
}
