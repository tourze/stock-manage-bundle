# Stock Manage Bundle - 技术设计

## 1. 技术概览

### 1.1 架构模式
- **扁平化 Service 层架构**：业务逻辑全部在 Service 层实现，不分层
- **贫血模型实体**：Entity 只包含数据和 getter/setter，不含业务逻辑
- **依赖注入**：使用 Symfony 的依赖注入容器管理服务
- **事件驱动**：通过事件系统解耦，支持扩展

### 1.2 核心设计原则
- **KISS 原则**：保持简单直接，避免过度抽象
- **YAGNI 原则**：只实现当前需要的功能
- **单一职责**：每个 Service 有明确的单一职责
- **高内聚低耦合**：通过接口和事件降低耦合

### 1.3 技术决策理由
- 采用扁平化架构：避免 DDD 的过度复杂性，提高开发效率
- 使用贫血模型：业务逻辑集中管理，便于维护和测试
- 事件系统：提供扩展点，不破坏核心功能

## 2. 公共 API 设计

### 2.1 核心服务接口

#### StockServiceInterface - 库存核心服务
```php
namespace StockManageBundle\Service;

interface StockServiceInterface
{
    /**
     * 创建库存批次
     * 
     * @param array{
     *     spu_id: string,
     *     batch_no: string,
     *     quantity: int,
     *     unit_cost: float,
     *     quality_level: string,
     *     production_date?: \DateTimeInterface,
     *     expiry_date?: \DateTimeInterface,
     *     location_id?: string,
     *     attributes?: array
     * } $data
     * @throws InvalidArgumentException
     * @throws DuplicateBatchException
     */
    public function createBatch(array $data): StockBatch;
    
    /**
     * 获取可用库存
     * 
     * @param array{
     *     location_id?: string,
     *     quality_level?: string,
     *     exclude_expired?: bool,
     *     include_reserved?: bool
     * } $criteria
     */
    public function getAvailableStock(string $spuId, array $criteria = []): StockSummary;
    
    /**
     * 库存分配
     * 
     * @param array{
     *     spu_id: string,
     *     quantity: int,
     *     strategy: string,
     *     location_id?: string,
     *     quality_level?: string
     * } $request
     * @throws InsufficientStockException
     */
    public function allocate(array $request): StockAllocationResult;
    
    /**
     * 批次合并
     * 
     * @param string[] $batchIds
     * @throws BatchNotFoundException
     * @throws IncompatibleBatchException
     */
    public function mergeBatches(array $batchIds, string $targetBatchNo): StockBatch;
    
    /**
     * 批次拆分
     * 
     * @param array<int, int> $quantities 拆分数量数组
     * @throws BatchNotFoundException
     * @throws InsufficientQuantityException
     */
    public function splitBatch(string $batchId, array $quantities): array;
}
```

#### ReservationServiceInterface - 预留服务
```php
namespace StockManageBundle\Service;

interface ReservationServiceInterface
{
    /**
     * 创建库存预留
     * 
     * @param array{
     *     spu_id: string,
     *     quantity: int,
     *     type: string,
     *     business_id: string,
     *     expires_at?: \DateTimeInterface,
     *     batch_ids?: string[]
     * } $data
     * @throws InsufficientStockException
     */
    public function reserve(array $data): StockReservation;
    
    /**
     * 确认预留
     * 
     * @throws ReservationNotFoundException
     * @throws ReservationExpiredException
     */
    public function confirm(string $reservationId): void;
    
    /**
     * 释放预留
     * 
     * @throws ReservationNotFoundException
     */
    public function release(string $reservationId, string $reason = ''): void;
    
    /**
     * 延长预留期限
     * 
     * @throws ReservationNotFoundException
     */
    public function extend(string $reservationId, \DateTimeInterface $newExpiryDate): void;
}
```

#### LockServiceInterface - 锁定服务
```php
namespace StockManageBundle\Service;

interface LockServiceInterface
{
    /**
     * 业务锁定
     * 
     * @param array{
     *     batch_ids: string[],
     *     quantities: array<string, int>,
     *     type: string,
     *     business_id: string,
     *     reason: string,
     *     expires_at?: \DateTimeInterface
     * } $data
     * @throws BatchNotFoundException
     * @throws InsufficientStockException
     */
    public function lockForBusiness(array $data): BusinessStockLock;
    
    /**
     * 操作锁定
     * 
     * @param array{
     *     batch_ids: string[],
     *     operation_type: string,
     *     operator: string,
     *     reason: string
     * } $data
     */
    public function lockForOperation(array $data): OperationalStockLock;
    
    /**
     * 释放锁定
     * 
     * @throws LockNotFoundException
     */
    public function releaseLock(string $lockId, string $lockType, string $reason = ''): void;
}
```

