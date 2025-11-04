<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InsufficientStockException;
use Tourze\StockManageBundle\Exception\InvalidOperationException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\AllocationService;
use Tourze\StockManageBundle\Service\OutboundProcessor;

/**
 * @internal
 */
#[CoversClass(OutboundProcessor::class)]
final class OutboundProcessorTest extends TestCase
{
    private OutboundProcessor $outboundProcessor;

    private EntityManagerInterface&MockObject $entityManager;

    private StockBatchRepository&MockObject $batchRepository;

    private AllocationService&MockObject $allocationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->batchRepository = $this->createMock(StockBatchRepository::class);
        $this->allocationService = $this->createMock(AllocationService::class);

        $this->outboundProcessor = new OutboundProcessor(
            $this->entityManager,
            $this->batchRepository,
            $this->allocationService
        );
    }

    private function createMockSku(string $skuId): SKU&MockObject
    {
        $sku = $this->createMock(SKU::class);
        self::assertInstanceOf(SKU::class, $sku);
        $sku->method('getId')->willReturn($skuId);

        return $sku;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStockBatch(array $data): StockBatch
    {
        /** @var SKU $sku */
        $sku = $data['sku'] ?? $this->createMockSku('SKU001');

        $batch = new StockBatch();

        // 设置必填属性
        $batch->setSku($sku);
        $batch->setBatchNo((string) ($data['batch_no'] ?? 'BATCH001'));
        $batch->setQuantity((int) ($data['quantity'] ?? 100));
        $batch->setUnitCost((float) ($data['unit_cost'] ?? 10.50));
        $batch->setQualityLevel((string) ($data['quality_level'] ?? 'A'));
        $batch->setLocationId((string) ($data['location_id'] ?? 'WH001'));

        // 使用反射设置 ID（因为 ID 通常由 Doctrine 管理）
        if (isset($data['id'])) {
            $reflection = new \ReflectionClass($batch);
            $idProperty = $reflection->getProperty('id');
            $idProperty->setValue($batch, $data['id']);
        }

        // 设置可用数量（默认等于总数量）
        $batch->setAvailableQuantity((int) ($data['available_quantity'] ?? $data['quantity'] ?? 100));

        // 设置其他可选属性
        if (isset($data['status'])) {
            $batch->setStatus((string) $data['status']);
        }
        // 注意：spuId 是通过 SKU 关联获取的，不需要单独设置

        return $batch;
    }

    public function testProcessSalesOutbound(): void
    {
        $sku1 = $this->createMockSku('SKU001');
        $sku2 = $this->createMockSku('SKU002');

        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'location_id' => 'WH001',
            'items' => [
                [
                    'sku' => $sku1,
                    'quantity' => 100,
                    'allocation_strategy' => 'fifo',
                ],
                [
                    'sku' => $sku2,
                    'quantity' => 50,
                ],
            ],
        ];

        // Mock allocation results
        $allocation1 = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 100,
                    'unitCost' => 10.50,
                ],
            ],
        ];

        $allocation2 = [
            'batches' => [
                [
                    'batchId' => 2,
                    'batchNo' => 'BATCH002',
                    'quantity' => 50,
                    'unitCost' => 12.00,
                ],
            ],
        ];

        $batch1 = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku1,
            'quantity' => 100,
            'unit_cost' => 10.50,
        ]);

        $batch2 = $this->createStockBatch([
            'id' => 2,
            'batch_no' => 'BATCH002',
            'sku' => $sku2,
            'quantity' => 50,
            'unit_cost' => 12.00,
        ]);

        $callCount = 0;
        $this->allocationService->expects($this->exactly(2))
            ->method('calculateAllocation')
            ->willReturnCallback(function ($sku, $quantity, $strategy, $filter) use ($sku1, $sku2, $allocation1, $allocation2, &$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals($sku1, $sku);
                    $this->assertEquals(100, $quantity);
                    $this->assertEquals('fifo', $strategy);
                    $this->assertEquals(['location_id' => 'WH001'], $filter);

                    return $allocation1;
                }
                $this->assertEquals($sku2, $sku);
                $this->assertEquals(50, $quantity);
                $this->assertEquals('fifo', $strategy);
                $this->assertEquals(['location_id' => 'WH001'], $filter);

                return $allocation2;
            })
        ;

        $this->batchRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function ($id) use ($batch1, $batch2) {
                return match ($id) {
                    1 => $batch1,
                    2 => $batch2,
                    default => null,
                };
            })
        ;

        // 真实实体会在服务中被修改，验证在测试结果中进行

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processSalesOutbound($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('requestedItems', $result);
        $this->assertArrayHasKey('allocatedItems', $result);
        $this->assertArrayHasKey('totalQuantity', $result);
        $this->assertArrayHasKey('totalCost', $result);

        $this->assertCount(2, $result['requestedItems']);
        $this->assertCount(2, $result['allocatedItems']);
        $this->assertEquals(150, $result['totalQuantity']); // 100 + 50
        $this->assertEquals(1650.0, $result['totalCost']); // 100*10.50 + 50*12.00
    }

    public function testProcessSalesOutboundWithInvalidBatch(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 999, // Non-existent batch ID
                    'batchNo' => 'BATCH999',
                    'quantity' => 100,
                    'unitCost' => 10.50,
                ],
            ],
        ];

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不存在: 999');

        $this->outboundProcessor->processSalesOutbound($data);
    }

    public function testProcessDamageOutbound(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
                [
                    'batch_id' => 2,
                    'quantity' => 20,
                    'reason' => '运输损坏',
                ],
            ],
        ];

        $batch1 = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 10.50,
        ]);

        $batch2 = $this->createStockBatch([
            'id' => 2,
            'batch_no' => 'BATCH002',
            'quantity' => 50,
            'available_quantity' => 40,
            'unit_cost' => 12.00,
        ]);

        $this->batchRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function ($id) use ($batch1, $batch2) {
                return match ($id) {
                    1 => $batch1,
                    2 => $batch2,
                    default => null,
                };
            })
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processDamageOutbound($data);

        $this->assertIsArray($result);
        $this->assertCount(2, $result['requestedItems']);
        $this->assertCount(2, $result['allocatedItems']);
        $this->assertEquals(50, $result['totalQuantity']); // 30 + 20
        $this->assertEquals(555.0, $result['totalCost']); // 30*10.50 + 20*12.00
    }

    public function testProcessDamageOutboundWithInsufficientStock(): void
    {
        $data = [
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 100, // More than available
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'quantity' => 100,
            'available_quantity' => 50, // Less than requested
            'spu_id' => 'SPU001',
        ]);

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        $this->expectException(InsufficientStockException::class);

        $this->outboundProcessor->processDamageOutbound($data);
    }

    public function testProcessTransferOutbound(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 60,
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 10.50,
        ]);

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processTransferOutbound($data);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['requestedItems']);
        $this->assertCount(1, $result['allocatedItems']);
        $this->assertEquals(60, $result['totalQuantity']);
        $this->assertEquals(630.0, $result['totalCost']); // 60*10.50
    }

    public function testProcessPickOutbound(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 40,
                    'purpose' => '办公用品',
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 40,
                    'unitCost' => 10.50,
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 10.50,
        ]);

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with($sku, 40, 'fifo', [])
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processPickOutbound($data);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['requestedItems']);
        $this->assertCount(1, $result['allocatedItems']);
        $this->assertEquals(40, $result['totalQuantity']);
        $this->assertEquals(420.0, $result['totalCost']); // 40*10.50

        // 验证请求项包含部门信息
        $this->assertIsArray($result['requestedItems'][0]);
        /** @var array<string, mixed> $requestedItem */
        $requestedItem = $result['requestedItems'][0];
        $this->assertArrayHasKey('department', $requestedItem);
        $this->assertArrayHasKey('purpose', $requestedItem);
        $this->assertEquals('IT部门', $requestedItem['department']);
        $this->assertEquals('办公用品', $requestedItem['purpose']);

        // 验证分配项包含部门信息
        $this->assertIsArray($result['allocatedItems'][0]);
        /** @var array<string, mixed> $allocatedItem */
        $allocatedItem = $result['allocatedItems'][0];
        $this->assertArrayHasKey('department', $allocatedItem);
        $this->assertArrayHasKey('purpose', $allocatedItem);
        $this->assertEquals('IT部门', $allocatedItem['department']);
        $this->assertEquals('办公用品', $allocatedItem['purpose']);
    }

    public function testProcessPickOutboundWithLocation(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'operator' => 'user1',
            'location_id' => 'WH001',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 40,
                    'purpose' => '办公用品',
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 40,
                    'unitCost' => 10.50,
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 10.50,
        ]);

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with($sku, 40, 'fifo', ['location_id' => 'WH001'])
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        $result = $this->outboundProcessor->processPickOutbound($data);

        $this->assertIsArray($result);
        $this->assertEquals(40, $result['totalQuantity']);
    }

    public function testProcessTransferOutboundWithInvalidBatch(): void
    {
        $data = [
            'items' => [
                [
                    'batch_id' => 999, // Non-existent batch ID
                    'quantity' => 60,
                ],
            ],
        ];

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不存在: 999');

        $this->outboundProcessor->processTransferOutbound($data);
    }

    public function testProcessDamageOutboundWithInvalidBatch(): void
    {
        $data = [
            'items' => [
                [
                    'batch_id' => 999, // Non-existent batch ID
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不存在: 999');

        $this->outboundProcessor->processDamageOutbound($data);
    }

    public function testProcessTransferOutboundWithInsufficientStock(): void
    {
        $data = [
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 100, // More than available
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'quantity' => 100,
            'available_quantity' => 50, // Less than requested
            'spu_id' => 'SPU001',
        ]);

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        $this->expectException(InsufficientStockException::class);

        $this->outboundProcessor->processTransferOutbound($data);
    }

    public function testProcessPickOutboundWithInvalidBatch(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'department' => 'IT部门',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 40,
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 999, // Non-existent batch ID
                    'batchNo' => 'BATCH999',
                    'quantity' => 40,
                    'unitCost' => 10.50,
                ],
            ],
        ];

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不存在: 999');

        $this->outboundProcessor->processPickOutbound($data);
    }

    public function testProcessAdjustmentOutbound(): void
    {
        $sku1 = $this->createMockSku('SKU001');
        $sku2 = $this->createMockSku('SKU002');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'location_id' => 'WH001',
            'items' => [
                [
                    'sku' => $sku1,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
                [
                    'sku' => $sku2,
                    'quantity' => 15,
                    'reason' => '损坏报废',
                ],
            ],
        ];

        // Mock allocation results for both SKUs
        $allocation1 = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 25,
                    'unitCost' => 18.50,
                ],
            ],
        ];

        $allocation2 = [
            'batches' => [
                [
                    'batchId' => 2,
                    'batchNo' => 'BATCH002',
                    'quantity' => 15,
                    'unitCost' => 22.00,
                ],
            ],
        ];

        $batch1 = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku1,
            'quantity' => 100,
            'available_quantity' => 80,
            'unit_cost' => 18.50,
        ]);

        $batch2 = $this->createStockBatch([
            'id' => 2,
            'batch_no' => 'BATCH002',
            'sku' => $sku2,
            'quantity' => 50,
            'available_quantity' => 30,
            'unit_cost' => 22.00,
        ]);

        $callCount = 0;
        $this->allocationService->expects($this->exactly(2))
            ->method('calculateAllocation')
            ->willReturnCallback(function ($sku, $quantity, $strategy, $filter) use ($sku1, $sku2, $allocation1, $allocation2, &$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals($sku1, $sku);
                    $this->assertEquals(25, $quantity);
                    $this->assertEquals('fifo', $strategy);
                    $this->assertEquals(['location_id' => 'WH001'], $filter);

                    return $allocation1;
                }
                $this->assertEquals($sku2, $sku);
                $this->assertEquals(15, $quantity);
                $this->assertEquals('fifo', $strategy);
                $this->assertEquals(['location_id' => 'WH001'], $filter);

                return $allocation2;
            })
        ;

        $this->batchRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function ($id) use ($batch1, $batch2) {
                return match ($id) {
                    1 => $batch1,
                    2 => $batch2,
                    default => null,
                };
            })
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processAdjustmentOutbound($data);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('requestedItems', $result);
        $this->assertArrayHasKey('allocatedItems', $result);
        $this->assertArrayHasKey('totalQuantity', $result);
        $this->assertArrayHasKey('totalCost', $result);

        $this->assertCount(2, $result['requestedItems']);
        $this->assertCount(2, $result['allocatedItems']);
        $this->assertEquals(40, $result['totalQuantity']); // 25 + 15
        $this->assertEquals(792.5, $result['totalCost']); // 25*18.50 + 15*22.00

        // 验证请求项包含调整原因
        $this->assertIsArray($result['requestedItems']);
        /** @var array<array<string, mixed>> $requestedItems */
        $requestedItems = $result['requestedItems'];
        $this->assertIsArray($requestedItems[0]);
        $this->assertIsArray($requestedItems[1]);
        $this->assertEquals($sku1, $requestedItems[0]['sku']);
        $this->assertEquals(25, $requestedItems[0]['quantity']);
        $this->assertEquals('盘点亏损', $requestedItems[0]['reason']);

        $this->assertEquals($sku2, $requestedItems[1]['sku']);
        $this->assertEquals(15, $requestedItems[1]['quantity']);
        $this->assertEquals('损坏报废', $requestedItems[1]['reason']);

        // 验证分配项包含调整原因
        $this->assertIsArray($result['allocatedItems']);
        /** @var array<array<string, mixed>> $allocatedItems */
        $allocatedItems = $result['allocatedItems'];
        $this->assertIsArray($allocatedItems[0]);
        $this->assertIsArray($allocatedItems[1]);
        $this->assertEquals('盘点亏损', $allocatedItems[0]['reason']);
        $this->assertEquals('损坏报废', $allocatedItems[1]['reason']);
    }

    public function testProcessAdjustmentOutboundSingleItem(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-002',
            'operator' => 'warehouse-manager',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 50,
                    'reason' => '系统调整',
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 30,
                    'unitCost' => 15.00,
                ],
                [
                    'batchId' => 2,
                    'batchNo' => 'BATCH002',
                    'quantity' => 20,
                    'unitCost' => 20.00,
                ],
            ],
        ];

        $batch1 = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'available_quantity' => 60,
            'unit_cost' => 15.00,
        ]);

        $batch2 = $this->createStockBatch([
            'id' => 2,
            'batch_no' => 'BATCH002',
            'sku' => $sku,
            'available_quantity' => 40,
            'unit_cost' => 20.00,
        ]);

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with($sku, 50, 'fifo', [])
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function ($id) use ($batch1, $batch2) {
                return match ($id) {
                    1 => $batch1,
                    2 => $batch2,
                    default => null,
                };
            })
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->exactly(2))
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processAdjustmentOutbound($data);

        $this->assertIsArray($result);
        $this->assertCount(1, $result['requestedItems']);
        $this->assertCount(2, $result['allocatedItems']); // 跨两个批次分配
        $this->assertEquals(50, $result['totalQuantity']);
        $this->assertEquals(850.0, $result['totalCost']); // 30*15.00 + 20*20.00

        // 验证请求项
        $this->assertIsArray($result['requestedItems'][0]);
        /** @var array<string, mixed> $requestedItem */
        $requestedItem = $result['requestedItems'][0];
        $this->assertEquals($sku, $requestedItem['sku']);
        $this->assertEquals(50, $requestedItem['quantity']);
        $this->assertEquals('系统调整', $requestedItem['reason']);

        // 验证分配项都包含调整原因
        $this->assertIsArray($result['allocatedItems']);
        /** @var array<array<string, mixed>> $allocatedItems */
        $allocatedItems = $result['allocatedItems'];
        foreach ($allocatedItems as $allocatedItem) {
            $this->assertIsArray($allocatedItem);
            $this->assertEquals('系统调整', $allocatedItem['reason']);
        }
    }

    public function testProcessAdjustmentOutboundWithInvalidBatch(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-003',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 30,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 999, // Non-existent batch ID
                    'batchNo' => 'BATCH999',
                    'quantity' => 30,
                    'unitCost' => 15.00,
                ],
            ],
        ];

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null)
        ;

        $this->expectException(InvalidOperationException::class);
        $this->expectExceptionMessage('批次不存在: 999');

        $this->outboundProcessor->processAdjustmentOutbound($data);
    }

    public function testProcessAdjustmentOutboundWithLocationFilter(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-004',
            'operator' => 'warehouse-manager',
            'location_id' => 'WH002',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 35,
                    'reason' => '位置调整',
                ],
            ],
        ];

        $allocation = [
            'batches' => [
                [
                    'batchId' => 1,
                    'batchNo' => 'BATCH001',
                    'quantity' => 35,
                    'unitCost' => 25.00,
                ],
            ],
        ];

        $batch = $this->createStockBatch([
            'id' => 1,
            'batch_no' => 'BATCH001',
            'sku' => $sku,
            'available_quantity' => 50,
            'unit_cost' => 25.00,
            'location_id' => 'WH002',
        ]);

        $this->allocationService->expects($this->once())
            ->method('calculateAllocation')
            ->with($sku, 35, 'fifo', ['location_id' => 'WH002'])
            ->willReturn($allocation)
        ;

        $this->batchRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($batch)
        ;

        // 真实实体会在服务中被修改

        $this->entityManager->expects($this->once())
            ->method('flush')
        ;

        $result = $this->outboundProcessor->processAdjustmentOutbound($data);

        $this->assertIsArray($result);
        $this->assertEquals(35, $result['totalQuantity']);
        $this->assertEquals(875.0, $result['totalCost']); // 35*25.00
        $this->assertIsArray($result['requestedItems'][0]);
        $this->assertIsArray($result['allocatedItems'][0]);
        /** @var array<string, mixed> $requestedItem */
        $requestedItem = $result['requestedItems'][0];
        /** @var array<string, mixed> $allocatedItem */
        $allocatedItem = $result['allocatedItems'][0];
        $this->assertEquals('位置调整', $requestedItem['reason']);
        $this->assertEquals('位置调整', $allocatedItem['reason']);
    }
}
