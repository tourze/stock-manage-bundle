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
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\TransferService;

/**
 * @internal
 */
#[CoversClass(TransferService::class)]
class TransferServiceTest extends TestCase
{
    private TransferService $service;

    private EntityManagerInterface $entityManager;

    private StockBatchRepository&MockStockBatchRepositoryForTransfer $batchRepository;

    private MockOutboundService $outboundService;

    private MockInboundService $inboundService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = new class implements EntityManagerInterface {
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

            // 实现接口方法
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

        $this->batchRepository = new class($this->createMock(ManagerRegistry::class)) extends StockBatchRepository implements MockStockBatchRepositoryForTransfer {
            /** @var array<int, StockBatch> */
            private array $batches = [];

            public function __construct(ManagerRegistry $registry)
            {
                parent::__construct($registry);
            }

            public function find(mixed $id, LockMode|int|null $lockMode = null, \DateTimeInterface|int|null $lockVersion = null): ?object
            {
                if (!is_int($id)) {
                    return null;
                }

                return $this->batches[$id] ?? null;
            }

            public function setBatch(int $id, StockBatch $batch): void
            {
                $this->batches[$id] = $batch;
            }
        };

        $this->outboundService = new class implements MockOutboundService {
            /** @var array<string, array<array<string, mixed>>> */
            private array $calls = [];

            public function transferOutbound(array $data): StockOutbound
            {
                $this->calls['transferOutbound'][] = $data;

                return new StockOutbound();
            }

            /** @return array<string, array<array<string, mixed>>> */
            public function getCalls(): array
            {
                return $this->calls;
            }

            // 实现其他接口方法
            public function salesOutbound(array $data): StockOutbound
            {
                return new StockOutbound();
            }

            public function damageOutbound(array $data): StockOutbound
            {
                return new StockOutbound();
            }

            public function adjustmentOutbound(array $data): StockOutbound
            {
                return new StockOutbound();
            }

            public function pickOutbound(array $data): StockOutbound
            {
                return new StockOutbound();
            }
        };

        $this->inboundService = new class implements MockInboundService {
            /** @var array<string, array<array<string, mixed>>> */
            private array $calls = [];

            public function productionInbound(array $data): StockInbound
            {
                $this->calls['productionInbound'][] = $data;

                return new StockInbound();
            }

            /** @return array<string, array<array<string, mixed>>> */
            public function getCalls(): array
            {
                return $this->calls;
            }

            // 实现其他接口方法
            public function purchaseInbound(array $data): StockInbound
            {
                return new StockInbound();
            }

            public function transferInbound(array $data): StockInbound
            {
                return new StockInbound();
            }

            public function adjustmentInbound(array $data): StockInbound
            {
                return new StockInbound();
            }

            public function returnInbound(array $data): StockInbound
            {
                return new StockInbound();
            }
        };

        $this->service = new TransferService(
            $this->entityManager,
            $this->batchRepository,
            $this->outboundService,
            $this->inboundService
        );
    }