### 2.2 库存操作接口

#### InboundServiceInterface - 入库服务
```php
namespace StockManageBundle\Service;

interface InboundServiceInterface
{
    /**
     * 采购入库
     * 
     * @param array{
     *     purchase_order_no: string,
     *     items: array<array{
     *         spu_id: string,
     *         batch_no: string,
     *         quantity: int,
     *         unit_cost: float,
     *         quality_level: string
     *     }>,
     *     operator: string
     * } $data
     */
    public function purchaseInbound(array $data): StockInbound;
    
    /**
     * 退货入库
     */
    public function returnInbound(array $data): StockInbound;
    
    /**
     * 调拨入库
     */
    public function transferInbound(array $data): StockInbound;
    
    /**
     * 生产入库
     */
    public function productionInbound(array $data): StockInbound;
}
```

#### OutboundServiceInterface - 出库服务
```php
namespace StockManageBundle\Service;

interface OutboundServiceInterface
{
    /**
     * 销售出库
     * 
     * @param array{
     *     order_no: string,
     *     items: array<array{
     *         spu_id: string,
     *         quantity: int,
     *         allocation_strategy?: string
     *     }>,
     *     operator: string
     * } $data
     */
    public function salesOutbound(array $data): StockOutbound;
    
    /**
     * 损耗出库
     */
    public function damageOutbound(array $data): StockOutbound;
    
    /**
     * 调拨出库
     */
    public function transferOutbound(array $data): StockOutbound;
    
    /**
     * 领用出库
     */
    public function pickOutbound(array $data): StockOutbound;
}
```

### 2.3 使用示例代码

```php
// 创建库存批次
$batch = $stockService->createBatch([
    'spu_id' => 'SPU001',
    'batch_no' => 'BATCH20241208001',
    'quantity' => 1000,
    'unit_cost' => 10.50,
    'quality_level' => 'A',
    'production_date' => new \DateTime('2024-12-01'),
    'expiry_date' => new \DateTime('2025-12-01'),
]);

// 库存预留
$reservation = $reservationService->reserve([
    'spu_id' => 'SPU001',
    'quantity' => 100,
    'type' => 'order',
    'business_id' => 'ORDER001',
    'expires_at' => new \DateTime('+24 hours'),
]);

// 库存分配（FIFO策略）
$result = $stockService->allocate([
    'spu_id' => 'SPU001',
    'quantity' => 200,
    'strategy' => 'fifo',
    'quality_level' => 'A',
]);

// 销售出库
$outbound = $outboundService->salesOutbound([
    'order_no' => 'ORDER001',
    'items' => [
        ['spu_id' => 'SPU001', 'quantity' => 100],
        ['spu_id' => 'SPU002', 'quantity' => 50],
    ],
    'operator' => 'user_123',
]);
```

### 2.4 错误处理策略

- 使用具体的异常类型表示不同错误
- 异常包含详细的错误信息和上下文
- 支持异常链追踪根本原因
- 提供错误恢复建议

## 3. 内部架构

### 3.1 核心组件划分

