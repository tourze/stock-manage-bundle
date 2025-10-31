<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\InboundService;

/**
 * 测试入库流程的数据一致性.
 *
 * @internal
 */
#[CoversClass(InboundService::class)]
#[RunTestsInSeparateProcesses]
class InboundDataConsistencyTest extends AbstractIntegrationTestCase
{
    private InboundService $inboundService;

    private StockBatchRepository $batchRepository;

    protected function onSetUp(): void
    {
        $this->inboundService = self::getService(InboundService::class);
        $this->batchRepository = self::getService(StockBatchRepository::class);
    }

    /**
     * 创建测试用的 SKU 实体.
     */
    private function createTestSku(?string $gtin = null): Sku
    {
        $spu = new Spu();
        $spu->setTitle('测试商品');
        $spu->setSubtitle('测试商品副标题');

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setGtin($gtin ?? 'TEST-' . uniqid());
        $sku->setUnit('个');
        $sku->setValid(true);

        self::getEntityManager()->persist($spu);
        self::getEntityManager()->persist($sku);
        self::getEntityManager()->flush();

        return $sku;
    }

    public function testPurchaseInboundCreatesConsistentData(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('PURCHASE-TEST-001');

        // 准备采购入库数据
        $inboundData = [
            'purchase_order_no' => 'PO-2024-001',
            'operator' => 'test-operator',
            'location_id' => 'WAREHOUSE-A',
            'notes' => '采购入库测试',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                    'unit_cost' => 10.50,
                    'quality_level' => 'A',
                ],
            ],
        ];

        // 执行采购入库
        $inbound = $this->inboundService->purchaseInbound($inboundData);

        // 验证入库单数据一致性
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('PO-2024-001', $inbound->getReferenceNo());
        $this->assertEquals('test-operator', $inbound->getOperator());
        $this->assertEquals('WAREHOUSE-A', $inbound->getLocationId());
        $this->assertEquals('采购入库测试', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals(100, $item['quantity']);
        $this->assertEquals(10.50, $item['unit_cost']);
        $this->assertEquals(1050.0, $item['amount']);
        $this->assertEquals('A', $item['quality_level']);
        $this->assertNotEmpty($item['batch_no']);
        $this->assertNotEmpty($item['batch_id']);
        $this->assertIsInt($item['batch_id']);

        // 验证批次创建
        $batch = $this->batchRepository->find($item['batch_id']);
        $this->assertNotNull($batch);
        $this->assertEquals($sku, $batch->getSku());
        $this->assertEquals($item['batch_no'], $batch->getBatchNo());
        $this->assertEquals(100, $batch->getQuantity());
        $this->assertEquals(100, $batch->getAvailableQuantity());
        $this->assertEquals(10.50, $batch->getUnitCost());
        $this->assertEquals('A', $batch->getQualityLevel());
        $this->assertEquals('available', $batch->getStatus());
    }

    public function testMultipleItemsInboundCreatesMultipleBatches(): void
    {
        // 创建两个测试 SKU
        $sku1 = $this->createTestSku('MULTIPLE-TEST-001');
        $sku2 = $this->createTestSku('MULTIPLE-TEST-002');

        // 准备多物料采购入库数据
        $inboundData = [
            'purchase_order_no' => 'PO-2024-002',
            'operator' => 'test-operator',
            'items' => [
                [
                    'sku' => $sku1,
                    'quantity' => 50,
                    'unit_cost' => 15.00,
                    'quality_level' => 'A',
                ],
                [
                    'sku' => $sku2,
                    'quantity' => 30,
                    'unit_cost' => 25.00,
                    'quality_level' => 'B',
                ],
            ],
        ];

        // 执行采购入库
        $inbound = $this->inboundService->purchaseInbound($inboundData);

        // 验证入库项目
        $items = $inbound->getItems();
        $this->assertCount(2, $items);

        // 验证第一个物料的批次
        $item0 = $items[0];
        $this->assertIsArray($item0);
        $this->assertIsInt($item0['batch_id']);
        $batch1 = $this->batchRepository->find($item0['batch_id']);
        $this->assertNotNull($batch1);
        $this->assertEquals($sku1, $batch1->getSku());
        $this->assertEquals(50, $batch1->getQuantity());
        $this->assertEquals(15.00, $batch1->getUnitCost());
        $this->assertEquals('A', $batch1->getQualityLevel());

        // 验证第二个物料的批次
        $item1 = $items[1];
        $this->assertIsArray($item1);
        $this->assertIsInt($item1['batch_id']);
        $batch2 = $this->batchRepository->find($item1['batch_id']);
        $this->assertNotNull($batch2);
        $this->assertEquals($sku2, $batch2->getSku());
        $this->assertEquals(30, $batch2->getQuantity());
        $this->assertEquals(25.00, $batch2->getUnitCost());
        $this->assertEquals('B', $batch2->getQualityLevel());

        // 验证批次号不同
        $this->assertNotEquals($batch1->getBatchNo(), $batch2->getBatchNo());

        // 验证批次ID不同
        $this->assertNotEquals($batch1->getId(), $batch2->getId());
    }

    public function testBatchNoReusabilityInDifferentInbounds(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('BATCH-REUSE-TEST-001');

        // 第一次采购入库
        $inboundData1 = [
            'purchase_order_no' => 'PO-2024-003',
            'operator' => 'test-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                    'unit_cost' => 10.00,
                    'quality_level' => 'A',
                ],
            ],
        ];

        $inbound1 = $this->inboundService->purchaseInbound($inboundData1);
        $items1 = $inbound1->getItems();
        $item1 = $items1[0];
        $this->assertIsArray($item1);
        $this->assertArrayHasKey('batch_no', $item1);
        $this->assertIsString($item1['batch_no']);
        $firstBatchNo = $item1['batch_no'];

        // 第二次采购入库
        $inboundData2 = [
            'purchase_order_no' => 'PO-2024-004',
            'operator' => 'test-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 80,
                    'unit_cost' => 12.00,
                    'quality_level' => 'A',
                ],
            ],
        ];

        $inbound2 = $this->inboundService->purchaseInbound($inboundData2);
        $items2 = $inbound2->getItems();
        $item2 = $items2[0];
        $this->assertIsArray($item2);
        $this->assertArrayHasKey('batch_no', $item2);
        $this->assertIsString($item2['batch_no']);
        $secondBatchNo = $item2['batch_no'];

        // 验证批次号不能重用（每次入库都生成新的批次号）
        $this->assertNotEquals($firstBatchNo, $secondBatchNo);

        // 验证两个批次都存在且独立
        $batch1 = $this->batchRepository->findOneBy(['batchNo' => $firstBatchNo]);
        $batch2 = $this->batchRepository->findOneBy(['batchNo' => $secondBatchNo]);

        $this->assertNotNull($batch1);
        $this->assertNotNull($batch2);
        $this->assertNotEquals($batch1->getId(), $batch2->getId());

        // 验证批次数据
        $this->assertEquals(100, $batch1->getQuantity());
        $this->assertEquals(10.00, $batch1->getUnitCost());

        $this->assertEquals(80, $batch2->getQuantity());
        $this->assertEquals(12.00, $batch2->getUnitCost());
    }

    public function testGenerateBatchNo(): void
    {
        // 测试不同类型的批次号生成
        $purchaseBatchNo = $this->inboundService->generateBatchNo('purchase');
        $this->assertStringStartsWith('PUR-', $purchaseBatchNo);
        $this->assertMatchesRegularExpression('/^PUR-\d{8}-\d{4}$/', $purchaseBatchNo);

        $productionBatchNo = $this->inboundService->generateBatchNo('production');
        $this->assertStringStartsWith('PROD-', $productionBatchNo);
        $this->assertMatchesRegularExpression('/^PROD-\d{8}-\d{4}$/', $productionBatchNo);

        $returnBatchNo = $this->inboundService->generateBatchNo('return');
        $this->assertStringStartsWith('RET-', $returnBatchNo);
        $this->assertMatchesRegularExpression('/^RET-\d{8}-\d{4}$/', $returnBatchNo);

        $transferBatchNo = $this->inboundService->generateBatchNo('transfer');
        $this->assertStringStartsWith('TRF-', $transferBatchNo);
        $this->assertMatchesRegularExpression('/^TRF-\d{8}-\d{4}$/', $transferBatchNo);

        $adjustmentBatchNo = $this->inboundService->generateBatchNo('adjustment');
        $this->assertStringStartsWith('ADJ-', $adjustmentBatchNo);
        $this->assertMatchesRegularExpression('/^ADJ-\d{8}-\d{4}$/', $adjustmentBatchNo);

        $defaultBatchNo = $this->inboundService->generateBatchNo('unknown');
        $this->assertStringStartsWith('BATCH-', $defaultBatchNo);
        $this->assertMatchesRegularExpression('/^BATCH-\d{8}-\d{4}$/', $defaultBatchNo);
    }

    public function testGenerateUniqueBatchNo(): void
    {
        // 测试唯一批次号生成
        $batchNumbers = [];
        for ($i = 0; $i < 10; ++$i) {
            $batchNo = $this->inboundService->generateUniqueBatchNo('purchase');
            $this->assertNotContains($batchNo, $batchNumbers, '批次号应该是唯一的');
            $batchNumbers[] = $batchNo;
        }

        // 测试格式
        foreach ($batchNumbers as $batchNo) {
            $this->assertStringStartsWith('PUR-', $batchNo);
            $this->assertMatchesRegularExpression('/^PUR-\d{8}-\d{4}$/', $batchNo);
        }
    }

    public function testProductionInbound(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('PRODUCTION-TEST-001');

        // 准备生产入库数据
        $inboundData = [
            'production_order_no' => 'PROD-2024-001',
            'operator' => 'production-operator',
            'location_id' => 'PRODUCTION-LINE-1',
            'notes' => '生产入库测试',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 200,
                    'unit_cost' => 8.75,
                    'quality_level' => 'A',
                    'production_date' => new \DateTimeImmutable('2024-01-15'),
                ],
            ],
        ];

        // 执行生产入库
        $inbound = $this->inboundService->productionInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('PROD-2024-001', $inbound->getReferenceNo());
        $this->assertEquals('production-operator', $inbound->getOperator());
        $this->assertEquals('PRODUCTION-LINE-1', $inbound->getLocationId());
        $this->assertEquals('生产入库测试', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals(200, $item['quantity']);
        $this->assertEquals(8.75, $item['unit_cost']);
        $this->assertEquals(1750.0, $item['amount']);
        $this->assertEquals('A', $item['quality_level']);
        $this->assertEquals('2024-01-15', $item['production_date']);
        $batchNo = $item['batch_no'];
        $this->assertIsString($batchNo);
        $this->assertStringStartsWith('PROD-', $batchNo);
        $this->assertIsInt($item['batch_id']);

        // 验证批次创建
        $batch = $this->batchRepository->find($item['batch_id']);
        $this->assertNotNull($batch);
        $this->assertEquals($sku, $batch->getSku());
        $this->assertEquals(200, $batch->getQuantity());
        $this->assertEquals(200, $batch->getAvailableQuantity());
        $this->assertEquals(8.75, $batch->getUnitCost());
        $this->assertEquals('A', $batch->getQualityLevel());
        $this->assertEquals('available', $batch->getStatus());
        $this->assertNotNull($batch->getProductionDate());
    }

    public function testReturnInbound(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('RETURN-TEST-001');

        // 先创建一个原始批次模拟之前的出库
        $originalBatchNo = 'PUR-20240101-0001';
        $originalBatch = new StockBatch();
        $originalBatch->setSku($sku);
        $originalBatch->setBatchNo($originalBatchNo);
        $originalBatch->setQuantity(50);
        $originalBatch->setAvailableQuantity(0); // 模拟已全部出库
        $originalBatch->setUnitCost(15.00);
        $originalBatch->setQualityLevel('A');
        $originalBatch->setStatus('sold_out');

        self::getEntityManager()->persist($originalBatch);
        self::getEntityManager()->flush();

        // 准备退货入库数据
        $inboundData = [
            'return_order_no' => 'RT-2024-001',
            'operator' => 'return-operator',
            'location_id' => 'RETURN-AREA',
            'notes' => '客户退货',
            'items' => [
                [
                    'sku' => $sku,
                    'batch_no' => $originalBatchNo,
                    'quantity' => 30,
                    'quality_level' => 'A',
                ],
            ],
        ];

        // 执行退货入库
        $inbound = $this->inboundService->returnInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('RT-2024-001', $inbound->getReferenceNo());
        $this->assertEquals('return-operator', $inbound->getOperator());
        $this->assertEquals('RETURN-AREA', $inbound->getLocationId());
        $this->assertEquals('客户退货', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals($originalBatchNo, $item['batch_no']);
        $this->assertEquals(30, $item['quantity']);
        $this->assertEquals(15.00, $item['unit_cost']); // 使用原批次的成本
        $this->assertEquals(450.0, $item['amount']);
        $this->assertEquals('A', $item['quality_level']);

        // 验证原批次库存增加
        self::getEntityManager()->clear();
        $updatedBatch = $this->batchRepository->findOneBy(['batchNo' => $originalBatchNo]);
        $this->assertNotNull($updatedBatch);
        $this->assertEquals(80, $updatedBatch->getQuantity()); // 50 + 30
        $this->assertEquals(30, $updatedBatch->getAvailableQuantity()); // 0 + 30
        $this->assertEquals(15.00, $updatedBatch->getUnitCost());
    }

    public function testReturnInboundWithNewBatch(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('RETURN-NEW-BATCH-TEST-001');

        // 使用不存在的批次号，验证系统会创建新批次
        $nonExistentBatchNo = 'PUR-20240201-9999';

        // 准备退货入库数据
        $inboundData = [
            'return_order_no' => 'RT-2024-002',
            'operator' => 'return-operator',
            'notes' => '找不到原批次的退货',
            'items' => [
                [
                    'sku' => $sku,
                    'batch_no' => $nonExistentBatchNo,
                    'quantity' => 20,
                    'quality_level' => 'B',
                ],
            ],
        ];

        // 执行退货入库
        $inbound = $this->inboundService->returnInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('RT-2024-002', $inbound->getReferenceNo());
        $this->assertEquals('return-operator', $inbound->getOperator());
        $this->assertEquals('找不到原批次的退货', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals($nonExistentBatchNo, $item['batch_no']);
        $this->assertEquals(20, $item['quantity']);
        $this->assertEquals(0.0, $item['unit_cost']); // 新批次默认成本为 0
        $this->assertEquals(0.0, $item['amount']);
        $this->assertEquals('B', $item['quality_level']);

        // 验证系统创建了新批次
        $newBatch = $this->batchRepository->findOneBy(['batchNo' => $nonExistentBatchNo]);
        $this->assertNotNull($newBatch);
        $this->assertEquals($sku, $newBatch->getSku());
        $this->assertEquals(20, $newBatch->getQuantity());
        $this->assertEquals(20, $newBatch->getAvailableQuantity());
        $this->assertEquals(0.0, $newBatch->getUnitCost());
        $this->assertEquals('B', $newBatch->getQualityLevel());
        $this->assertEquals('available', $newBatch->getStatus());
    }

    public function testTransferInbound(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('TRANSFER-TEST-001');

        // 先创建源批次
        $sourceBatch = new StockBatch();
        $sourceBatch->setSku($sku);
        $sourceBatch->setBatchNo('PUR-20240301-0001');
        $sourceBatch->setQuantity(100);
        $sourceBatch->setAvailableQuantity(100);
        $sourceBatch->setUnitCost(12.50);
        $sourceBatch->setQualityLevel('A');
        $sourceBatch->setStatus('available');
        $sourceBatch->setLocationId('WAREHOUSE-A');

        self::getEntityManager()->persist($sourceBatch);
        self::getEntityManager()->flush();

        // 准备调拨入库数据
        $inboundData = [
            'transfer_no' => 'TRF-2024-001',
            'from_location' => 'WAREHOUSE-A',
            'location_id' => 'WAREHOUSE-B',
            'operator' => 'transfer-operator',
            'notes' => '仓库间调拨',
            'items' => [
                [
                    'batch_id' => $sourceBatch->getId(),
                    'quantity' => 60,
                ],
            ],
        ];

        // 执行调拨入库
        $inbound = $this->inboundService->transferInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('TRF-2024-001', $inbound->getReferenceNo());
        $this->assertEquals('transfer-operator', $inbound->getOperator());
        $this->assertEquals('WAREHOUSE-B', $inbound->getLocationId());
        $this->assertEquals('仓库间调拨', $inbound->getRemark());

        // 验证元数据包含源位置
        $metadata = $inbound->getMetadata();
        $this->assertNotNull($metadata);
        $this->assertEquals('WAREHOUSE-A', $metadata['from_location']);

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertIsInt($item['batch_id']);
        $this->assertEquals($sourceBatch->getId(), $item['batch_id']);
        $this->assertEquals('PUR-20240301-0001', $item['batch_no']);
        $this->assertEquals(60, $item['quantity']);
        $this->assertEquals(12.50, $item['unit_cost']);
        $this->assertEquals(750.0, $item['amount']);
        $this->assertEquals('A', $item['quality_level']);

        // 验证批次位置已更新
        self::getEntityManager()->clear();
        $updatedBatch = $this->batchRepository->find($sourceBatch->getId());
        $this->assertNotNull($updatedBatch);
        $this->assertEquals('WAREHOUSE-B', $updatedBatch->getLocationId());
    }

    public function testCreateOrUpdateBatchIntegration(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('BATCH-INTEGRATION-TEST-001');

        // 测试创建新批次
        $item1 = [
            'sku' => $sku,
            'batch_no' => 'TEST-BATCH-001',
            'quantity' => 100,
            'unit_cost' => 20.00,
            'quality_level' => 'A',
        ];

        $batch1 = $this->inboundService->createOrUpdateBatch($item1, 'purchase');
        self::getEntityManager()->flush(); // 确保数据保存

        // 验证新批次创建
        $this->assertNotNull($batch1->getId());
        $this->assertEquals($sku, $batch1->getSku());
        $this->assertEquals('TEST-BATCH-001', $batch1->getBatchNo());
        $this->assertEquals(100, $batch1->getQuantity());
        $this->assertEquals(100, $batch1->getAvailableQuantity());
        $this->assertEquals(20.00, $batch1->getUnitCost());
        $this->assertEquals('A', $batch1->getQualityLevel());
        $this->assertEquals('available', $batch1->getStatus());

        // 测试更新现有批次（相同批次号）
        $item2 = [
            'sku' => $sku,
            'batch_no' => 'TEST-BATCH-001', // 相同批次号
            'quantity' => 50,
            'unit_cost' => 25.00, // 不同成本
            'quality_level' => 'A',
        ];

        $batch2 = $this->inboundService->createOrUpdateBatch($item2, 'purchase');

        // 验证批次更新（加权平均成本计算）
        $this->assertEquals($batch1->getId(), $batch2->getId()); // 应该是同一个批次
        $this->assertEquals(150, $batch2->getQuantity()); // 100 + 50
        $this->assertEquals(150, $batch2->getAvailableQuantity()); // 100 + 50

        // 验证加权平均成本: (100 * 20.00 + 50 * 25.00) / 150 = 21.67
        $expectedCost = (100 * 20.00 + 50 * 25.00) / 150;
        $this->assertEqualsWithDelta($expectedCost, $batch2->getUnitCost(), 0.01);

        // 测试自动生成批次号
        $item3 = [
            'sku' => $sku,
            // 不提供批次号
            'quantity' => 30,
            'unit_cost' => 15.00,
            'quality_level' => 'B',
        ];

        $batch3 = $this->inboundService->createOrUpdateBatch($item3, 'production');
        self::getEntityManager()->flush(); // 确保数据保存

        // 验证自动生成的批次号
        $this->assertNotNull($batch3->getBatchNo());
        $this->assertStringStartsWith('PROD-', $batch3->getBatchNo());
        $this->assertEquals(30, $batch3->getQuantity());
        $this->assertEquals(15.00, $batch3->getUnitCost());
        $this->assertEquals('B', $batch3->getQualityLevel());

        // 验证是不同的批次
        $this->assertNotEquals($batch1->getId(), $batch3->getId());
    }

    public function testAdjustmentInbound(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('ADJUSTMENT-TEST-001');

        // 准备调整入库数据
        $inboundData = [
            'adjustment_no' => 'ADJ-2024-001',
            'operator' => 'adjustment-operator',
            'location_id' => 'WAREHOUSE-MAIN',
            'notes' => '盘点调整入库',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 120,
                    'unit_cost' => 18.50,
                    'quality_level' => 'A',
                    'reason' => '盘点发现盈余',
                ],
            ],
        ];

        // 执行调整入库
        $inbound = $this->inboundService->adjustmentInbound($inboundData);

        // 验证入库单数据一致性
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('ADJ-2024-001', $inbound->getReferenceNo());
        $this->assertEquals('adjustment-operator', $inbound->getOperator());
        $this->assertEquals('WAREHOUSE-MAIN', $inbound->getLocationId());
        $this->assertEquals('盘点调整入库', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals(120, $item['quantity']);
        $this->assertEquals(18.50, $item['unit_cost']);
        $this->assertEquals(2220.0, $item['amount']);
        $this->assertEquals('A', $item['quality_level']);
        $this->assertEquals('盘点发现盈余', $item['reason']);
        $this->assertNotEmpty($item['batch_no']);
        $this->assertNotEmpty($item['batch_id']);
        $this->assertIsInt($item['batch_id']);
        $batchNo = $item['batch_no'];
        $this->assertIsString($batchNo);
        $this->assertStringStartsWith('ADJ-', $batchNo);

        // 验证批次创建
        $batch = $this->batchRepository->find($item['batch_id']);
        $this->assertNotNull($batch);
        $this->assertEquals($sku, $batch->getSku());
        $this->assertEquals($item['batch_no'], $batch->getBatchNo());
        $this->assertEquals(120, $batch->getQuantity());
        $this->assertEquals(120, $batch->getAvailableQuantity());
        $this->assertEquals(18.50, $batch->getUnitCost());
        $this->assertEquals('A', $batch->getQualityLevel());
        $this->assertEquals('available', $batch->getStatus());
    }

    public function testAdjustmentInboundWithDefaultValues(): void
    {
        // 创建测试 SKU
        $sku = $this->createTestSku('ADJUSTMENT-DEFAULT-TEST-001');

        // 准备调整入库数据（使用默认值）
        $inboundData = [
            'adjustment_no' => 'ADJ-2024-002',
            'operator' => 'system',
            'notes' => '系统自动调整',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 80,
                    'reason' => '系统盘点调整',
                    // 未提供 unit_cost 和 quality_level，使用默认值
                ],
            ],
        ];

        // 执行调整入库
        $inbound = $this->inboundService->adjustmentInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('ADJ-2024-002', $inbound->getReferenceNo());
        $this->assertEquals('system', $inbound->getOperator());
        $this->assertEquals('系统自动调整', $inbound->getRemark());

        // 验证入库项目使用了默认值
        $items = $inbound->getItems();
        $this->assertCount(1, $items);

        $item = $items[0];
        $this->assertIsArray($item);
        $this->assertEquals($sku, $item['sku']);
        $this->assertEquals(80, $item['quantity']);
        $this->assertEquals(0.0, $item['unit_cost']); // 默认值
        $this->assertEquals(0.0, $item['amount']); // 80 * 0.0 = 0.0
        $this->assertEquals('A', $item['quality_level']); // 默认值
        $this->assertEquals('系统盘点调整', $item['reason']);
        $this->assertIsInt($item['batch_id']);

        // 验证批次也使用了默认值
        $batch = $this->batchRepository->find($item['batch_id']);
        $this->assertNotNull($batch);
        $this->assertEquals(80, $batch->getQuantity());
        $this->assertEquals(80, $batch->getAvailableQuantity());
        $this->assertEquals(0.0, $batch->getUnitCost());
        $this->assertEquals('A', $batch->getQualityLevel());
        $this->assertEquals('available', $batch->getStatus());
    }

    public function testAdjustmentInboundMultipleItems(): void
    {
        // 创建两个测试 SKU
        $sku1 = $this->createTestSku('ADJUSTMENT-MULTI-TEST-001');
        $sku2 = $this->createTestSku('ADJUSTMENT-MULTI-TEST-002');

        // 准备多物料调整入库数据
        $inboundData = [
            'adjustment_no' => 'ADJ-2024-003',
            'operator' => 'warehouse-manager',
            'location_id' => 'MAIN-WAREHOUSE',
            'notes' => '年度盘点调整',
            'items' => [
                [
                    'sku' => $sku1,
                    'quantity' => 150,
                    'unit_cost' => 22.00,
                    'quality_level' => 'A',
                    'reason' => '盘点盈余',
                ],
                [
                    'sku' => $sku2,
                    'quantity' => 90,
                    'unit_cost' => 35.50,
                    'quality_level' => 'B',
                    'reason' => '账面调整',
                ],
            ],
        ];

        // 执行调整入库
        $inbound = $this->inboundService->adjustmentInbound($inboundData);

        // 验证入库单数据
        $this->assertNotNull($inbound->getId());
        $this->assertEquals('ADJ-2024-003', $inbound->getReferenceNo());
        $this->assertEquals('warehouse-manager', $inbound->getOperator());
        $this->assertEquals('MAIN-WAREHOUSE', $inbound->getLocationId());
        $this->assertEquals('年度盘点调整', $inbound->getRemark());

        // 验证入库项目数据
        $items = $inbound->getItems();
        $this->assertCount(2, $items);

        // 验证第一个物料
        $item1 = $items[0];
        $this->assertIsArray($item1);
        $this->assertEquals($sku1, $item1['sku']);
        $this->assertEquals(150, $item1['quantity']);
        $this->assertEquals(22.00, $item1['unit_cost']);
        $this->assertEquals(3300.0, $item1['amount']);
        $this->assertEquals('A', $item1['quality_level']);
        $this->assertEquals('盘点盈余', $item1['reason']);

        // 验证第二个物料
        $item2 = $items[1];
        $this->assertIsArray($item2);
        $this->assertEquals($sku2, $item2['sku']);
        $this->assertEquals(90, $item2['quantity']);
        $this->assertEquals(35.50, $item2['unit_cost']);
        $this->assertEquals(3195.0, $item2['amount']);
        $this->assertEquals('B', $item2['quality_level']);
        $this->assertEquals('账面调整', $item2['reason']);

        // 验证两个批次都被创建
        $this->assertIsInt($item1['batch_id']);
        $this->assertIsInt($item2['batch_id']);
        $batch1 = $this->batchRepository->find($item1['batch_id']);
        $batch2 = $this->batchRepository->find($item2['batch_id']);

        $this->assertNotNull($batch1);
        $this->assertNotNull($batch2);
        $this->assertNotEquals($batch1->getId(), $batch2->getId());

        // 验证批次号都以ADJ-开头
        $this->assertStringStartsWith('ADJ-', $batch1->getBatchNo());
        $this->assertStringStartsWith('ADJ-', $batch2->getBatchNo());
        $this->assertNotEquals($batch1->getBatchNo(), $batch2->getBatchNo());

        // 验证批次数据
        $this->assertEquals($sku1, $batch1->getSku());
        $this->assertEquals(150, $batch1->getQuantity());
        $this->assertEquals(22.00, $batch1->getUnitCost());
        $this->assertEquals('A', $batch1->getQualityLevel());

        $this->assertEquals($sku2, $batch2->getSku());
        $this->assertEquals(90, $batch2->getQuantity());
        $this->assertEquals(35.50, $batch2->getUnitCost());
        $this->assertEquals('B', $batch2->getQualityLevel());
    }
}
