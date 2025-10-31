# Product Stock Bundle - 需求规范

## 1. 概述

### 1.1 项目背景
Product Stock Bundle 是一个通用的库存管理引擎，旨在为电商系统和企业应用提供完整的库存管理能力。该Bundle既可作为通用库存管理系统，又针对电商场景进行了特别优化。

### 1.2 核心目标
- 提供完整的批次化库存管理能力
- 支持多种库存分配策略（FIFO/LIFO/FEFO等）
- 实现库存预留和锁定机制
- 提供库存预警和自动补货建议
- 支持虚拟库存和组合商品
- 确保高并发场景下的数据一致性

### 1.3 适用范围
- 电商平台的库存管理
- ERP系统的库存模块
- WMS仓储管理系统
- 供应链管理系统

## 2. 功能需求

### 2.1 批次管理
- **FR-01**: 系统应支持按批次管理库存，每个批次包含批次号、数量、成本、质量等级等信息
- **FR-02**: 系统应支持批次的创建、合并、拆分操作
- **FR-03**: 系统应跟踪批次的生产日期和过期日期
- **FR-04**: 系统应支持批次状态管理（待入库/在途/可用/部分可用/已耗尽/已过期/已损坏/隔离中）
- **FR-05**: 系统应支持批次的序列号管理，跟踪单个商品

### 2.2 库存操作
- **FR-06**: 系统应支持入库操作（采购入库/退货入库/调拨入库/生产入库）
- **FR-07**: 系统应支持出库操作（销售出库/损耗出库/调拨出库/领用出库）
- **FR-08**: 系统应支持库存调拨（跨位置移动）
- **FR-09**: 系统应支持库存盘点和调整
- **FR-10**: 系统应支持质量状态变更（良品/次品/残品转换）

### 2.3 库存锁定
- **FR-11**: 系统应支持业务锁定（订单/活动/预售）
- **FR-12**: 系统应支持操作锁定（调拨/质检/盘点）
- **FR-13**: 系统应记录锁定原因和详细说明
- **FR-14**: 系统应支持锁定的自动过期和释放
- **FR-15**: 系统应支持部分锁定和部分释放

### 2.4 库存预留
- **FR-16**: 系统应支持多种预留类型（订单/活动/VIP客户）
- **FR-17**: 系统应记录预留原因和业务背景
- **FR-18**: 系统应支持预留的确认、释放和过期处理
- **FR-19**: 系统应支持预留期限的延长
- **FR-20**: 系统应支持按批次的预留分配

### 2.5 分配策略
- **FR-21**: 系统应支持FIFO（先进先出）分配策略
- **FR-22**: 系统应支持LIFO（后进先出）分配策略
- **FR-23**: 系统应支持FEFO（先过期先出）分配策略
- **FR-24**: 系统应支持成本优化分配策略
- **FR-25**: 系统应支持质量优先分配策略
- **FR-26**: 系统应支持混合分配策略

### 2.6 成本核算
- **FR-27**: 系统应支持移动加权平均成本计算
- **FR-28**: 系统应支持FIFO成本计算
- **FR-29**: 系统应支持批次成本追踪
- **FR-30**: 系统应提供库存价值报表

### 2.7 库存快照
- **FR-31**: 系统应支持创建库存快照（日终/月终/盘点/临时）
- **FR-32**: 系统应记录快照触发方式（自动/手动/事件/系统/紧急）
- **FR-33**: 系统应保存快照明细和汇总数据
- **FR-34**: 系统应支持快照对比分析

### 2.8 组合商品
- **FR-35**: 系统应支持组合商品定义（固定组合/灵活组合）
- **FR-36**: 系统应计算组合商品的可用库存
- **FR-37**: 系统应支持组合商品的部分发货
- **FR-38**: 系统应支持组合商品的自动拆分

### 2.9 虚拟库存
- **FR-39**: 系统应支持预售库存管理
- **FR-40**: 系统应支持期货库存管理
- **FR-41**: 系统应支持负库存配置
- **FR-42**: 系统应支持共享库存池

### 2.10 库存预警
- **FR-43**: 系统应支持低库存预警
- **FR-44**: 系统应支持高库存预警
- **FR-45**: 系统应支持过期预警
- **FR-46**: 系统应提供自动补货建议
- **FR-47**: 系统应支持多渠道通知（邮件/短信/系统消息）

### 2.11 在途库存
- **FR-48**: 系统应跟踪在途库存状态
- **FR-49**: 系统应记录预计到达和实际到达时间
- **FR-50**: 系统应集成物流追踪信息

## 3. 非功能需求