```
packages/stock-manage-bundle/
├── src/
│   ├── Entity/                 # 贫血模型实体
│   │   ├── StockBatch.php
│   │   ├── StockSerialNumber.php
│   │   ├── StockInbound.php
│   │   ├── StockOutbound.php
│   │   ├── StockTransfer.php
│   │   ├── StockAdjustment.php
│   │   ├── BusinessStockLock.php
│   │   ├── OperationalStockLock.php
│   │   ├── StockReservation.php
│   │   ├── StockSnapshot.php
│   │   ├── BundleStock.php
│   │   ├── VirtualStock.php
│   │   ├── StockAlert.php
│   │   └── StockInTransit.php
│   │
│   ├── Repository/              # 数据访问层
│   │   ├── StockBatchRepository.php
│   │   ├── StockReservationRepository.php
│   │   ├── StockLockRepository.php
│   │   ├── StockInboundRepository.php
│   │   ├── StockOutboundRepository.php
│   │   └── StockSnapshotRepository.php
│   │
│   ├── Service/                 # 扁平化业务逻辑层
│   │   ├── StockService.php
│   │   ├── ReservationService.php
│   │   ├── LockService.php
│   │   ├── InboundService.php
│   │   ├── OutboundService.php
│   │   ├── TransferService.php
│   │   ├── AdjustmentService.php
│   │   ├── AllocationService.php
│   │   ├── CostCalculationService.php
│   │   ├── SnapshotService.php
│   │   ├── BundleStockService.php
│   │   ├── VirtualStockService.php
│   │   ├── AlertService.php
│   │   └── InTransitService.php
│   │
│   ├── Strategy/                # 分配策略
│   │   ├── AllocationStrategyInterface.php
│   │   ├── FifoStrategy.php
│   │   ├── LifoStrategy.php
│   │   ├── FefoStrategy.php
│   │   └── CostOptimizedStrategy.php
│   │
│   ├── Event/                   # 事件类
│   │   ├── StockCreatedEvent.php
│   │   ├── StockAllocatedEvent.php
│   │   ├── StockReservedEvent.php
│   │   ├── StockLockedEvent.php
│   │   ├── StockInboundEvent.php
│   │   ├── StockOutboundEvent.php
│   │   └── StockAdjustedEvent.php
│   │
│   ├── EventListener/           # 事件监听器
│   │   ├── StockAlertListener.php
│   │   ├── StockSnapshotListener.php
│   │   └── StockAuditListener.php
│   │
│   ├── Exception/               # 异常类
│   │   ├── InsufficientStockException.php
│   │   ├── BatchNotFoundException.php
│   │   ├── DuplicateBatchException.php
│   │   ├── ReservationExpiredException.php
│   │   └── LockConflictException.php
│   │
│   ├── Model/                   # 值对象和DTO
│   │   ├── StockSummary.php
│   │   ├── StockAllocationResult.php
│   │   ├── StockMovement.php
│   │   └── StockStatus.php
│   │
│   ├── Validator/               # 验证器
│   │   ├── StockBatchValidator.php
│   │   ├── AllocationValidator.php
│   │   └── ReservationValidator.php
│   │
│   ├── DependencyInjection/     # 依赖注入
│   │   └── StockManageExtension.php
│   │
│   └── StockManageBundle.php   # Bundle主类
```

### 3.2 内部类关系

```
StockService
    ├── StockBatchRepository
    ├── AllocationService
    │   └── AllocationStrategyInterface
    │       ├── FifoStrategy
    │       ├── LifoStrategy
    │       └── FefoStrategy
    ├── EventDispatcher
    └── EntityManager

ReservationService
    ├── StockReservationRepository
    ├── StockBatchRepository
    ├── EventDispatcher
    └── EntityManager

LockService
    ├── StockLockRepository
    ├── StockBatchRepository
    ├── EventDispatcher
    └── EntityManager

InboundService/OutboundService
    ├── StockService
    ├── AllocationService
    ├── CostCalculationService
    ├── EventDispatcher
    └── EntityManager
```

### 3.3 数据流设计

#### 入库流程
1. InboundService 接收入库请求
2. 验证入库数据（批次号、数量、质量等）
3. 创建或更新 StockBatch 实体
4. 记录 StockInbound 入库记录
5. 更新成本（CostCalculationService）
6. 触发 StockInboundEvent 事件
7. 返回入库结果

#### 出库流程
1. OutboundService 接收出库请求
2. AllocationService 根据策略分配库存
3. 检查并消耗预留/锁定库存
4. 更新 StockBatch 数量
5. 记录 StockOutbound 出库记录
6. 触发 StockOutboundEvent 事件
7. 返回出库结果

#### 预留流程
1. ReservationService 接收预留请求
2. 检查可用库存
3. 创建 StockReservation 记录
4. 更新批次的预留数量
5. 设置过期时间
6. 触发 StockReservedEvent 事件
7. 返回预留凭证

## 4. 扩展机制

### 4.1 扩展点定义

