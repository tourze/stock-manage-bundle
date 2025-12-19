# Stock Manage Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/stock-manage-bundle.svg)](https://packagist.org/packages/tourze/stock-manage-bundle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue.svg)](https://php.net)

`tourze/stock-manage-bundle` 只保留“核心库存 + 聚合入口”：

- 核心库存：批次、库存日志、虚拟库存、分配策略、基础查询
- 聚合入口：检测到对应模块已安装时自动启用各 `stock-*` 扩展 Bundle（可插拔）

## 核心内容

- 核心实体：`Tourze\StockManageBundle\Entity\StockBatch` / `StockLog` / `VirtualStock`
- 核心服务：`Tourze\StockManageBundle\Service\StockServiceInterface`、`BatchQueryServiceInterface`
- 分配策略：FIFO/LIFO/FEFO（见 `Tourze\StockManageBundle\Service\AllocationStrategy\*`）

## 扩展模块（按需安装）

- 入库：`tourze/stock-inbound-bundle`
- 出库：`tourze/stock-outbound-bundle`
- 预占：`tourze/stock-reservation-bundle`
- 调拨：`tourze/stock-transfer-bundle`
- 组合商品库存（`BundleItem` / `BundleStock`）：`tourze/stock-bundle-bundle`
- 库存调整：`tourze/stock-adjustment-bundle`
- 库存锁定：`tourze/stock-lock-bundle`
- 预警：`tourze/stock-alert-bundle`
- 快照：`tourze/stock-snapshot-bundle`
- 成本：`tourze/stock-cost-bundle`

## 安装

```bash
composer require tourze/stock-manage-bundle
```

在 `config/bundles.php` 中注册：

```php
return [
    // ...
    Tourze\StockManageBundle\StockManageBundle::class => ['all' => true],
];
```

如果项目启用了 `tourze/bundle-dependency`，在安装上述扩展 Bundle 后会被自动启用（见 `Tourze\StockManageBundle\StockManageBundle::getBundleDependencies()`）。

## 使用建议

- 业务侧优先注入接口：`InboundServiceInterface` / `OutboundServiceInterface` / `ReservationServiceInterface`
- 实现与服务别名由各自扩展 Bundle 提供；未安装模块时不要注入对应接口

## 快速开始（核心库存）

```php
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Service\StockServiceInterface;

$stockService = $container->get(StockServiceInterface::class);

$sku = new SKU('PROD001');
$batch = $stockService->createBatch([
    'sku' => $sku,
    'batch_no' => 'BATCH20240101001',
    'quantity' => 100,
]);
```