    private function createSku(string $skuId): Sku
    {
        $sku = new Sku();
        $sku->setGtin($skuId);

        return $sku;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockTransfer(array $data = []): StockTransfer
    {
        $transfer = new StockTransfer();

        $this->setTransferBasicFields($transfer, $data);
        $this->setTransferStatus($transfer, $data);
        $this->setTransferItems($transfer, $data);
        $this->setTransferMetadata($transfer, $data);

        return $transfer;
    }

    /**
     * 设置调拨单基本字段.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferBasicFields(StockTransfer $transfer, array $data): void
    {
        $this->setTransferNo($transfer, $data);
        $this->setTransferLocations($transfer, $data);
        $this->setTransferInitiator($transfer, $data);
    }

    /**
     * 设置调拨单号.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferNo(StockTransfer $transfer, array $data): void
    {
        if (isset($data['transferNo']) && is_string($data['transferNo'])) {
            $transfer->setTransferNo($data['transferNo']);
        }
    }

    /**
     * 设置调拨单位置.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferLocations(StockTransfer $transfer, array $data): void
    {
        if (isset($data['fromLocation']) && is_string($data['fromLocation'])) {
            $transfer->setFromLocation($data['fromLocation']);
        }
        if (isset($data['toLocation']) && is_string($data['toLocation'])) {
            $transfer->setToLocation($data['toLocation']);
        }
    }

    /**
     * 设置调拨单发起人.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferInitiator(StockTransfer $transfer, array $data): void
    {
        if (array_key_exists('initiator', $data)) {
            $value = $data['initiator'];
            if (is_string($value) || null === $value) {
                $transfer->setInitiator($value);
            }
        }
    }

    /**
     * 设置调拨单状态.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferStatus(StockTransfer $transfer, array $data): void
    {
        if (isset($data['status']) && $data['status'] instanceof StockTransferStatus) {
            $transfer->setStatus($data['status']);
        }
    }

    /**
     * 设置调拨单项目.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferItems(StockTransfer $transfer, array $data): void
    {
        if (isset($data['items']) && is_array($data['items'])) {
            /** @var list<array<string, mixed>> $items */
            $items = array_values($data['items']);
            $transfer->setItems($items);
        }
    }

    /**
     * 设置调拨单元数据.
     *
     * @param array<string, mixed> $data
     */
    private function setTransferMetadata(StockTransfer $transfer, array $data): void
    {
        if (array_key_exists('metadata', $data)) {
            $value = $data['metadata'];
            if (is_array($value) || null === $value) {
                /** @var array<mixed>|null $metadata */
                $metadata = $value;
                $transfer->setMetadata($metadata);
            }
        }
    }

    public function testCreateTransferSuccess(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'from_location' => 'WH001',
            'to_location' => 'WH002',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 50,
                ],
            ],
            'operator' => 'user_123',
            'notes' => '仓库间调拨',
        ];

        $sku = $this->createSku('SPU001');

        $batch = new StockBatch();
        $batch->setBatchNo('BATCH001');
        $batch->setSku($sku);
        $batch->setAvailableQuantity(100);
        $batch->setLocationId('WH001');

        // 设置一个ID，通过反射设置私有属性
        $reflection = new \ReflectionClass($batch);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($batch, 1);

        $this->batchRepository->setBatch(1, $batch);

        // 使用自定义的EntityManager实现，不需要预期

        $result = $this->service->createTransfer($data);

        $this->assertInstanceOf(StockTransfer::class, $result);
        $this->assertEquals('TRF001', $result->getTransferNo());
        $this->assertEquals('WH001', $result->getFromLocation());
        $this->assertEquals('WH002', $result->getToLocation());
        $this->assertEquals(StockTransferStatus::PENDING, $result->getStatus());
        $this->assertEquals(50, $result->getTotalQuantity());
        $this->assertEquals('user_123', $result->getInitiator());
    }

    public function testCreateTransferInsufficientStock(): void
    {
        $data = [
            'transfer_no' => 'TRF002',
            'from_location' => 'WH001',
            'to_location' => 'WH002',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 200,
                ],
            ],
            'operator' => 'user_123',
        ];

        $sku = $this->createSku('SPU001');
        $batch = new StockBatch();
        $batch->setSku($sku);
        $batch->setAvailableQuantity(50);
        $batch->setLocationId('WH001');

        // 设置ID
        $reflection = new \ReflectionClass($batch);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($batch, 1);

        $this->batchRepository->setBatch(1, $batch);

        $this->expectException(InsufficientStockException::class);
        $this->service->createTransfer($data);
    }

    public function testExecuteTransferSuccess(): void
    {
        $transfer = $this->createStockTransfer([
            'transferNo' => 'TRF001',
            'fromLocation' => 'WH001',
            'toLocation' => 'WH002',
            'status' => StockTransferStatus::PENDING,
            'initiator' => 'user_123',
            'items' => [
                [
                    'batch_id' => 1,
                    'batch_no' => 'BATCH001',
                    'spu_id' => 'SPU001',
                    'quantity' => 50,
                ],
            ],
        ]);

        // 使用自定义服务实现，验证调用数据

        $result = $this->service->executeTransfer($transfer);

        $this->assertSame($transfer, $result);
        $this->assertEquals(StockTransferStatus::IN_TRANSIT, $transfer->getStatus());
        $this->assertNotNull($transfer->getShippedTime());

        // 验证outboundService被调用 - 使用断言检查服务被调用
        $outboundCalls = $this->outboundService->getCalls();
        $this->assertArrayHasKey('transferOutbound', $outboundCalls);
        $this->assertIsArray($outboundCalls['transferOutbound']);
        $this->assertCount(1, $outboundCalls['transferOutbound']);

        $callData = $outboundCalls['transferOutbound'][0];
        $this->assertIsArray($callData);
        /* @var array<string, mixed> $callData */
        $this->assertEquals('TRF001', $callData['transfer_no']);
        $this->assertEquals('WH002', $callData['to_location']);
        $this->assertEquals('WH001', $callData['location_id']);
        $this->assertEquals('user_123', $callData['operator']);
        $this->assertIsArray($callData['items']);
        $this->assertCount(1, $callData['items']);
        /** @var array<array<string, mixed>> $items */
        $items = $callData['items'];
        $this->assertEquals('1', $items[0]['batch_id']);
        $this->assertEquals(50, $items[0]['quantity']);
    }

    public function testReceiveTransferSuccess(): void
    {
        $sku = $this->createSku('SPU001');

        $transfer = $this->createStockTransfer([
            'transferNo' => 'TRF001',
            'fromLocation' => 'WH001',
            'toLocation' => 'WH002',
            'status' => StockTransferStatus::IN_TRANSIT,
            'items' => [
                [
                    'batch_id' => 1,
                    'batch_no' => 'BATCH001',
                    'sku' => $sku,
                    'quantity' => 50,
                    'unit_cost' => 10.0,
                    'quality_level' => 'A',
                ],
            ],
        ]);

        // 使用自定义inboundService实现

        $actualReceived = [
            'received_items' => [
                ['batch_id' => 1, 'received_quantity' => 50],
            ],
            'receiver' => 'user_456',
        ];

        $result = $this->service->receiveTransfer($transfer, $actualReceived);

        $this->assertSame($transfer, $result);
        $this->assertEquals(StockTransferStatus::RECEIVED, $transfer->getStatus());
        $this->assertNotNull($transfer->getReceivedTime());
        $this->assertEquals('user_456', $transfer->getReceiver());
        $metadata = $transfer->getMetadata();
        $this->assertNotNull($metadata);
        $this->assertArrayHasKey('received_items', $metadata);

        // 验证inboundService被调用
        $inboundCalls = $this->inboundService->getCalls();
        $this->assertArrayHasKey('productionInbound', $inboundCalls);
        $this->assertIsArray($inboundCalls['productionInbound']);
        $this->assertCount(1, $inboundCalls['productionInbound']);

        $callData = $inboundCalls['productionInbound'][0];
        $this->assertIsArray($callData);
        /* @var array<string, mixed> $callData */
        $this->assertEquals('TRF001', $callData['production_order_no']);
        $this->assertEquals('WH002', $callData['location_id']);
        $this->assertIsArray($callData['items']);
        $this->assertCount(1, $callData['items']);
        /** @var array<array<string, mixed>> $items */
        $items = $callData['items'];
        $this->assertInstanceOf(Sku::class, $items[0]['sku']);
        /** @var Sku $sku */
        $sku = $items[0]['sku'];
        $this->assertEquals('SPU001', $sku->getGtin());
        $this->assertEquals('BATCH001-WH002', $items[0]['batch_no']);
        $this->assertEquals(50, $items[0]['quantity']);
    }

    public function testCancelTransferSuccess(): void
    {
        $sku = $this->createSku('SPU001');

        $transfer = $this->createStockTransfer([
            'transferNo' => 'TRF001',
            'fromLocation' => 'WH001',
            'status' => StockTransferStatus::IN_TRANSIT,
            'initiator' => 'user_123',
            'metadata' => [],
            'items' => [
                [
                    'sku' => $sku,
                    'batch_no' => 'BATCH001',
                    'quantity' => 50,
                    'unit_cost' => 10.0,
                    'quality_level' => 'A',
                ],
            ],
        ]);

        // 使用自定义inboundService实现

        $result = $this->service->cancelTransfer($transfer, '计划变更');

        $this->assertSame($transfer, $result);
        $this->assertEquals(StockTransferStatus::CANCELLED, $transfer->getStatus());

        $metadata = $transfer->getMetadata();
        $this->assertNotNull($metadata);
        $this->assertArrayHasKey('cancelled_at', $metadata);
        $this->assertArrayHasKey('cancel_reason', $metadata);
        $this->assertEquals('计划变更', $metadata['cancel_reason']);

        // 验证inboundService被调用
        $inboundCalls = $this->inboundService->getCalls();
        $this->assertArrayHasKey('productionInbound', $inboundCalls);
        $this->assertIsArray($inboundCalls['productionInbound']);
        $this->assertCount(1, $inboundCalls['productionInbound']);

        $callData = $inboundCalls['productionInbound'][0];
        $this->assertIsArray($callData);
        $this->assertEquals('TRF001-CANCEL', $callData['production_order_no']);
        $this->assertEquals('WH001', $callData['location_id']);
        $this->assertIsArray($callData['items']);
        $this->assertCount(1, $callData['items']);
    }

    public function testCancelTransferInvalidStatus(): void
    {
        $transfer = $this->createStockTransfer([
            'transferNo' => 'TRF001',
            'status' => StockTransferStatus::RECEIVED,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('调拨单TRF001状态为received，不能取消');

        $this->service->cancelTransfer($transfer, '测试取消');
    }

    public function testCreateTransferInvalidData(): void
    {
        $data = [
            'transfer_no' => '',
            'from_location' => 'WH001',
            'to_location' => 'WH002',
            'items' => [],
            'operator' => 'user_123',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('调拨单号不能为空');

        $this->service->createTransfer($data);
    }

    public function testCreateTransferSameLocation(): void
    {
        $data = [
            'transfer_no' => 'TRF003',
            'from_location' => 'WH001',
            'to_location' => 'WH001',
            'items' => [
                ['batch_id' => '1', 'quantity' => 10],
            ],
            'operator' => 'user_123',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('源位置和目标位置不能相同');

        $this->service->createTransfer($data);
    }

    public function testReceiveTransferInvalidStatus(): void
    {
        $transfer = $this->createStockTransfer([
            'transferNo' => 'TRF001',
            'status' => StockTransferStatus::PENDING,
        ]);

        $actualReceived = [
            'received_items' => [],
            'receiver' => 'user_456',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('调拨单TRF001状态为pending，不能接收');

        $this->service->receiveTransfer($transfer, $actualReceived);
    }
}