### 3.1 性能需求
- **NFR-01**: 单个SKU查询响应时间 < 100ms
- **NFR-02**: 批量库存分配处理能力 > 1000 SKU/秒
- **NFR-03**: 支持至少100万个SKU的库存管理
- **NFR-04**: 支持至少1000万条库存记录

### 3.2 可靠性需求
- **NFR-05**: 系统可用性 > 99.9%
- **NFR-06**: 数据一致性保证（ACID事务）
- **NFR-07**: 支持数据备份和恢复
- **NFR-08**: 支持故障转移和灾难恢复

### 3.3 安全需求
- **NFR-09**: 支持操作审计日志
- **NFR-10**: 支持权限控制（基于角色）
- **NFR-11**: 敏感数据加密存储
- **NFR-12**: 防止库存超卖

### 3.4 可扩展性需求
- **NFR-13**: 支持水平扩展
- **NFR-14**: 支持自定义分配策略
- **NFR-15**: 支持插件式扩展
- **NFR-16**: 提供完整的事件系统

### 3.5 兼容性需求
- **NFR-17**: PHP >= 8.1
- **NFR-18**: Symfony >= 6.4
- **NFR-19**: 支持MySQL/PostgreSQL
- **NFR-20**: 提供RESTful API接口（可选）

## 4. 约束条件

### 4.1 技术约束
- 必须使用Doctrine ORM进行数据持久化
- 必须遵循Symfony Bundle最佳实践
- 必须通过PHPStan Level 8检查
- 测试覆盖率必须 >= 90%

### 4.2 业务约束
- 库存数据必须实时准确
- 不允许负库存（除非明确配置）
- 所有操作必须有审计记录
- 批次追溯链必须完整

### 4.3 集成约束
- 必须与product-core-bundle的SPU实体集成
- 可选与warehouse-operation-bundle集成
- 支持消息队列异步处理
- 支持缓存系统集成

## 5. 接口定义

### 5.1 核心服务接口
```php
interface StockServiceInterface {
    public function createBatch(CreateBatchCommand $command): StockBatch;
    public function allocate(StockAllocationRequest $request): StockAllocationResult;
    public function getAvailableStock(SPU $spu, ?StockQueryCriteria $criteria = null): StockSummary;
}

interface ReservationServiceInterface {
    public function reserve(ReserveCommand $command): StockReservation;
    public function confirm(string $reservationId): void;
    public function release(string $reservationId): void;
}

interface LockServiceInterface {
    public function lockForBusiness(BusinessLockCommand $command): BusinessStockLock;
    public function lockForOperation(OperationLockCommand $command): OperationalStockLock;
    public function releaseLock(string $lockId, string $lockType, ReleaseReason $reason): void;
}
```

### 5.2 事件接口
```php
interface StockEventInterface {
    public function getStockBatch(): StockBatch;
    public function getQuantity(): int;
    public function getOperator(): ?string;
    public function getOccurredAt(): \DateTimeInterface;
}
```

## 6. 数据模型

### 6.1 核心实体
- StockBatch（库存批次）
- StockSerialNumber（序列号）
- StockInbound（入库记录）
- StockOutbound（出库记录）
- StockTransfer（调拨记录）
- StockAdjustment（盘点调整）
- BusinessStockLock（业务锁定）
- OperationalStockLock（操作锁定）
- StockReservation（库存预留）
- StockSnapshot（库存快照）
- BundleStock（组合商品）
- VirtualStock（虚拟库存）
- StockAlert（库存预警）
- StockInTransit（在途库存）

## 7. 验收标准

### 7.1 功能验收
- 所有功能需求必须实现并通过测试
- 提供完整的单元测试和集成测试
- 提供使用示例和文档

### 7.2 性能验收
- 通过压力测试验证性能指标
- 提供性能测试报告
- 优化关键查询的执行计划

### 7.3 质量验收
- 通过PHPStan Level 8检查
- 代码覆盖率 >= 90%
- 通过代码审查
- 符合编码规范

## 8. 风险评估

### 8.1 技术风险
- **风险**: 高并发下的数据一致性
- **缓解**: 使用乐观锁和事务隔离

### 8.2 业务风险
- **风险**: 库存计算错误导致超卖
- **缓解**: 完善的测试和库存校验机制

### 8.3 集成风险
- **风险**: 与现有系统集成困难
- **缓解**: 提供清晰的接口和适配层

## 9. 里程碑

- **M1**: 基础架构和批次管理（第1-2周）
- **M2**: 库存操作和锁定机制（第3-4周）
- **M3**: 预留和分配策略（第5-6周）
- **M4**: 电商特性和优化（第7-8周）
- **M5**: 测试、文档和发布（第9-10周）