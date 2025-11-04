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
use Tourze\StockManageBundle\Entity\StockSnapshot;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\SnapshotService;

/**
 * @internal
 */
interface SnapshotMockEntityManager // @phpstan-ignore-line
{
    public function getPersistCallCount(): int;

    public function getFlushCallCount(): int;

    public function getRemoveCallCount(): int;

    /**
     * @param array<string, EntityRepository<object>> $mocks
     */
    public function setRepositoryMocks(array $mocks): void;
}

/**
 * @internal
 */
interface SnapshotMockBatchRepository
{
    /**
     * @param list<StockBatch> $batches
     */
    public function setFindBySkuResult(array $batches): void;

    /**
     * @param list<StockBatch> $batches
     */
    public function setFindByResult(array $batches): void;

    /**
     * @param list<StockBatch> $batches
     */
    public function setFindAllResult(array $batches): void;
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses -- Test helper interfaces
// phpstan: ignore symplify.multipleClassLikeInFile

/**
 * @internal
 */
#[CoversClass(SnapshotService::class)]
class SnapshotServiceTest extends TestCase
{
    private SnapshotService $service;

    private EntityManagerInterface&SnapshotMockEntityManager $entityManager;

    private StockBatchRepository&SnapshotMockBatchRepository $batchRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = new class implements EntityManagerInterface, SnapshotMockEntityManager {
            private int $persistCallCount = 0;

            private int $flushCallCount = 0;

            private int $removeCallCount = 0;

            /** @var array<string, EntityRepository<object>> */
            private array $repositoryMocks = [];

            public function persist(object $object): void
            {
                ++$this->persistCallCount;
            }

            public function flush(): void
            {
                ++$this->flushCallCount;
            }

            public function remove(object $object): void
            {
                ++$this->removeCallCount;
            }

            public function getPersistCallCount(): int
            {
                return $this->persistCallCount;
            }

            public function getFlushCallCount(): int
            {
                return $this->flushCallCount;
            }

            public function getRemoveCallCount(): int
            {
                return $this->removeCallCount;
            }

            /** @param array<string, EntityRepository<object>> $mocks */
            public function setRepositoryMocks(array $mocks): void
            {
                $this->repositoryMocks = $mocks;
            }

            /** @return EntityRepository<object> */
            public function getRepository(string $className): EntityRepository
            {
                $repo = $this->repositoryMocks[$className] ?? throw new \LogicException('Repository not found');
                if (!$repo instanceof EntityRepository) {
                    throw new \LogicException('Repository must be an instance of EntityRepository');
                }

                return $repo;
            }

            // 实现接口方法
            public function find(string $className, mixed $id, LockMode|int|null $lockMode = null, \DateTimeInterface|int|null $lockVersion = null): ?object
            {
                return null;
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

        $this->batchRepository = new class([]) extends StockBatchRepository implements SnapshotMockBatchRepository {
            /** @var list<StockBatch> */
            private array $findBySkuResult = [];

            /** @var list<StockBatch> */
            private array $findByResult = [];

            /** @var list<StockBatch> */
            private array $findAllResult = [];

            /** @param array<mixed> $args */
            public function __construct(array $args = []) // @phpstan-ignore constructor.missingParentCall, constructor.unusedParameter
            {
                // 跳过父类构造函数（测试Mock不需要完整初始化）
            }

            /** @param list<StockBatch> $batches */
            public function setFindBySkuResult(array $batches): void
            {
                $this->findBySkuResult = array_values($batches);
            }

            /** @param list<StockBatch> $batches */
            public function setFindByResult(array $batches): void
            {
                $this->findByResult = array_values($batches);
            }

            /** @param list<StockBatch> $batches */
            public function setFindAllResult(array $batches): void
            {
                $this->findAllResult = array_values($batches);
            }

            public function findBySku(\Tourze\ProductServiceContracts\SKU $sku): array
            {
                return $this->findBySkuResult;
            }

            public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
            {
                return $this->findByResult;
            }

            public function findAll(): array
            {
                return $this->findAllResult;
            }
        };

        $this->service = new SnapshotService(
            $this->entityManager,
            $this->batchRepository
        );
    }

    private function createSku(string $spuId, int $id = 0): Sku
    {
        $sku = new class($id) extends Sku {
            public function __construct(private int $skuId)
            {
                parent::__construct();
            }

            public function getId(): string
            {
                return (string) $this->skuId;
            }
        };
        $sku->setGtin($spuId);

        return $sku;
    }

    public function testCreateSnapshot(): void
    {
        $sku1 = $this->createSku('SPU001');

        $batch1 = new class($sku1, 100, 10.0, 'BATCH001', 'LOC001') extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
                private string $locationId,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }

            public function getLocationId(): string
            {
                return $this->locationId;
            }
        };