#### 自定义分配策略
```php
// 实现 AllocationStrategyInterface
class CustomStrategy implements AllocationStrategyInterface
{
    public function allocate(array $batches, int $requiredQuantity): array
    {
        // 自定义分配逻辑
    }
}

// 注册为服务
services:
    app.allocation.custom_strategy:
        class: App\Strategy\CustomStrategy
        tags:
            - { name: stock_manage.allocation_strategy, alias: custom }
```

#### 自定义验证器
```php
// 实现验证逻辑
class CustomStockValidator
{
    public function validate(StockBatch $batch): void
    {
        // 自定义验证逻辑
    }
}
```

### 4.2 事件系统设计

#### 核心事件
- `stock.batch.created` - 批次创建后
- `stock.batch.updated` - 批次更新后
- `stock.reserved` - 库存预留后
- `stock.reservation.confirmed` - 预留确认后
- `stock.reservation.released` - 预留释放后
- `stock.locked` - 库存锁定后
- `stock.lock.released` - 锁定释放后
- `stock.inbound` - 入库完成后
- `stock.outbound` - 出库完成后
- `stock.adjusted` - 库存调整后
- `stock.alert` - 库存预警触发

#### 事件监听示例
```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StockEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'stock.outbound' => 'onStockOutbound',
            'stock.batch.created' => 'onBatchCreated',
        ];
    }
    
    public function onStockOutbound(StockOutboundEvent $event): void
    {
        // 处理出库后的业务逻辑
    }
}
```

### 4.3 配置说明

Bundle 使用环境变量进行配置，在运行时通过 `$_ENV` 读取：

```php
// Service 中读取配置示例
class StockService
{
    private string $allocationStrategy;
    private bool $allowNegativeStock;
    
    public function __construct()
    {
        $this->allocationStrategy = $_ENV['STOCK_ALLOCATION_STRATEGY'] ?? 'fifo';
        $this->allowNegativeStock = (bool)($_ENV['STOCK_ALLOW_NEGATIVE'] ?? false);
    }
}
```

主要环境变量：
- `STOCK_ALLOCATION_STRATEGY` - 默认分配策略
- `STOCK_ALLOW_NEGATIVE` - 是否允许负库存
- `STOCK_RESERVATION_TTL` - 预留过期时间
- `STOCK_LOCK_TTL` - 锁定过期时间

## 5. 集成设计

### 5.1 Symfony 集成（Bundle）

#### Bundle Extension（最简配置）
```php
namespace StockManageBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class StockManageExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // 仅加载服务配置
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');
    }
}
```

#### 服务配置（services.yaml）
```yaml
# Resources/config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: false

    StockManageBundle\:
        resource: '../../*'
        exclude:
            - '../../{Entity,Model,Exception,Event}'
            - '../../StockManageBundle.php'

    # 核心服务声明
    StockManageBundle\Service\StockServiceInterface:
        alias: StockManageBundle\Service\StockService
        public: true

    StockManageBundle\Service\ReservationServiceInterface:
        alias: StockManageBundle\Service\ReservationService
        public: true

    StockManageBundle\Service\LockServiceInterface:
        alias: StockManageBundle\Service\LockService
        public: true

    # 策略服务自动标记
    StockManageBundle\Strategy\:
        resource: '../../Strategy/*'
        tags: ['stock_manage.allocation_strategy']
```

### 5.2 独立使用指南

```php
use StockManageBundle\Service\StockService;
use StockManageBundle\Repository\StockBatchRepository;
use Doctrine\ORM\EntityManager;

// 手动初始化
$entityManager = // ... 获取 EntityManager
$batchRepository = new StockBatchRepository($entityManager);
$allocationService = new AllocationService([
    new FifoStrategy(),
    new LifoStrategy(),
]);

$stockService = new StockService(
    $entityManager,
    $batchRepository,
    $allocationService,
    $eventDispatcher
);

// 使用服务
$batch = $stockService->createBatch([
    'spu_id' => 'SPU001',
    'batch_no' => 'BATCH001',
    'quantity' => 100,
]);
```

## 6. 测试策略

### 6.1 单元测试方案

