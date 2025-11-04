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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\InboundService;

/**
 * @internal
 */
interface MockEntityManager // @phpstan-ignore-line
{
    public function getPersistCount(): int;

    public function getFlushCount(): int;
}

/**
 * @internal
 */
interface MockBatchRepository
{
    /**
     * @param array<string, mixed> $criteria
     */
    public function setFindResult(array $criteria, ?object $result): void;

    public function setFindByIdResult(mixed $id, ?object $result): void;

    /**
     * @param array<object|null> $sequence
     */
    public function setFindOneBySequence(array $sequence): void;

    public function resetFindOneBySequence(): void;
}

/**
 * @internal
 */
#[CoversClass(InboundService::class)]
class InboundServiceTest extends TestCase
{
    private InboundService $service;

    private EntityManagerInterface&MockEntityManager $entityManager;

    private StockBatchRepository&MockBatchRepository $batchRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = new class implements EntityManagerInterface, MockEntityManager {
            private int $persistCount = 0;

            private int $flushCount = 0;

            public function persist(object $object): void
            {
                ++$this->persistCount;
            }

            public function flush(): void
            {
                ++$this->flushCount;
            }

            public function getPersistCount(): int
            {
                return $this->persistCount;
            }

            public function getFlushCount(): int
            {
                return $this->flushCount;
            }

            // 实现其他必需的接口方法
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

            /**
             * @template T of object
             * @param class-string<T> $className
             * @return EntityRepository<T>
             */
            public function getRepository(string $className): EntityRepository
            {
                return new EntityRepository($this, new ClassMetadata($className));
            }

            public function getClassMetadata(string $className): ClassMetadata
            {
                /** @var class-string<object> $className */
                /** @var ClassMetadata<object> */
                return new ClassMetadata($className);
            }

            /** @return OrmClassMetadataFactory */
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

            public function getPartialReference(string $entityName, mixed $identifier): never
            {
                throw new \LogicException('Not implemented');
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

        $this->batchRepository = new class([]) extends StockBatchRepository implements MockBatchRepository {
            /** @var array<string, object|null> */
            private array $findResults = [];

            /** @var array<mixed, object|null> */
            private array $findByIdResults = [];

            /** @var array<object|null> */
            private array $findOneBySequence = [];

            private int $findOneByCallCount = 0;

            /**
             * @param array<mixed> $args
             */
            public function __construct(array $args = []) // @phpstan-ignore constructor.missingParentCall, constructor.unusedParameter
            {
                // Note: 跳过父类构造函数以避免依赖问题（测试Mock不需要完整初始化）
                // phpcs:ignore SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?StockBatch
            {
                // 如果设置了序列返回值，使用序列
                if (count($this->findOneBySequence) > 0) {
                    $result = $this->findOneBySequence[$this->findOneByCallCount] ?? null;
                    ++$this->findOneByCallCount;
                    if (!($result instanceof StockBatch || null === $result)) {
                        throw new \LogicException('Invalid result type in findOneBySequence');
                    }

                    return $result;
                }

                // 否则使用标准行为
                $key = serialize($criteria);
                $result = $this->findResults[$key] ?? null;
                if (!($result instanceof StockBatch || null === $result)) {
                    throw new \LogicException('Invalid result type in findResults');
                }

                return $result;
            }

            public function find(mixed $id, LockMode|int|null $lockMode = null, \DateTimeInterface|int|null $lockVersion = null): ?StockBatch
            {
                if (!is_int($id) && !is_string($id)) {
                    throw new \LogicException('ID must be int or string');
                }

                $result = $this->findByIdResults[$id] ?? null;
                if (!($result instanceof StockBatch || null === $result)) {
                    throw new \LogicException('Invalid result type in findByIdResults');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $criteria
             */
            public function setFindResult(array $criteria, ?object $result): void
            {
                $key = serialize($criteria);
                $this->findResults[$key] = $result;
            }

            public function setFindByIdResult(mixed $id, ?object $result): void
            {
                if (!is_int($id) && !is_string($id)) {
                    throw new \LogicException('ID must be int or string');
                }

                $this->findByIdResults[$id] = $result;
            }

            /**
             * @param array<object|null> $sequence
             */
            public function setFindOneBySequence(array $sequence): void
            {
                $this->findOneBySequence = $sequence;
                $this->findOneByCallCount = 0;
            }

            public function resetFindOneBySequence(): void
            {
                $this->findOneBySequence = [];
                $this->findOneByCallCount = 0;
            }
        };

        $this->service = new InboundService(
            $this->entityManager,
            $this->batchRepository
        );
    }

    private function createSku(string $skuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($skuId);

        return $sku;
    }

    public function testPurchaseInbound(): void
    {
        $data = [
            'purchase_order_no' => 'PO202412001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'batch_no' => 'BATCH001',
                    'quantity' => 100,
                    'unit_cost' => 10.50,
                    'quality_level' => 'A',
                ],
            ],
            'operator' => 'user_123',
            'location_id' => 'WH001',
            'notes' => '采购入库测试',
        ];

        // 设置批次不存在，创建新批次
        $this->batchRepository->setFindResult(['batchNo' => 'BATCH001'], null);

        $result = $this->service->purchaseInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $this->assertEquals(StockInboundType::PURCHASE, $result->getType());
        $this->assertEquals('PO202412001', $result->getReferenceNo());
        $this->assertEquals('user_123', $result->getOperator());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('采购入库测试', $result->getRemark());

        // 验证EntityManager被调用的次数
        $this->assertEquals(2, $this->entityManager->getPersistCount());
        $this->assertEquals(2, $this->entityManager->getFlushCount());
    }

    public function testReturnInbound(): void
    {
        $data = [
            'return_order_no' => 'RET202412001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'batch_no' => 'BATCH001',
                    'quantity' => 50,
                    'quality_level' => 'A',
                ],
            ],
            'operator' => 'user_123',
        ];

        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);
        $batch->setAvailableQuantity(80);
        $batch->setUnitCost(10.0);
        $batch->setQualityLevel('A');
        $batch->setStatus('available');

