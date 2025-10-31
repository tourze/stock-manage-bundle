<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StockInboundWizardSkuApiController extends AbstractController
{
    #[Route(path: '/admin/stock/inbound-wizard/api/skus', name: 'admin_stock_inbound_wizard_api_skus', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        // 通过 AJAX 获取 SKU 列表的功能
        // 建议前端通过具体的 SKU ID 调用 loadSkuByIdentifier 方法
        // 或者考虑创建一个专门的 SKU 查询服务

        // 临时返回空数组，需要实现具体的 SKU 查询逻辑
        $data = [];

        // TODO: 实现 SKU 查询逻辑，避免直接调用跨模块的 Repository
        // 可以考虑：
        // 1. 在 StockManageBundle 中创建一个 SkuQueryService
        // 2. 或者通过事件系统获取 SKU 数据
        // 3. 或者让前端通过搜索接口动态加载 SKU

        return new JsonResponse($data);
    }
}
