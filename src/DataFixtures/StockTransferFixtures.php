<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;

class StockTransferFixtures extends Fixture
{
    public const STOCK_TRANSFER_PENDING = 'stock_transfer_pending';
    public const STOCK_TRANSFER_IN_TRANSIT = 'stock_transfer_in_transit';
    public const STOCK_TRANSFER_RECEIVED = 'stock_transfer_received';

    public function load(ObjectManager $manager): void
    {
        // 待处理的调拨
        $pendingTransfer = new StockTransfer();
        $pendingTransfer->setTransferNo('TF2024001');
        $pendingTransfer->setFromLocation('WH-A-01');
        $pendingTransfer->setToLocation('WH-B-02');
        $pendingTransfer->setItems([
            [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 15,
                'unit_cost' => 50.00,
            ],
        ]);
        $pendingTransfer->setStatus(StockTransferStatus::PENDING);
        $pendingTransfer->setInitiator('warehouse_manager_001');
        $pendingTransfer->setReason('库存平衡调拨');
        $pendingTransfer->setMetadata([
            'priority' => 'normal',
            'expected_ship_date' => '2024-12-12',
        ]);

        $manager->persist($pendingTransfer);
        $this->addReference(self::STOCK_TRANSFER_PENDING, $pendingTransfer);

        // 运输中的调拨
        $inTransitTransfer = new StockTransfer();
        $inTransitTransfer->setTransferNo('TF2024002');
        $inTransitTransfer->setFromLocation('WH-B-02');
        $inTransitTransfer->setToLocation('WH-C-03');
        $inTransitTransfer->setItems([
            [
                'spu_id' => 'PRODUCT_002',
                'quantity' => 10,
                'unit_cost' => 120.00,
            ],
            [
                'spu_id' => 'PRODUCT_003',
                'quantity' => 20,
                'unit_cost' => 30.00,
            ],
        ]);
        $inTransitTransfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $inTransitTransfer->setInitiator('logistics_coordinator_001');
        $inTransitTransfer->setReceiver('warehouse_staff_002');
        $inTransitTransfer->setReason('补充目标仓库库存');
        $inTransitTransfer->setShippedTime(new \DateTimeImmutable('2024-12-09'));
        $inTransitTransfer->setMetadata([
            'carrier' => 'LOGISTICS_001',
            'tracking_number' => 'TRK123456789',
            'estimated_arrival' => '2024-12-11',
        ]);

        $manager->persist($inTransitTransfer);
        $this->addReference(self::STOCK_TRANSFER_IN_TRANSIT, $inTransitTransfer);

        // 已完成的调拨
        $receivedTransfer = new StockTransfer();
        $receivedTransfer->setTransferNo('TF2024003');
        $receivedTransfer->setFromLocation('WH-C-03');
        $receivedTransfer->setToLocation('WH-A-01');
        $receivedTransfer->setItems([
            [
                'spu_id' => 'PRODUCT_001',
                'quantity' => 20,
                'unit_cost' => 50.00,
            ],
        ]);
        $receivedTransfer->setStatus(StockTransferStatus::RECEIVED);
        $receivedTransfer->setInitiator('warehouse_manager_002');
        $receivedTransfer->setReceiver('warehouse_staff_001');
        $receivedTransfer->setReason('紧急调拨支援');
        $receivedTransfer->setShippedTime(new \DateTimeImmutable('2024-12-08'));
        $receivedTransfer->setReceivedTime(new \DateTimeImmutable('2024-12-09'));
        $receivedTransfer->setMetadata([
            'transfer_type' => 'emergency',
            'approval_required' => true,
            'approved_by' => 'regional_manager',
        ]);

        $manager->persist($receivedTransfer);
        $this->addReference(self::STOCK_TRANSFER_RECEIVED, $receivedTransfer);

        $manager->flush();
    }
}