        // 设置findOneBy的返回结果
        $this->batchRepository->setFindResult(['batchNo' => 'BATCH001'], $batch);

        $result = $this->service->returnInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $this->assertEquals(StockInboundType::RETURN, $result->getType());
        $this->assertEquals('RET202412001', $result->getReferenceNo());

        // 验证批次库存已更新
        $this->assertEquals(150, $batch->getQuantity());
        $this->assertEquals(130, $batch->getAvailableQuantity());

        // 验证EntityManager被调用的次数
        $this->assertEquals(1, $this->entityManager->getPersistCount());
        $this->assertEquals(1, $this->entityManager->getFlushCount());
    }

    public function testTransferInbound(): void
    {
        $batch = new StockBatch();
        $sku = new Sku();
        $sku->setGtin('SPU001');
        $batch->setSku($sku);
        $batch->setBatchNo('BATCH001');
        $batch->setUnitCost(10.0);
        $batch->setQualityLevel('A');
        $batch->setLocationId('WH001');

        $data = [
            'transfer_no' => 'TR202412001',
            'from_location' => 'WH001',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 30,
                ],
            ],
            'operator' => 'user_123',
            'location_id' => 'WH002',
        ];

        // 设置find方法的返回结果
        $this->batchRepository->setFindByIdResult('1', $batch);

        $result = $this->service->transferInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $this->assertEquals(StockInboundType::TRANSFER, $result->getType());
        $this->assertEquals('TR202412001', $result->getReferenceNo());

        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('from_location', $metadata);
        $this->assertEquals('WH001', $metadata['from_location']);

        // 验证批次的位置已更新
        $this->assertEquals('WH002', $batch->getLocationId());

        // 验证EntityManager被调用的次数
        $this->assertEquals(1, $this->entityManager->getPersistCount());
        $this->assertEquals(1, $this->entityManager->getFlushCount());
    }

    public function testProductionInbound(): void
    {
        $data = [
            'production_order_no' => 'PROD202412001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'batch_no' => 'BATCH002',
                    'quantity' => 200,
                    'unit_cost' => 8.0,
                    'quality_level' => 'A',
                    'production_date' => new \DateTimeImmutable('2024-12-08'),
                ],
            ],
            'operator' => 'user_123',
        ];

        // 设置批次不存在
        $this->batchRepository->setFindResult(['batchNo' => 'BATCH002'], null);

        $result = $this->service->productionInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $this->assertEquals(StockInboundType::PRODUCTION, $result->getType());
        $this->assertEquals('PROD202412001', $result->getReferenceNo());

        $items = $result->getItems();
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertIsArray($items[0]);
        $this->assertArrayHasKey('production_date', $items[0]);

        // 验证EntityManager被调用的次数
        $this->assertEquals(2, $this->entityManager->getPersistCount());
        $this->assertEquals(2, $this->entityManager->getFlushCount());
    }

    public function testPurchaseInboundWithExistingBatch(): void
    {
        $data = [
            'purchase_order_no' => 'PO202412002',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'batch_no' => 'BATCH001',
                    'quantity' => 50,
                    'unit_cost' => 11.0,
                    'quality_level' => 'A',
                ],
            ],
            'operator' => 'user_123',
        ];

        $batch = new StockBatch();
        $batch->setSku($this->createSku('SPU001'));
        $batch->setBatchNo('BATCH001');
        $batch->setQuantity(100);
        $batch->setAvailableQuantity(100);
        $batch->setUnitCost(10.0);
        $batch->setQualityLevel('A');
        $batch->setStatus('available');

        // 设置findOneBy返回现有批次
        $this->batchRepository->setFindResult(['batchNo' => 'BATCH001'], $batch);

        $result = $this->service->purchaseInbound($data);

        // 验证批次库存和成本已更新
        $this->assertEquals(150, $batch->getQuantity());
        $this->assertEquals(150, $batch->getAvailableQuantity());
        // 加权平均成本: (100 * 10 + 50 * 11) / 150 = 10.333...
        $this->assertEqualsWithDelta(10.333, $batch->getUnitCost(), 0.001);

        // 验证EntityManager被调用的次数
        $this->assertEquals(1, $this->entityManager->getPersistCount());
        $this->assertEquals(2, $this->entityManager->getFlushCount());
    }

    public function testInvalidDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field: purchase_order_no');

        $this->service->purchaseInbound([
            'items' => [],
            'operator' => 'user_123',
        ]);
    }

    public function testEmptyItemsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Items cannot be empty');

        $this->service->purchaseInbound([
            'purchase_order_no' => 'PO202412001',
            'items' => [],
            'operator' => 'user_123',
        ]);
    }

    public function testInvalidQuantityThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than 0');

        $this->service->purchaseInbound([
            'purchase_order_no' => 'PO202412001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'batch_no' => 'BATCH001',
                    'quantity' => 0,
                    'unit_cost' => 10.0,
                    'quality_level' => 'A',
                ],
            ],
            'operator' => 'user_123',
        ]);
    }

    public function testTransferInboundBatchNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Batch not found: 999');

        // 设置find方法返回null（批次不存在）
        $this->batchRepository->setFindByIdResult('999', null);

        $this->service->transferInbound([
            'transfer_no' => 'TR202412001',
            'from_location' => 'WH001',
            'items' => [
                [
                    'batch_id' => '999',
                    'quantity' => 30,
                ],
            ],
            'operator' => 'user_123',
        ]);
    }

    public function testGenerateBatchNo(): void
    {
        // 测试采购类型
        $purchaseBatchNo = $this->service->generateBatchNo('purchase');
        $this->assertStringStartsWith('PUR-', $purchaseBatchNo);
        $this->assertMatchesRegularExpression('/^PUR-\d{8}-\d{4}$/', $purchaseBatchNo);

        // 测试生产类型
        $productionBatchNo = $this->service->generateBatchNo('production');
        $this->assertStringStartsWith('PROD-', $productionBatchNo);
        $this->assertMatchesRegularExpression('/^PROD-\d{8}-\d{4}$/', $productionBatchNo);

        // 测试退货类型
        $returnBatchNo = $this->service->generateBatchNo('return');
        $this->assertStringStartsWith('RET-', $returnBatchNo);
        $this->assertMatchesRegularExpression('/^RET-\d{8}-\d{4}$/', $returnBatchNo);

        // 测试调拨类型
        $transferBatchNo = $this->service->generateBatchNo('transfer');
        $this->assertStringStartsWith('TRF-', $transferBatchNo);
        $this->assertMatchesRegularExpression('/^TRF-\d{8}-\d{4}$/', $transferBatchNo);

        // 测试调整类型
        $adjustmentBatchNo = $this->service->generateBatchNo('adjustment');
        $this->assertStringStartsWith('ADJ-', $adjustmentBatchNo);
        $this->assertMatchesRegularExpression('/^ADJ-\d{8}-\d{4}$/', $adjustmentBatchNo);

        // 测试大写类型
        $upperCaseBatchNo = $this->service->generateBatchNo('PURCHASE');
        $this->assertStringStartsWith('PUR-', $upperCaseBatchNo);

        // 测试未知类型使用默认前缀
        $unknownBatchNo = $this->service->generateBatchNo('unknown');
        $this->assertStringStartsWith('BATCH-', $unknownBatchNo);
        $this->assertMatchesRegularExpression('/^BATCH-\d{8}-\d{4}$/', $unknownBatchNo);

        // 验证批次号格式包含当前日期
        $today = date('Ymd');
        $this->assertStringContainsString($today, $purchaseBatchNo);
    }

    public function testGenerateUniqueBatchNo(): void
    {
        // 测试生成唯一批次号 - 第一次尝试就成功
        $this->batchRepository->setFindOneBySequence([null]); // 批次不存在，返回唯一批次号

        $uniqueBatchNo = $this->service->generateUniqueBatchNo('purchase');
        $this->assertStringStartsWith('PUR-', $uniqueBatchNo);
        $this->assertMatchesRegularExpression('/^PUR-\d{8}-\d{4}$/', $uniqueBatchNo);
    }

    public function testGenerateUniqueBatchNoWithRetry(): void
    {
        $existingBatch = new StockBatch();

        // Mock第一次查询返回存在的批次，第二次返回null
        $this->batchRepository->setFindOneBySequence([$existingBatch, null]);

        $uniqueBatchNo = $this->service->generateUniqueBatchNo('production');
        $this->assertStringStartsWith('PROD-', $uniqueBatchNo);
    }

    public function testGenerateUniqueBatchNoFailsAfterMaxAttempts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to generate unique batch number after multiple attempts');

        $existingBatch = new StockBatch();

        // Mock所有尝试都返回存在的批次
        $this->batchRepository->setFindOneBySequence([$existingBatch, $existingBatch, $existingBatch, $existingBatch, $existingBatch]);

        $this->service->generateUniqueBatchNo('purchase', 5);
    }

    public function testGenerateUniqueBatchNoWithDifferentTypes(): void
    {
        // 测试不同类型的唯一批次号生成
        $types = ['purchase', 'production', 'return', 'transfer', 'adjustment'];
        $expectedPrefixes = ['PUR', 'PROD', 'RET', 'TRF', 'ADJ'];

        // 设置每次调用都返回null（批次不存在）
        $this->batchRepository->setFindOneBySequence([null, null, null, null, null]);

        foreach ($types as $index => $type) {
            $batchNo = $this->service->generateUniqueBatchNo($type);
            $this->assertStringStartsWith($expectedPrefixes[$index] . '-', $batchNo);
        }
    }

    public function testGenerateUniqueBatchNoDefaultMaxAttempts(): void
    {
        $existingBatch = new StockBatch();

        // Mock默认最大尝试次数（10次）都返回存在的批次
        $this->batchRepository->setFindOneBySequence(array_fill(0, 10, $existingBatch));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to generate unique batch number after multiple attempts');

        $this->service->generateUniqueBatchNo('purchase'); // 使用默认maxAttempts=10
    }

    public function testCreateOrUpdateBatch(): void
    {
        $sku = $this->createSku('SPU001');
        $item = [
            'sku' => $sku,
            'batch_no' => 'TEST-BATCH-001',
            'quantity' => 100,
            'unit_cost' => 10.0,
            'quality_level' => 'A',
        ];

        // Mock repository返回null表示批次不存在，需要创建新批次
        $this->batchRepository->setFindResult(['batchNo' => 'TEST-BATCH-001'], null);

        $batch = $this->service->createOrUpdateBatch($item, 'purchase');

        $this->assertInstanceOf(StockBatch::class, $batch);
        $this->assertEquals('TEST-BATCH-001', $batch->getBatchNo());
        $this->assertEquals(100, $batch->getQuantity());
        $this->assertEquals(100, $batch->getAvailableQuantity());
        $this->assertEquals(10.0, $batch->getUnitCost());
        $this->assertEquals('A', $batch->getQualityLevel());
        $this->assertEquals('available', $batch->getStatus());
    }

    public function testCreateOrUpdateBatchUpdateExisting(): void
    {
        $sku = $this->createSku('SPU002');
        $existingBatch = new StockBatch();
        $existingBatch->setSku($sku);
        $existingBatch->setBatchNo('EXISTING-BATCH');
        $existingBatch->setQuantity(50);
        $existingBatch->setAvailableQuantity(50);
        $existingBatch->setUnitCost(12.0);

        $item = [
            'sku' => $sku,
            'batch_no' => 'EXISTING-BATCH',
            'quantity' => 30,
            'unit_cost' => 8.0,
            'quality_level' => 'A',
        ];

        // Mock repository返回现有批次
        $this->batchRepository->setFindResult(['batchNo' => 'EXISTING-BATCH'], $existingBatch);

        $batch = $this->service->createOrUpdateBatch($item, 'purchase');

        // 验证数量增加
        $this->assertEquals(80, $batch->getQuantity()); // 50 + 30
        $this->assertEquals(80, $batch->getAvailableQuantity()); // 50 + 30

        // 验证加权平均成本: (50 * 12.0 + 30 * 8.0) / 80 = 10.5
        $this->assertEqualsWithDelta(10.5, $batch->getUnitCost(), 0.001);
    }

    public function testCreateOrUpdateBatchWithAutoBatchNo(): void
    {
        $sku = $this->createSku('SPU003');
        $item = [
            'sku' => $sku,
            // 没有提供batch_no，应该自动生成
            'quantity' => 25,
            'unit_cost' => 15.0,
            'quality_level' => 'B',
        ];

        // Mock repository查询批次号返回null（不存在）
        // 会被调用两次：一次在generateUniqueBatchNo中，一次在createOrUpdateBatch中
        $this->batchRepository->setFindOneBySequence([null, null]);

        $batch = $this->service->createOrUpdateBatch($item, 'production');

        $this->assertInstanceOf(StockBatch::class, $batch);
        $this->assertStringStartsWith('PROD-', $batch->getBatchNo());
        $this->assertEquals(25, $batch->getQuantity());
        $this->assertEquals(15.0, $batch->getUnitCost());
        $this->assertEquals('B', $batch->getQualityLevel());
    }

    public function testAdjustmentInbound(): void
    {
        $data = [
            'adjustment_no' => 'ADJ202412001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'quantity' => 75,
                    'unit_cost' => 12.0,
                    'quality_level' => 'A',
                    'reason' => '盘点调整',
                ],
            ],
            'operator' => 'user_123',
            'location_id' => 'WH001',
            'notes' => '库存调整入库',
        ];

        // Mock批次不存在，创建新批次
        // 会被调用两次：一次在generateUniqueBatchNo中，一次在createOrUpdateBatch中
        $this->batchRepository->setFindOneBySequence([null, null]);

        $result = $this->service->adjustmentInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $this->assertEquals(StockInboundType::ADJUSTMENT, $result->getType());
        $this->assertEquals('ADJ202412001', $result->getReferenceNo());
        $this->assertEquals('user_123', $result->getOperator());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('库存调整入库', $result->getRemark());

        $items = $result->getItems();
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertIsArray($items[0]);
        $this->assertEquals(75, $items[0]['quantity']);
        $this->assertEquals(12.0, $items[0]['unit_cost']);
        $this->assertEquals(900.0, $items[0]['amount']);
        $this->assertEquals('A', $items[0]['quality_level']);
        $this->assertEquals('盘点调整', $items[0]['reason']);

        // 验证EntityManager被调用的次数
        $this->assertEquals(2, $this->entityManager->getPersistCount());
        $this->assertEquals(2, $this->entityManager->getFlushCount());
    }

    public function testAdjustmentInboundWithDefaultValues(): void
    {
        $data = [
            'adjustment_no' => 'ADJ202412002',
            'items' => [
                [
                    'sku' => $this->createSku('SPU002'),
                    'quantity' => 50,
                    'reason' => '系统调整',
                    // 没有提供unit_cost和quality_level，使用默认值
                ],
            ],
            'operator' => 'system',
        ];

        $this->batchRepository->setFindOneBySequence([null, null]);

        $result = $this->service->adjustmentInbound($data);

        $this->assertInstanceOf(StockInbound::class, $result);
        $items = $result->getItems();
        $this->assertIsArray($items);
        $this->assertCount(1, $items);
        $this->assertIsArray($items[0]);
        $this->assertEquals(0.0, $items[0]['unit_cost']);
        $this->assertEquals(0.0, $items[0]['amount']);
        $this->assertEquals('A', $items[0]['quality_level']);
        $this->assertEquals('系统调整', $items[0]['reason']);

        // 验证EntityManager被调用的次数
        $this->assertEquals(2, $this->entityManager->getPersistCount());
        $this->assertEquals(2, $this->entityManager->getFlushCount());
    }

    public function testAdjustmentInboundMissingReason(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required field in item: reason');

        $this->service->adjustmentInbound([
            'adjustment_no' => 'ADJ202412003',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'quantity' => 25,
                    // 缺少必需的reason字段
                ],
            ],
            'operator' => 'user_123',
        ]);
    }
}