```php
namespace StockManageBundle\Tests\Service;

class StockServiceTest extends TestCase
{
    private StockService $service;
    private MockObject $repository;
    private MockObject $entityManager;
    
    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockBatchRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        
        $this->service = new StockService(
            $this->entityManager,
            $this->repository,
            // ... 其他依赖
        );
    }
    
    public function testCreateBatch(): void
    {
        $data = [
            'spu_id' => 'SPU001',
            'batch_no' => 'BATCH001',
            'quantity' => 100,
        ];
        
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['batchNo' => 'BATCH001'])
            ->willReturn(null);
        
        $batch = $this->service->createBatch($data);
        
        $this->assertInstanceOf(StockBatch::class, $batch);
        $this->assertEquals('BATCH001', $batch->getBatchNo());
        $this->assertEquals(100, $batch->getQuantity());
    }
}
```

### 6.2 集成测试方案

```php
namespace StockManageBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StockWorkflowTest extends KernelTestCase
{
    private StockServiceInterface $stockService;
    private ReservationServiceInterface $reservationService;
    
    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->stockService = $container->get(StockServiceInterface::class);
        $this->reservationService = $container->get(ReservationServiceInterface::class);
    }
    
    public function testCompleteStockFlow(): void
    {
        // 创建批次
        $batch = $this->stockService->createBatch([
            'spu_id' => 'SPU001',
            'batch_no' => 'TEST_BATCH',
            'quantity' => 1000,
        ]);
        
        // 预留库存
        $reservation = $this->reservationService->reserve([
            'spu_id' => 'SPU001',
            'quantity' => 100,
            'type' => 'order',
            'business_id' => 'ORDER001',
        ]);
        
        // 确认预留
        $this->reservationService->confirm($reservation->getId());
        
        // 验证库存
        $summary = $this->stockService->getAvailableStock('SPU001');
        $this->assertEquals(900, $summary->getAvailableQuantity());
    }
}
```

### 6.3 性能基准测试

```php
namespace StockManageBundle\Tests\Benchmark;

use PhpBench\Benchmark\Metadata\Annotations as Bench;

class AllocationBenchmark
{
    /**
     * @Bench\Revs(1000)
     * @Bench\Iterations(5)
     * @Bench\Groups({"allocation"})
     */
    public function benchFifoAllocation(): void
    {
        $strategy = new FifoStrategy();
        $batches = $this->generateBatches(100);
        
        $strategy->allocate($batches, 500);
    }
    
    /**
     * @Bench\Revs(1000)
     * @Bench\Iterations(5)
     * @Bench\Groups({"allocation"})
     */
    public function benchFefoAllocation(): void
    {
        $strategy = new FefoStrategy();
        $batches = $this->generateBatches(100);
        
        $strategy->allocate($batches, 500);
    }
}
```

## 7. 数据设计

### 7.1 实体设计

#### StockBatch - 库存批次
```php
#[ORM\Entity(repositoryClass: StockBatchRepository::class)]
#[ORM\Table(name: 'stock_batches')]
#[ORM\Index(columns: ['spu_id', 'status'], name: 'idx_spu_status')]
#[ORM\Index(columns: ['batch_no'], name: 'idx_batch_no')]
class StockBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 50)]
    private string $spuId;
    
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $batchNo;
    
    #[ORM\Column(type: 'integer')]
    private int $quantity = 0;
    
    #[ORM\Column(type: 'integer')]
    private int $availableQuantity = 0;
    
    #[ORM\Column(type: 'integer')]
    private int $reservedQuantity = 0;
    
    #[ORM\Column(type: 'integer')]
    private int $lockedQuantity = 0;
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $unitCost;
    
    #[ORM\Column(type: 'string', length: 20)]
    private string $qualityLevel;
    
    #[ORM\Column(type: 'string', length: 20)]
    private string $status;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $productionDate = null;
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;
    
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $locationId = null;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $attributes = null;
    
    #[ORM\Column(name: 'create_time', type: 'datetime_immutable')]
    private \DateTimeImmutable $createTime;

    #[ORM\Column(name: 'update_time', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updateTime = null;
    
    // Getter/Setter methods...
}
```

#### StockReservation - 库存预留
```php
#[ORM\Entity(repositoryClass: StockReservationRepository::class)]
#[ORM\Table(name: 'stock_reservations')]
class StockReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    
    #[ORM\Column(type: 'string', length: 50)]
    private string $spuId;
    
    #[ORM\Column(type: 'integer')]
    private int $quantity;
    
    #[ORM\Column(type: 'string', length: 30)]
    private string $type;
    
    #[ORM\Column(type: 'string', length: 100)]
    private string $businessId;
    
    #[ORM\Column(type: 'string', length: 20)]
    private string $status;
    
    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expiresAt;
    
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $batchAllocations = null;
    
    #[ORM\Column(name: 'create_time', type: 'datetime_immutable')]
    private \DateTimeImmutable $createTime;

    // Getter/Setter methods...
}
```

