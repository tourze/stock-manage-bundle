# Task 4 In Progress: 阶段4 - 库存操作服务

## 已完成内容

### Task 4.1: 实体定义 - 操作记录 ✅

创建了以下操作记录实体（贫血模型）：

#### StockInbound - 入库记录
- 支持入库类型：purchase（采购）、return（退货）、transfer（调拨）、production（生产）、adjustment（调整）
- 字段包含：业务单号、入库明细、总金额、总数量、操作人、位置、备注等
- 自动计算总金额和总数量
- 使用 Doctrine Types 常量和添加了中文注释

#### StockOutbound - 出库记录
- 支持出库类型：sales（销售）、damage（损耗）、transfer（调拨）、pick（领用）、adjustment（调整）、sample（样品）
- 字段包含：业务单号、出库明细、批次分配明细、总成本、总数量等
- 分离出库请求（items）和实际分配（allocations）
- 自动计算总成本和总数量

#### StockTransfer - 调拨记录
- 支持调拨状态：pending（待处理）、in_transit（运输中）、received（已接收）、cancelled（已取消）
- 字段包含：调拨单号、源位置、目标位置、调拨明细、发起人、接收人等
- 跟踪发出时间和接收时间
- 包含状态判断辅助方法

### 需要完成的实体

#### StockAdjustment - 盘点调整
- 待实现

## 已解决的问题

1. **命名空间问题**：修复了测试文件中不一致的命名空间
2. **Composer 自动加载配置**：在根 composer.json 中添加了 StockManageBundle 的 PSR-4 配置
   - 需要用户运行 `composer dump-autoload` 使配置生效

## 待完成任务

### Task 4.2: InboundService 实现
- 实现入库服务接口
- 支持多种入库类型
- 更新批次库存
- 记录入库历史
- 触发入库事件

### Task 4.3: OutboundService 实现
- 实现出库服务接口
- 集成分配策略
- 消耗库存和预留
- 记录出库历史
- 计算出库成本

### Task 4.4: TransferService & AdjustmentService
- 实现库存调拨
- 实现盘点调整
- 保证数据一致性
- 生成调整报告

## 技术要点

1. **贫血模型设计**：所有实体只包含数据和 getter/setter，业务逻辑在 Service 层
2. **使用 Doctrine Types 常量**：遵循 PHPStan 规范要求
3. **字段注释**：所有字段都添加了中文注释说明
4. **自动计算**：在设置明细时自动计算总量和总金额
5. **事务一致性**：入库出库操作需要在事务中执行

## 下一步计划

1. 创建 StockAdjustment 实体
2. 实现 InboundService 服务
3. 实现 OutboundService 服务
4. 实现 TransferService 和 AdjustmentService
5. 创建相应的测试用例
6. 运行质量检查三连

## 用户需要执行的操作

在项目根目录运行以下命令更新自动加载：
```bash
composer dump-autoload
```

这将使新添加的 StockManageBundle 命名空间生效，测试才能正常运行。