        $batch2 = new class($sku1, 200, 20.0, 'BATCH002', 'LOC001') extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
                private string $locationId,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }

            public function getLocationId(): string
            {
                return $this->locationId;
            }
        };

        $this->batchRepository->setFindBySkuResult([$batch1, $batch2]);

        $snapshot = $this->service->createSnapshot($this->createSku('SPU001'), 300, 'daily', ['trigger_method' => 'auto', 'notes' => 'Daily snapshot']);

        $this->assertInstanceOf(StockSnapshot::class, $snapshot);
        $this->assertEquals('daily', $snapshot->getType());
        $this->assertEquals('auto', $snapshot->getTriggerMethod());
        $this->assertEquals(300, $snapshot->getTotalQuantity());
        $this->assertEquals(5000.0, $snapshot->getTotalValue());
        $this->assertEquals(1, $snapshot->getProductCount());

        // 验证EntityManager方法被调用
        $this->assertEquals(1, $this->entityManager->getPersistCallCount());
        $this->assertEquals(1, $this->entityManager->getFlushCallCount());
    }

    public function testCreateSnapshotByLocation(): void
    {
        $locationId = 'LOC001';

        $sku = $this->createSku('SPU001');
        $batch = new class($sku, 100, 10.0, 'BATCH001', $locationId) extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
                private string $locationId,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }

            public function getLocationId(): string
            {
                return $this->locationId;
            }
        };

        $this->batchRepository->setFindByResult([$batch]);

        $snapshot = $this->service->createSnapshotByLocation($locationId, 'inventory_count', 'manual');

        $this->assertEquals($locationId, $snapshot->getLocationId());
        $this->assertEquals(100, $snapshot->getTotalQuantity());
        $this->assertEquals(1000.0, $snapshot->getTotalValue());

        // 验证EntityManager方法被调用
        $this->assertEquals(1, $this->entityManager->getPersistCallCount());
        $this->assertEquals(1, $this->entityManager->getFlushCallCount());
    }

    public function testCompareSnapshots(): void
    {
        $date1 = new \DateTimeImmutable('2024-01-01');
        $date2 = new \DateTimeImmutable('2024-01-02');

        $snapshot1 = new class(['byProduct' => ['SPU001' => ['quantity' => 100, 'value' => 1000.0, 'batches' => ['BATCH001']], 'SPU002' => ['quantity' => 200, 'value' => 4000.0, 'batches' => ['BATCH002']]]], 300, 5000.0, $date1) extends StockSnapshot {
            /**
             * @param array<string, mixed> $summary
             */
            public function __construct(private array $summary, private int $totalQuantity, private float $totalValue, private \DateTimeImmutable $createTime) // @phpstan-ignore property.onlyWritten
            {
                parent::__construct();
                $this->createTime = $createTime;
            }

            public function getSummary(): array
            {
                return $this->summary;
            }

            public function getTotalQuantity(): int
            {
                return $this->totalQuantity;
            }

            public function getTotalValue(): float
            {
                return $this->totalValue;
            }
        };

        $snapshot2 = new class(['byProduct' => ['SPU001' => ['quantity' => 150, 'value' => 1500.0, 'batches' => ['BATCH001', 'BATCH003']], 'SPU002' => ['quantity' => 180, 'value' => 3600.0, 'batches' => ['BATCH002']], 'SPU003' => ['quantity' => 50, 'value' => 500.0, 'batches' => ['BATCH004']]]], 380, 5600.0, $date2) extends StockSnapshot {
            /**
             * @param array<string, mixed> $summary
             */
            public function __construct(private array $summary, private int $totalQuantity, private float $totalValue, private \DateTimeImmutable $createTime) // @phpstan-ignore property.onlyWritten
            {
                parent::__construct();
                $this->createTime = $createTime;
            }

            public function getSummary(): array
            {
                return $this->summary;
            }

            public function getTotalQuantity(): int
            {
                return $this->totalQuantity;
            }

            public function getTotalValue(): float
            {
                return $this->totalValue;
            }
        };

        $comparison = $this->service->compareSnapshots($snapshot1, $snapshot2);

        $this->assertEquals(80, $comparison['quantityChange']);
        $this->assertEquals(600.0, $comparison['valueChange']);
        $this->assertEquals(26.67, $comparison['quantityChangePercentage']);
        $this->assertEquals(12.0, $comparison['valueChangePercentage']);

        $this->assertArrayHasKey('SPU001', $comparison['productChanges']);
        $this->assertEquals(50, $comparison['productChanges']['SPU001']['quantityChange']);

        $this->assertArrayHasKey('SPU003', $comparison['newProducts']);
        $this->assertEmpty($comparison['removedProducts']);
    }

    public function testGetLatestSnapshot(): void
    {
        $snapshot = new class extends StockSnapshot {
            // 空的匿名类实现
        };

        // 创建一个真实的EntityRepository mock
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')
            ->willReturn($snapshot)
        ;
        $this->entityManager->setRepositoryMocks([StockSnapshot::class => $repository]);

        $result = $this->service->getLatestSnapshot();

        $this->assertSame($snapshot, $result);
    }

    public function testGetSnapshotsByDateRange(): void
    {
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-12-31');

        $snapshots = [
            new class extends StockSnapshot {},
            new class extends StockSnapshot {},
        ];

        // 创建 Query mock
        $query = $this->createMock(Query::class);
        $query->method('getResult')
            ->willReturn($snapshots)
        ;

        // 创建 QueryBuilder mock
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // 创建 EntityRepository mock
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')
            ->willReturn($qb)
        ;
        $this->entityManager->setRepositoryMocks([StockSnapshot::class => $repository]);

        $result = $this->service->getSnapshotsByDateRange($startDate, $endDate);

        $this->assertCount(2, $result);
    }

    public function testDeleteOldSnapshots(): void
    {
        $retentionDays = 30;
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$retentionDays} days");

        $oldSnapshots = [
            new class extends StockSnapshot {},
            new class extends StockSnapshot {},
        ];

        // 创建 Query mock
        $query = $this->createMock(Query::class);
        $query->method('getResult')
            ->willReturn($oldSnapshots)
        ;

        // 创建 QueryBuilder mock
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        // 创建 EntityRepository mock
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')
            ->willReturn($qb)
        ;
        $this->entityManager->setRepositoryMocks([StockSnapshot::class => $repository]);

        $deletedCount = $this->service->deleteOldSnapshots($retentionDays);

        $this->assertEquals(2, $deletedCount);

        // 验证EntityManager方法被调用
        $this->assertEquals(2, $this->entityManager->getRemoveCallCount());
        $this->assertEquals(1, $this->entityManager->getFlushCallCount());
    }

    public function testGenerateSnapshotReport(): void
    {
        $createTime = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2024-01-31');

        $snapshot = new class('SNAP001', 'daily', $createTime, 1000, 10000.0, 10, 20, ['byProduct' => ['SPU001' => ['quantity' => 100, 'value' => 1000.0, 'batches' => ['BATCH001']], 'SPU002' => ['quantity' => 200, 'value' => 2000.0, 'batches' => ['BATCH002']]]], null, null, null, $validUntil, 'auto') extends StockSnapshot {
            /**
             * @param array<string, mixed> $summary
             */
            public function __construct(
                private string $snapshotNo,
                private string $type,
                private \DateTimeImmutable $createTime, // @phpstan-ignore property.onlyWritten
                private int $totalQuantity,
                private float $totalValue,
                private int $productCount,
                private int $batchCount,
                private array $summary,
                private ?string $operator,
                private ?string $locationId,
                private ?string $notes,
                private \DateTimeImmutable $validUntil, // @phpstan-ignore property.onlyWritten
                private string $triggerMethod,
            ) {
                parent::__construct();
                $this->createTime = $createTime;
                $this->validUntil = $validUntil;
            }

            public function getSnapshotNo(): string
            {
                return $this->snapshotNo;
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function getTotalQuantity(): int
            {
                return $this->totalQuantity;
            }

            public function getTotalValue(): float
            {
                return $this->totalValue;
            }

            public function getProductCount(): int
            {
                return $this->productCount;
            }

            public function getBatchCount(): int
            {
                return $this->batchCount;
            }

            public function getSummary(): array
            {
                return $this->summary;
            }

            public function getOperator(): ?string
            {
                return $this->operator;
            }

            public function getLocationId(): ?string
            {
                return $this->locationId;
            }

            public function getNotes(): ?string
            {
                return $this->notes;
            }

            public function getTriggerMethod(): string
            {
                return $this->triggerMethod;
            }
        };

        $report = $this->service->generateSnapshotReport($snapshot);

        $this->assertArrayHasKey('snapshotNo', $report);
        $this->assertArrayHasKey('type', $report);
        $this->assertArrayHasKey('date', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('topProducts', $report);

        $this->assertEquals('SNAP001', $report['snapshotNo']);
        $this->assertEquals(1000, $report['summary']['totalQuantity']);
        $this->assertCount(2, $report['topProducts']);
    }

    public function testCreateFullSnapshot(): void
    {
        $sku = $this->createSku('SPU001');
        $batch = new class($sku, 100, 10.0, 'BATCH001', 'LOC001') extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
                private string $locationId,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }

            public function getLocationId(): string
            {
                return $this->locationId;
            }
        };

        $this->batchRepository->setFindAllResult([$batch]);

        $snapshot = $this->service->createFullSnapshot('daily', 'manual', 'Full inventory snapshot');

        $this->assertInstanceOf(StockSnapshot::class, $snapshot);
        $this->assertEquals('daily', $snapshot->getType());
        $this->assertEquals('manual', $snapshot->getTriggerMethod());
        $this->assertEquals('Full inventory snapshot', $snapshot->getNotes());

        // 验证EntityManager方法被调用
        $this->assertEquals(1, $this->entityManager->getPersistCallCount());
        $this->assertEquals(1, $this->entityManager->getFlushCallCount());
    }

    public function testCreateInventoryCountSnapshot(): void
    {
        // 使用带前缀的ID避免PHP将纯数字字符串键转换为整数
        $sku1 = new class(101) extends Sku {
            public function __construct(private int $skuId)
            {
                parent::__construct();
            }

            public function getId(): string
            {
                return 'sku_' . $this->skuId;  // 返回 "sku_101"
            }
        };
        $sku1->setGtin('SPU001');

        $sku2 = new class(102) extends Sku {
            public function __construct(private int $skuId)
            {
                parent::__construct();
            }

            public function getId(): string
            {
                return 'sku_' . $this->skuId;  // 返回 "sku_102"
            }
        };
        $sku2->setGtin('SPU002');

        // 使用SKU ID作为键，确保是字符串类型
        $actualDataCasted = [
            $sku1->getId() => 90,   // 'sku_101' => 90
            $sku2->getId() => 200,  // 'sku_102' => 200
        ];

        $batch1 = new class($sku1, 100, 10.0, 'BATCH001') extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }
        };

        $batch2 = new class($sku2, 180, 15.0, 'BATCH002') extends StockBatch {
            public function __construct(
                private \Tourze\ProductServiceContracts\SKU $sku,
                private int $availableQuantity,
                private float $unitCost,
                private string $batchNo,
            ) {
                parent::__construct();
            }

            public function getSku(): \Tourze\ProductServiceContracts\SKU
            {
                return $this->sku;
            }

            public function getAvailableQuantity(): int
            {
                return $this->availableQuantity;
            }

            public function getUnitCost(): float
            {
                return $this->unitCost;
            }

            public function getBatchNo(): string
            {
                return $this->batchNo;
            }
        };

        $this->batchRepository->setFindAllResult([$batch1, $batch2]);

        $result = $this->service->createInventoryCountSnapshot($actualDataCasted, 'user123', 'Inventory count');

        $this->assertArrayHasKey('snapshot', $result);
        $this->assertArrayHasKey('differences', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertInstanceOf(StockSnapshot::class, $result['snapshot']);
        $this->assertIsArray($result['differences']);
        $this->assertIsArray($result['summary']);

        // 验证EntityManager方法被调用
        $this->assertGreaterThanOrEqual(1, $this->entityManager->getPersistCallCount());
        $this->assertGreaterThanOrEqual(1, $this->entityManager->getFlushCallCount());
    }
}
