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
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\AllocationService;
use Tourze\StockManageBundle\Service\OutboundProcessor;
use Tourze\StockManageBundle\Service\OutboundService;
use Tourze\StockManageBundle\Service\OutboundValidator;

/**
 * @internal
 */
interface OutboundMockEntityManager // @phpstan-ignore-line
{
    /** @return array<object> */
    public function getPersistedObjects(): array;

    public function isFlushed(): bool;
}

/**
 * @internal
 */
interface MockOutboundValidator
{
    /** @return array<string, array<array<string, mixed>>> */
    public function getCalls(): array;

    public function setException(string $method, \Exception $exception): void;
}

/**
 * @internal
 */
interface MockOutboundProcessor
{
    /** @return array<string, array<array<string, mixed>>> */
    public function getCalls(): array;

    /**
     * @param array<string, mixed> $result
     */
    public function setResult(string $method, array $result): void;

    public function setException(string $method, \Exception $exception): void;
}

/**
 * @internal
 */
#[CoversClass(OutboundService::class)]
class OutboundServiceTest extends TestCase
{
    private OutboundService $service;

    private EntityManagerInterface&OutboundMockEntityManager $entityManager;

    private OutboundValidator&MockOutboundValidator $validator;

    private OutboundProcessor&MockOutboundProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMockEntityManager();
        $this->validator = $this->createMockValidator();
        $this->processor = $this->createMockProcessor();

