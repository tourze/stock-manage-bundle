<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Service\OutboundValidator;

/**
 * @internal
 */
#[CoversClass(OutboundValidator::class)]
final class OutboundValidatorTest extends TestCase
{
    private OutboundValidator $outboundValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outboundValidator = new OutboundValidator();
    }

    private function createMockSku(string $skuId): SKU
    {
        $sku = $this->createMock(SKU::class);
        $sku->method('getId')->willReturn($skuId);

        return $sku;
    }

    public function testValidateSalesOutboundDataSuccess(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                ],
                [
                    'sku' => $sku,
                    'quantity' => 50,
                ],
            ],
        ];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataMissingOrderNo(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('销售单号不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataEmptyOrderNo(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => '',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('销售单号不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataMissingOperator(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => 'ORDER001',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 100,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('操作员不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataMissingItems(): void
    {
        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库明细不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataEmptyItems(): void
    {
        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库明细不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataInvalidItems(): void
    {
        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => 'not_an_array',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库明细不能为空');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataItemMissingSku(): void
    {
        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [
                [
                    'quantity' => 100,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库明细必须包含SKU');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataItemMissingQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库数量必须是大于0的整数');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateSalesOutboundDataItemInvalidQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 0,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库数量必须是大于0的整数');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidateDamageOutboundDataSuccess(): void
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

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateDamageOutboundDataMissingDamageNo(): void
    {
        $data = [
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('损耗单号不能为空');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateDamageOutboundDataItemMissingBatchId(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID必须是大于0的整数');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateDamageOutboundDataItemInvalidBatchId(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 0,
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID必须是大于0的整数');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateDamageOutboundDataItemMissingReason(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 30,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('损耗原因不能为空');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateTransferOutboundDataSuccess(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 60,
                ],
                [
                    'batch_id' => '2',
                    'quantity' => 40,
                ],
            ],
        ];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateTransferOutboundDataMissingTransferNo(): void
    {
        $data = [
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 60,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调拨单号不能为空');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateTransferOutboundDataMissingToLocation(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 60,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('目标位置不能为空');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateTransferOutboundDataItemMissingBatchId(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'quantity' => 60,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID不能为空');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateTransferOutboundDataItemEmptyBatchId(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => '',
                    'quantity' => 60,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID不能为空');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidatePickOutboundDataSuccess(): void
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
                ],
            ],
        ];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidatePickOutboundDataMissingPickNo(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'department' => 'IT部门',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 40,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('领用单号不能为空');

        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidatePickOutboundDataMissingDepartment(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'pick_no' => 'PICK001',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 40,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('部门不能为空');

        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidatePickOutboundDataItemMissingSku(): void
    {
        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'operator' => 'user1',
            'items' => [
                [
                    'quantity' => 40,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('领用明细必须包含SKU');

        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidatePickOutboundDataItemInvalidQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => -5,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('领用数量必须是大于0的整数');

        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidateTransferOutboundDataItemInvalidQuantity(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => '1',
                    'quantity' => 'invalid',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调拨数量必须是大于0的整数');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateDamageOutboundDataItemInvalidQuantityType(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 1,
                    'quantity' => 'invalid',
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('损耗数量必须是大于0的整数');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateSalesOutboundDataItemInvalidQuantityType(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'order_no' => 'ORDER001',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 'invalid',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('出库数量必须是大于0的整数');

        $this->outboundValidator->validateSalesOutboundData($data);
    }

    public function testValidatePickOutboundDataItemInvalidQuantityType(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'pick_no' => 'PICK001',
            'department' => 'IT部门',
            'operator' => 'user1',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 'invalid',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('领用数量必须是大于0的整数');

        $this->outboundValidator->validatePickOutboundData($data);
    }

    public function testValidateDamageOutboundDataItemInvalidBatchIdType(): void
    {
        $data = [
            'damage_no' => 'DMG001',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 'invalid',
                    'quantity' => 30,
                    'reason' => '过期损耗',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID必须是大于0的整数');

        $this->outboundValidator->validateDamageOutboundData($data);
    }

    public function testValidateTransferOutboundDataItemInvalidBatchIdType(): void
    {
        $data = [
            'transfer_no' => 'TRF001',
            'to_location' => 'WH002',
            'operator' => 'user1',
            'items' => [
                [
                    'batch_id' => 123, // Should be string according to the validation
                    'quantity' => 60,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('批次ID不能为空');

        $this->outboundValidator->validateTransferOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataSuccess(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
                [
                    'sku' => $sku,
                    'quantity' => 15,
                    'reason' => '损坏报废',
                ],
            ],
        ];

        // 不应该抛出异常
        $this->expectNotToPerformAssertions();
        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataMissingAdjustmentNo(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整单号不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataEmptyAdjustmentNo(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => '',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整单号不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataMissingOperator(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('操作员不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataEmptyOperator(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => '',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('操作员不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataMissingItems(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整明细不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataEmptyItems(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整明细不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataInvalidItems(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => 'not_an_array',
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整明细不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemMissingSku(): void
    {
        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整明细必须包含SKU');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemMissingQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整数量必须是大于0的整数');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemInvalidQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 0,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整数量必须是大于0的整数');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemInvalidQuantityType(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 'invalid',
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整数量必须是大于0的整数');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemNegativeQuantity(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => -10,
                    'reason' => '盘点亏损',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整数量必须是大于0的整数');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemMissingReason(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整原因不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataItemEmptyReason(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整原因不能为空');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }

    public function testValidateAdjustmentOutboundDataMultipleItemsWithMixedErrors(): void
    {
        $sku = $this->createMockSku('SKU001');

        $data = [
            'adjustment_no' => 'ADJ-OUT-001',
            'operator' => 'adjustment-operator',
            'items' => [
                [
                    'sku' => $sku,
                    'quantity' => 25,
                    'reason' => '盘点亏损',
                ],
                [
                    'sku' => $sku,
                    'quantity' => 0, // Invalid quantity
                    'reason' => '损坏报废',
                ],
            ],
        ];

        // 验证器应该在第一个无效项目处停止
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('调整数量必须是大于0的整数');

        $this->outboundValidator->validateAdjustmentOutboundData($data);
    }
}
