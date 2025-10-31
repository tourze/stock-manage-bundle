<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;

class StockInboundFixtures extends Fixture
{
    public const STOCK_INBOUND_PURCHASE = 'stock_inbound_purchase';
    public const STOCK_INBOUND_RETURN = 'stock_inbound_return';
    public const STOCK_INBOUND_TRANSFER = 'stock_inbound_transfer';

    public function load(ObjectManager $manager): void
    {
        // 采购入库
        $purchaseInbound = new StockInbound();
        $purchaseInbound->setType(StockInboundType::PURCHASE);
        $purchaseInbound->setReferenceNo('PO2024001');
        $purchaseInbound->setItems([
            [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 100,
                'unit_cost' => 50.00,
                'batch_no' => 'BATCH2024001',
            ],
            [
                'spu_id' => 'PRODUCT_002',
                'quantity' => 50,
                'unit_cost' => 120.00,
                'batch_no' => 'BATCH2024002',
            ],
        ]);
        $purchaseInbound->setOperator('purchaser_001');
        $purchaseInbound->setLocationId('WH-A-01');
        $purchaseInbound->setRemark('常规采购入库');
        $purchaseInbound->setMetadata([
            'supplier' => 'SUPPLIER_001',
            'purchase_order' => 'PO2024001',
            'invoice_number' => 'INV2024001',
        ]);

        $manager->persist($purchaseInbound);
        $this->addReference(self::STOCK_INBOUND_PURCHASE, $purchaseInbound);

        // 退货入库
        $returnInbound = new StockInbound();
        $returnInbound->setType(StockInboundType::RETURN);
        $returnInbound->setReferenceNo('RETURN2024001');
        $returnInbound->setItems([
            [
                'spu_id' => 'PRODUCT_003',
                'quantity' => 10,
                'unit_cost' => 30.00,
                'batch_no' => 'BATCH2024003',
                'reason' => '客户退货，商品完好',
            ],
        ]);
        $returnInbound->setOperator('customer_service_001');
        $returnInbound->setLocationId('WH-B-02');
        $returnInbound->setRemark('客户退货重新入库');
        $returnInbound->setMetadata([
            'customer_id' => 'CUSTOMER_001',
            'order_number' => 'ORDER2024001',
            'return_reason' => '商品质量问题',
        ]);

        $manager->persist($returnInbound);
        $this->addReference(self::STOCK_INBOUND_RETURN, $returnInbound);

        // 调拨入库
        $transferInbound = new StockInbound();
        $transferInbound->setType(StockInboundType::TRANSFER);
        $transferInbound->setReferenceNo('TRANSFER2024001');
        $transferInbound->setItems([
            [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 20,
                'unit_cost' => 50.00,
                'batch_no' => 'BATCH2024001',
            ],
        ]);
        $transferInbound->setOperator('warehouse_staff_001');
        $transferInbound->setLocationId('WH-C-03');
        $transferInbound->setRemark('从WH-A-01调拨入库');
        $transferInbound->setMetadata([
            'from_location' => 'WH-A-01',
            'transfer_order' => 'TO2024001',
            'transfer_reason' => '库存调配',
        ]);

        $manager->persist($transferInbound);
        $this->addReference(self::STOCK_INBOUND_TRANSFER, $transferInbound);

        $manager->flush();
    }
}