### 7.2 实体关系映射

```
StockBatch (1) ──┬──> (*) StockSerialNumber
                 ├──> (*) BusinessStockLock
                 ├──> (*) OperationalStockLock
                 └──> (*) StockReservation (通过 batchAllocations)

StockInbound (1) ──> (*) StockBatch (创建)
StockOutbound (1) ──> (*) StockBatch (消耗)
StockTransfer (1) ──> (*) StockBatch (移动)
StockAdjustment (1) ──> (*) StockBatch (调整)

StockSnapshot (1) ──> (*) StockBatch (快照时刻)
BundleStock (1) ──> (*) StockBatch (组合)
VirtualStock (1) ──> (1) StockBatch (虚拟映射)
```

### 7.3 缓存策略

- **查询缓存**：缓存热点 SPU 的库存汇总
- **计算缓存**：缓存成本计算结果
- **策略缓存**：缓存分配策略结果
- **TTL 设置**：
  - 库存汇总：60 秒
  - 成本计算：300 秒
  - 分配结果：30 秒


## 8. 部署设计

### 8.1 环境配置

```bash
# .env.local
STOCK_ALLOCATION_STRATEGY=fifo
STOCK_ALLOW_NEGATIVE=false
STOCK_RESERVATION_TTL=3600
STOCK_LOCK_TTL=7200
STOCK_COST_METHOD=weighted_average
STOCK_ALERT_LOW_THRESHOLD=100
STOCK_ALERT_HIGH_THRESHOLD=10000
```

### 8.2 扩展策略

- **水平扩展**：服务无状态，支持多实例部署
- **读写分离**：查询操作走从库，写操作走主库
- **分片策略**：按 SPU ID 哈希分片
- **缓存层**：Redis 集群缓存热点数据

### 8.3 监控方案

- **关键指标**：
  - 库存准确率
  - 分配成功率
  - 预留过期率
  - API 响应时间
  - 并发锁冲突率

- **告警规则**：
  - 库存不一致告警
  - 批次过期告警
  - 性能下降告警
  - 异常操作告警

## 9. 安全考虑

### 9.1 权限控制
- 基于角色的访问控制（RBAC）
- 操作级别的权限粒度
- API Token 认证

### 9.2 数据安全
- 敏感数据加密存储
- 完整的审计日志
- 数据备份策略

### 9.3 并发控制
- 乐观锁防止并发冲突
- 事务隔离级别配置
- 分布式锁支持

## 10. 性能优化

### 10.1 查询优化
- 合理的索引设计
- 查询结果缓存
- 批量查询接口

### 10.2 写入优化
- 批量操作支持
- 异步处理非关键操作
- 写入缓冲区

### 10.3 计算优化
- 增量计算成本
- 预计算常用指标
- 并行处理批次分配

## 11. 质量保证

### 11.1 代码质量
- PHPStan Level 8 静态分析
- 代码覆盖率 >= 90%
- 遵循 PSR-12 编码规范

### 11.2 测试覆盖
- 单元测试：Service 层 100% 覆盖
- 集成测试：核心流程 100% 覆盖
- 性能测试：关键操作基准测试

### 11.3 文档完整性
- API 文档自动生成
- 使用示例完整
- 配置说明详细

## 12. 设计验证

### 架构合规性检查 ✅
- [x] 不使用 DDD 分层架构
- [x] 采用扁平化 Service 层设计
- [x] 实体是贫血模型（只有 getter/setter）
- [x] 业务逻辑都在 Service 中
- [x] 遵循 KISS 和 YAGNI 原则

### 需求满足检查 ✅
- [x] 满足所有功能需求（FR-01 到 FR-50）
- [x] 满足所有非功能需求（NFR-01 到 NFR-20）
- [x] 包含完整的接口定义
- [x] 定义了清晰的组件边界
- [x] 涵盖错误处理和边缘案例

### 质量标准检查 ✅
- [x] 包含安全考虑
- [x] 包含性能考虑
- [x] 可以用所选技术栈实现
- [x] 遵循质量要求
- [x] 符合测试策略