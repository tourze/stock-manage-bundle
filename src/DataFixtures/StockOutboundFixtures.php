<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockOutbound;
use Tourze\StockManageBundle\Enum\StockOutboundType;

class StockOutboundFixtures extends Fixture
{
    public const STOCK_OUTBOUND_SALE = 'stock_outbound_sale';
    public const STOCK_OUTBOUND_TRANSFER = 'stock_outbound_transfer';
    public const STOCK_OUTBOUND_DAMAGE = 'stock_outbound_damage';

    public function load(ObjectManager $manager): void
    {
        // 销售出库
        $saleOutbound = new StockOutbound();
        $saleOutbound->setType(StockOutboundType::SALES);
        $saleOutbound->setReferenceNo('ORDER2024001');
        $saleOutbound->setItems([
            'PRODUCT_001' => [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 5,
                'unit_price' => 80.00,
            ],
            'PRODUCT_002' => [
                'spu_id' => 'PRODUCT_002',
                'quantity' => 2,
                'unit_price' => 150.00,
            ],
        ]);
        $saleOutbound->setAllocations([
            'BATCH2024001' => [
                'batch_id' => 'BATCH2024001',
                'spu_id' => 'PRODUCT_001',
                'quantity' => 5,
                'unit_cost' => 50.00,
            ],
            'BATCH2024002' => [
                'batch_id' => 'BATCH2024002',
                'spu_id' => 'PRODUCT_002',
                'quantity' => 2,
                'unit_cost' => 120.00,
            ],
        ]);
        $saleOutbound->setOperator('sales_staff_001');
        $saleOutbound->setLocationId('WH-A-01');
        $saleOutbound->setRemark('客户订单出库');
        $saleOutbound->setMetadata([
            'customer_id' => 'CUSTOMER_001',
            'order_number' => 'ORDER2024001',
            'sales_channel' => 'online',
        ]);

        $manager->persist($saleOutbound);
        $this->addReference(self::STOCK_OUTBOUND_SALE, $saleOutbound);

        // 调拨出库
        $transferOutbound = new StockOutbound();
        $transferOutbound->setType(StockOutboundType::TRANSFER);
        $transferOutbound->setReferenceNo('TRANSFER2024002');
        $transferOutbound->setItems([
            'PRODUCT_001' => [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 20,
                'unit_price' => 0.00,
            ],
        ]);
        $transferOutbound->setAllocations([
            'BATCH2024001' => [
                'batch_id' => 'BATCH2024001',
                'spu_id' => 'PRODUCT_001',
                'quantity' => 20,
                'unit_cost' => 50.00,
            ],
        ]);
        $transferOutbound->setOperator('warehouse_staff_001');
        $transferOutbound->setLocationId('WH-A-01');
        $transferOutbound->setRemark('调拨到WH-C-03');
        $transferOutbound->setMetadata([
            'to_location' => 'WH-C-03',
            'transfer_order' => 'TO2024002',
        ]);

        $manager->persist($transferOutbound);
        $this->addReference(self::STOCK_OUTBOUND_TRANSFER, $transferOutbound);

        // 报损出库
        $damageOutbound = new StockOutbound();
        $damageOutbound->setType(StockOutboundType::DAMAGE);
        $damageOutbound->setReferenceNo('DAMAGE2024001');
        $damageOutbound->setItems([
            'PRODUCT_003' => [
                'spu_id' => 'PRODUCT_003',
                'quantity' => 3,
                'unit_price' => 0.00,
                'reason' => '包装破损，商品损坏',
            ],
        ]);
        $damageOutbound->setAllocations([
            'BATCH2024003' => [
                'batch_id' => 'BATCH2024003',
                'spu_id' => 'PRODUCT_003',
                'quantity' => 3,
                'unit_cost' => 30.00,
            ],
        ]);
        $damageOutbound->setOperator('warehouse_staff_001');
        $damageOutbound->setLocationId('WH-B-02');
        $damageOutbound->setRemark('商品损坏报损');
        $damageOutbound->setMetadata([
            'damage_reason' => '包装破损',
            'handling_method' => 'destroy',
            'approver' => 'manager_001',
        ]);

        $manager->persist($damageOutbound);
        $this->addReference(self::STOCK_OUTBOUND_DAMAGE, $damageOutbound);

        $manager->flush();
    }
}