        $this->service = new OutboundService(
            $this->entityManager,
            $this->validator,
            $this->processor
        );
    }

    private function createMockEntityManager(): EntityManagerInterface&OutboundMockEntityManager
    {
        return new class implements EntityManagerInterface, OutboundMockEntityManager {
            /** @var array<object> */
            private array $persisted = [];

            private bool $flushed = false;

            public function persist(object $object): void
            {
                $this->persisted[] = $object;
            }

            public function flush(): void
            {
                $this->flushed = true;
            }

            /** @return array<object> */
            public function getPersistedObjects(): array
            {
                return $this->persisted;
            }

            public function isFlushed(): bool
            {
                return $this->flushed;
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
    }

    private function createMockValidator(): OutboundValidator&MockOutboundValidator
    {
        return new class extends OutboundValidator implements MockOutboundValidator {
            /** @var array<string, array<array<string, mixed>>> */
            private array $calls = [];

            /** @var array<string, \Exception> */
            private array $exceptions = [];

            public function __construct()
            {
                // 空构造函数，避免调用父类构造函数
            }

            public function validateSalesOutboundData(array $data): void
            {
                $this->calls['validateSalesOutboundData'][] = $data;
                if (isset($this->exceptions['validateSalesOutboundData'])) {
                    throw $this->exceptions['validateSalesOutboundData'];
                }
            }

            public function validateDamageOutboundData(array $data): void
            {
                $this->calls['validateDamageOutboundData'][] = $data;
                if (isset($this->exceptions['validateDamageOutboundData'])) {
                    throw $this->exceptions['validateDamageOutboundData'];
                }
            }

            public function validateTransferOutboundData(array $data): void
            {
                $this->calls['validateTransferOutboundData'][] = $data;
                if (isset($this->exceptions['validateTransferOutboundData'])) {
                    throw $this->exceptions['validateTransferOutboundData'];
                }
            }

            public function validatePickOutboundData(array $data): void
            {
                $this->calls['validatePickOutboundData'][] = $data;
                if (isset($this->exceptions['validatePickOutboundData'])) {
                    throw $this->exceptions['validatePickOutboundData'];
                }
            }

            public function validateAdjustmentOutboundData(array $data): void
            {
                $this->calls['validateAdjustmentOutboundData'][] = $data;
                if (isset($this->exceptions['validateAdjustmentOutboundData'])) {
                    throw $this->exceptions['validateAdjustmentOutboundData'];
                }
            }

            public function setException(string $method, \Exception $exception): void
            {
                $this->exceptions[$method] = $exception;
            }

            /** @return array<string, array<array<string, mixed>>> */
            public function getCalls(): array
            {
                return $this->calls;
            }
        };
    }

    private function createMockProcessor(): OutboundProcessor&MockOutboundProcessor
    {
        // 创建必需的Mock依赖
        $mockEm = $this->createMock(EntityManagerInterface::class);
        $mockRepo = $this->createMock(StockBatchRepository::class);
        $mockAllocation = $this->createMock(AllocationService::class);

        return new class($mockEm, $mockRepo, $mockAllocation) extends OutboundProcessor implements MockOutboundProcessor {
            /** @var array<string, array<array<string, mixed>>> */
            private array $calls = [];

            /** @var array<string, array<string, mixed>> */
            private array $results = [];

            /** @var array<string, \Exception> */
            private array $exceptions = [];

            public function __construct(
                EntityManagerInterface $em,
                StockBatchRepository $repo,
                AllocationService $allocation,
            ) {
                parent::__construct($em, $repo, $allocation);
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return non-empty-array<string, mixed>
             */
            public function processSalesOutbound(array $data): array
            {
                $this->calls['processSalesOutbound'][] = $data;
                if (isset($this->exceptions['processSalesOutbound'])) {
                    throw $this->exceptions['processSalesOutbound'];
                }

                $result = $this->results['processSalesOutbound'] ?? [
                    'requestedItems' => [],
                    'allocatedItems' => [],
                    'totalQuantity' => 0,
                    'totalCost' => 0.0,
                ];

                if (!isset($result['requestedItems'], $result['allocatedItems'], $result['totalQuantity'], $result['totalCost'])) {
                    throw new \LogicException('Invalid result structure in processSalesOutbound');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return non-empty-array<string, mixed>
             */
            public function processDamageOutbound(array $data): array
            {
                $this->calls['processDamageOutbound'][] = $data;
                if (isset($this->exceptions['processDamageOutbound'])) {
                    throw $this->exceptions['processDamageOutbound'];
                }

                $result = $this->results['processDamageOutbound'] ?? [
                    'requestedItems' => [],
                    'allocatedItems' => [],
                    'totalQuantity' => 0,
                    'totalCost' => 0.0,
                ];

                if (!isset($result['requestedItems'], $result['allocatedItems'], $result['totalQuantity'], $result['totalCost'])) {
                    throw new \LogicException('Invalid result structure in processDamageOutbound');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return non-empty-array<string, mixed>
             */
            public function processTransferOutbound(array $data): array
            {
                $this->calls['processTransferOutbound'][] = $data;
                if (isset($this->exceptions['processTransferOutbound'])) {
                    throw $this->exceptions['processTransferOutbound'];
                }

                $result = $this->results['processTransferOutbound'] ?? [
                    'requestedItems' => [],
                    'allocatedItems' => [],
                    'totalQuantity' => 0,
                    'totalCost' => 0.0,
                ];

                if (!isset($result['requestedItems'], $result['allocatedItems'], $result['totalQuantity'], $result['totalCost'])) {
                    throw new \LogicException('Invalid result structure in processTransferOutbound');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return non-empty-array<string, mixed>
             */
            public function processPickOutbound(array $data): array
            {
                $this->calls['processPickOutbound'][] = $data;
                if (isset($this->exceptions['processPickOutbound'])) {
                    throw $this->exceptions['processPickOutbound'];
                }

                $result = $this->results['processPickOutbound'] ?? [
                    'requestedItems' => [],
                    'allocatedItems' => [],
                    'totalQuantity' => 0,
                    'totalCost' => 0.0,
                ];

                if (!isset($result['requestedItems'], $result['allocatedItems'], $result['totalQuantity'], $result['totalCost'])) {
                    throw new \LogicException('Invalid result structure in processPickOutbound');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $data
             *
             * @return non-empty-array<string, mixed>
             */
            public function processAdjustmentOutbound(array $data): array
            {
                $this->calls['processAdjustmentOutbound'][] = $data;
                if (isset($this->exceptions['processAdjustmentOutbound'])) {
                    throw $this->exceptions['processAdjustmentOutbound'];
                }

                $result = $this->results['processAdjustmentOutbound'] ?? [
                    'requestedItems' => [],
                    'allocatedItems' => [],
                    'totalQuantity' => 0,
                    'totalCost' => 0.0,
                ];

                if (!isset($result['requestedItems'], $result['allocatedItems'], $result['totalQuantity'], $result['totalCost'])) {
                    throw new \LogicException('Invalid result structure in processAdjustmentOutbound');
                }

                return $result;
            }

            /**
             * @param array<string, mixed> $result
             */
            public function setResult(string $method, array $result): void
            {
                $this->results[$method] = $result;
            }

            public function setException(string $method, \Exception $exception): void
            {
                $this->exceptions[$method] = $exception;
            }

            /** @return array<string, array<array<string, mixed>>> */
            public function getCalls(): array
            {
                return $this->calls;
            }
        };
    }

    public function testSalesOutboundSuccess(): void
    {
        $data = [
            'order_no' => 'ORDER001',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'quantity' => 10,
                    'allocation_strategy' => 'fifo',
                ],
            ],
            'operator' => 'user_123',
            'location_id' => 'WH001',
            'notes' => '测试销售出库',
        ];

        // 设置处理结果
        $processorResult = [
            'requestedItems' => [[
                'sku' => $data['items'][0]['sku'],
                'quantity' => 10,
                'allocation_strategy' => 'fifo',
            ]],
            'allocatedItems' => [[
                'batchId' => 1,
                'batchNo' => 'BATCH001',
                'quantity' => 10,
                'unitCost' => 10.5,
                'qualityLevel' => 'A',
                'locationId' => 'WH001',
            ]],
            'totalQuantity' => 10,
            'totalCost' => 105.0,
        ];

        // 设置处理器返回结果
        $this->processor->setResult('processSalesOutbound', $processorResult);

        $result = $this->service->salesOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('ORDER001', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::SALES, $result->getType());
        $this->assertEquals(10, $result->getTotalQuantity());
        $this->assertEquals('105', $result->getTotalCost());
        $this->assertEquals('user_123', $result->getOperator());

        // 验证validator和processor被调用
        $validatorCalls = $this->validator->getCalls();
        $this->assertArrayHasKey('validateSalesOutboundData', $validatorCalls);
        $this->assertCount(1, $validatorCalls['validateSalesOutboundData']);

        $processorCalls = $this->processor->getCalls();
        $this->assertArrayHasKey('processSalesOutbound', $processorCalls);
        $this->assertCount(1, $processorCalls['processSalesOutbound']);

        // 验证EntityManager被调用
        $this->assertCount(1, $this->entityManager->getPersistedObjects());
        $this->assertTrue($this->entityManager->isFlushed());
    }

    public function testSalesOutboundInsufficientStock(): void
    {
        $data = [
            'order_no' => 'ORDER002',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'quantity' => 1000,
                ],
            ],
            'operator' => 'user_123',
        ];

        // 设置处理器抛出异常
        $this->processor->setException('processSalesOutbound', InsufficientStockException::create('SPU001', 1000, 100));

        $this->expectException(InsufficientStockException::class);
        $this->service->salesOutbound($data);
    }

    public function testDamageOutboundSuccess(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 5,
                    'reason' => '破损',
                ],
            ],
            'operator' => 'user_123',
            'notes' => '货物破损出库',
        ];

        $processorResult = [
            'requestedItems' => [[
                'batch_id' => '1',
                'quantity' => 5,
                'reason' => '破损',
            ]],
            'allocatedItems' => [[
                'batchId' => 1,
                'batchNo' => 'BATCH001',
                'quantity' => 5,
                'unitCost' => 10.0,
                'reason' => '破损',
            ]],
            'totalQuantity' => 5,
            'totalCost' => 50.0,
        ];

        // 设置处理器返回结果
        $this->processor->setResult('processDamageOutbound', $processorResult);

        $result = $this->service->damageOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('DMG001', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::DAMAGE, $result->getType());
        $this->assertEquals(5, $result->getTotalQuantity());
        $this->assertEquals('50', $result->getTotalCost());
    }

    public function testTransferOutboundSuccess(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 20,
                ],
            ],
            'operator' => 'user_123',
            'location_id' => 'WH001',
        ];

        $batch = $this->createMock(StockBatch::class);
        $batch->method('getId')->willReturn(1);
        $batch->method('getBatchNo')->willReturn('BATCH001');
        $batch->method('getSpuId')->willReturn('SPU001');
        $batch->method('getAvailableQuantity')->willReturn(100);
        $batch->method('getUnitCost')->willReturn(10.0);
        $batch->method('getLocationId')->willReturn('WH001');

        $processorResult = [
            'requestedItems' => [[
                'batch_id' => '1',
                'quantity' => 20,
            ]],
            'allocatedItems' => [[
                'batchId' => 1,
                'batchNo' => 'BATCH001',
                'quantity' => 20,
                'unitCost' => 10.0,
                'locationId' => 'WH001',
            ]],
            'totalQuantity' => 20,
            'totalCost' => 200.0,
        ];

        // 设置 processor 返回值
        $this->processor->setResult('processTransferOutbound', $processorResult);

        $result = $this->service->transferOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('TRF001', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::TRANSFER, $result->getType());
        $this->assertEquals(20, $result->getTotalQuantity());
        $this->assertEquals('200', $result->getTotalCost());

        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertEquals('WH002', $metadata['to_location']);

        // 验证validator和processor被调用
        $validatorCalls = $this->validator->getCalls();
        $this->assertArrayHasKey('validateTransferOutboundData', $validatorCalls);
        $this->assertCount(1, $validatorCalls['validateTransferOutboundData']);
        $this->assertEquals($data, $validatorCalls['validateTransferOutboundData'][0]);

        $processorCalls = $this->processor->getCalls();
        $this->assertArrayHasKey('processTransferOutbound', $processorCalls);
        $this->assertCount(1, $processorCalls['processTransferOutbound']);
        $this->assertEquals($data, $processorCalls['processTransferOutbound'][0]);

        // 验证EntityManager被调用
        $this->assertCount(1, $this->entityManager->getPersistedObjects());
        $this->assertTrue($this->entityManager->isFlushed());
    }

    public function testPickOutboundSuccess(): void
    {
        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'items' => [
                [
                    'sku' => $this->createSku('SPU001'),
                    'quantity' => 3,
                    'purpose' => '办公使用',
                ],
            ],
            'operator' => 'user_123',
        ];

        $batch = $this->createMock(StockBatch::class);
        $batch->method('getId')->willReturn(1);
        $batch->method('getBatchNo')->willReturn('BATCH001');
        $batch->method('getSpuId')->willReturn('SPU001');
        $batch->method('getAvailableQuantity')->willReturn(100);
        $batch->method('getUnitCost')->willReturn(10.0);

        $processorResult = [
            'requestedItems' => [[
                'sku' => $data['items'][0]['sku'],
                'quantity' => 3,
                'purpose' => '办公使用',
            ]],
            'allocatedItems' => [[
                'batchId' => 1,
                'batchNo' => 'BATCH001',
                'quantity' => 3,
                'unitCost' => 10.0,
                'qualityLevel' => 'A',
                'locationId' => 'WH001',
            ]],
            'totalQuantity' => 3,
            'totalCost' => 30.0,
        ];

        // 设置 processor 返回值
        $this->processor->setResult('processPickOutbound', $processorResult);

        $result = $this->service->pickOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('PICK001', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::PICK, $result->getType());
        $this->assertEquals(3, $result->getTotalQuantity());
        $this->assertEquals('30', $result->getTotalCost());

        $metadata = $result->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertEquals('IT部门', $metadata['department']);

        // 验证validator和processor被调用
        $validatorCalls = $this->validator->getCalls();
        $this->assertArrayHasKey('validatePickOutboundData', $validatorCalls);
        $this->assertCount(1, $validatorCalls['validatePickOutboundData']);
        $this->assertEquals($data, $validatorCalls['validatePickOutboundData'][0]);

        $processorCalls = $this->processor->getCalls();
        $this->assertArrayHasKey('processPickOutbound', $processorCalls);
        $this->assertCount(1, $processorCalls['processPickOutbound']);
        $this->assertEquals($data, $processorCalls['processPickOutbound'][0]);

        // 验证EntityManager被调用
        $this->assertCount(1, $this->entityManager->getPersistedObjects());
        $this->assertTrue($this->entityManager->isFlushed());
    }

    public function testSalesOutboundInvalidData(): void
    {
        $data = [
            'order_no' => '',
            'items' => [],
            'operator' => 'user_123',
        ];

        // 设置 validator 抛出异常
        $this->validator->setException('validateSalesOutboundData', new \InvalidArgumentException('订单号不能为空'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('订单号不能为空');
        $this->service->salesOutbound($data);
    }

    public function testDamageOutboundBatchNotFound(): void
    {
        $data = [
            'damage_no' => 'DMG002',
            'items' => [
                [
                    'batch_id' => '999',
                    'quantity' => 5,
                    'reason' => '过期',
                ],
            ],
            'operator' => 'user_123',
        ];

        // 设置 processor 抛出异常
        $this->processor->setException('processDamageOutbound', new \RuntimeException('批次不存在: 999'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('批次不存在: 999');
        $this->service->damageOutbound($data);
    }

    public function testOutboundWithInsufficientBatchStock(): void
    {
        $data = [
            'damage_no' => 'DMG003',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 200,
                    'reason' => '损坏',
                ],
            ],
            'operator' => 'user_123',
        ];

        $batch = $this->createMock(StockBatch::class);
        $batch->method('getId')->willReturn(1);
        $batch->method('getBatchNo')->willReturn('BATCH001');
        $batch->method('getSpuId')->willReturn('SPU001');
        $batch->method('getAvailableQuantity')->willReturn(50);

        // 设置 processor 抛出异常
        $this->processor->setException('processDamageOutbound', InsufficientStockException::create('SPU001', 200, 50));

        $this->expectException(InsufficientStockException::class);
        $this->service->damageOutbound($data);
    }

    public function testAdjustmentOutboundSuccess(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 15,
                    'reason' => '盘点亏损',
                ],
            ],
            'operator' => 'adjustment-operator',
            'location_id' => 'WH001',
            'notes' => '盘点调整出库',
        ];

        $batch = $this->createMock(StockBatch::class);
        $batch->method('getId')->willReturn(1);
        $batch->method('getBatchNo')->willReturn('BATCH001');
        $batch->method('getSpuId')->willReturn('SPU001');
        $batch->method('getAvailableQuantity')->willReturn(100);
        $batch->method('getUnitCost')->willReturn(12.5);

        $processorResult = [
            'requestedItems' => [[
                'batch_id' => '1',
                'quantity' => 15,
                'reason' => '盘点亏损',
            ]],
            'allocatedItems' => [[
                'batchId' => 1,
                'batchNo' => 'BATCH001',
                'quantity' => 15,
                'unitCost' => 12.5,
                'reason' => '盘点亏损',
            ]],
            'totalQuantity' => 15,
            'totalCost' => 187.5,
        ];

        // 设置 processor 返回值
        $this->processor->setResult('processAdjustmentOutbound', $processorResult);

        $result = $this->service->adjustmentOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('ADJ-OUT-001', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::ADJUSTMENT, $result->getType());
        $this->assertEquals(15, $result->getTotalQuantity());
        $this->assertEquals('187.5', $result->getTotalCost());
        $this->assertEquals('adjustment-operator', $result->getOperator());
        $this->assertEquals('WH001', $result->getLocationId());
        $this->assertEquals('盘点调整出库', $result->getRemark());

        // 验证validator和processor被调用
        $validatorCalls = $this->validator->getCalls();
        $this->assertArrayHasKey('validateAdjustmentOutboundData', $validatorCalls);
        $this->assertCount(1, $validatorCalls['validateAdjustmentOutboundData']);
        $this->assertEquals($data, $validatorCalls['validateAdjustmentOutboundData'][0]);

        $processorCalls = $this->processor->getCalls();
        $this->assertArrayHasKey('processAdjustmentOutbound', $processorCalls);
        $this->assertCount(1, $processorCalls['processAdjustmentOutbound']);
        $this->assertEquals($data, $processorCalls['processAdjustmentOutbound'][0]);

        // 验证EntityManager被调用
        $this->assertCount(1, $this->entityManager->getPersistedObjects());
        $this->assertTrue($this->entityManager->isFlushed());
    }

    public function testAdjustmentOutboundMultipleItems(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-002',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 10,
                    'reason' => '盘点亏损',
                ],
                [
                    'batch_id' => '2',
                    'quantity' => 5,
                    'reason' => '损坏报废',
                ],
            ],
            'operator' => 'warehouse-manager',
            'notes' => '多批次调整出库',
        ];

        $processorResult = [
            'requestedItems' => [
                [
                    'batch_id' => '1',
                    'quantity' => 10,
                    'reason' => '盘点亏损',
                ],
                [
                    'batch_id' => '2',
                    'quantity' => 5,
                    'reason' => '损坏报废',
                ],
            ],
            'allocatedItems' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 10,
                    'unitCost' => 20.0,
                    'reason' => '盘点亏损',
                ],
                [
                    'batchId' => 2,
                    'batchNo' => 'BATCH002',
                    'quantity' => 5,
                    'unitCost' => 15.0,
                    'reason' => '损坏报废',
                ],
            ],
            'totalQuantity' => 15,
            'totalCost' => 275.0, // 10 * 20.0 + 5 * 15.0
        ];

        // 设置 processor 返回值
        $this->processor->setResult('processAdjustmentOutbound', $processorResult);

        $result = $this->service->adjustmentOutbound($data);

        $this->assertInstanceOf(StockOutbound::class, $result);
        $this->assertEquals('ADJ-OUT-002', $result->getReferenceNo());
        $this->assertEquals(StockOutboundType::ADJUSTMENT, $result->getType());
        $this->assertEquals(15, $result->getTotalQuantity());
        $this->assertEquals('275', $result->getTotalCost());
        $this->assertEquals('warehouse-manager', $result->getOperator());
        $this->assertEquals('多批次调整出库', $result->getRemark());

        // 验证请求项目包含原因字段
        $requestedItems = $result->getItems();
        $this->assertIsArray($requestedItems);
        $this->assertArrayHasKey('items', $requestedItems);
        $this->assertIsArray($requestedItems['items']);
        $this->assertArrayHasKey(0, $requestedItems['items']);
        $this->assertArrayHasKey(1, $requestedItems['items']);
        $this->assertIsArray($requestedItems['items'][0]);
        $this->assertIsArray($requestedItems['items'][1]);
        $this->assertEquals('盘点亏损', $requestedItems['items'][0]['reason']);
        $this->assertEquals('损坏报废', $requestedItems['items'][1]['reason']);

        // 验证validator和processor被调用
        $validatorCalls = $this->validator->getCalls();
        $this->assertArrayHasKey('validateAdjustmentOutboundData', $validatorCalls);
        $this->assertCount(1, $validatorCalls['validateAdjustmentOutboundData']);
        $this->assertEquals($data, $validatorCalls['validateAdjustmentOutboundData'][0]);

        $processorCalls = $this->processor->getCalls();
        $this->assertArrayHasKey('processAdjustmentOutbound', $processorCalls);
        $this->assertCount(1, $processorCalls['processAdjustmentOutbound']);
        $this->assertEquals($data, $processorCalls['processAdjustmentOutbound'][0]);

        // 验证EntityManager被调用
        $this->assertCount(1, $this->entityManager->getPersistedObjects());
        $this->assertTrue($this->entityManager->isFlushed());
    }

    public function testAdjustmentOutboundInvalidData(): void
    {
        $data = [
            'adjustment_no' => '',
            'items' => [],
            'operator' => 'user_123',
        ];

        // 设置 validator 抛出异常
        $this->validator->setException('validateAdjustmentOutboundData', new \InvalidArgumentException('调整单号不能为空'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('调整单号不能为空');
        $this->service->adjustmentOutbound($data);
    }

    public function testAdjustmentOutboundMissingReason(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-003',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 10,
                    // 缺少必需的reason字段
                ],
            ],
            'operator' => 'user_123',
        ];

        // 设置 validator 抛出异常
        $this->validator->setException('validateAdjustmentOutboundData', new \InvalidArgumentException('调整原因不能为空'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('调整原因不能为空');
        $this->service->adjustmentOutbound($data);
    }

    public function testAdjustmentOutboundBatchNotFound(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-004',
            'items' => [
                [
                    'batch_id' => '999',
                    'quantity' => 10,
                    'reason' => '盘点亏损',
                ],
            ],
            'operator' => 'user_123',
        ];

        // 设置 processor 抛出异常
        $this->processor->setException('processAdjustmentOutbound', new \RuntimeException('批次不存在: 999'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('批次不存在: 999');
        $this->service->adjustmentOutbound($data);
    }

    private function createSku(string $spuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($spuId);

        return $sku;
    }
}
