<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\ProductServiceContracts\SpuLoaderInterface;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;

class StockReservationFixtures extends Fixture
{
    public const STOCK_RESERVATION_ORDER = 'stock_reservation_order';
    public const STOCK_RESERVATION_PRODUCTION = 'stock_reservation_production';
    public const STOCK_RESERVATION_TRANSFER = 'stock_reservation_transfer';

    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly SpuLoaderInterface $spuLoader,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 订单预定
        $orderReservation = new StockReservation();
        $spu1 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-RESERVE-001', title: '订单预定商品');
        $sku1 = $this->skuLoader->createSku($spu1, gtin: 'SKU-RESERVE-001');
        $orderReservation->setSku($sku1);
        $orderReservation->setQuantity(10);
        $orderReservation->setType(StockReservationType::ORDER);
        $orderReservation->setBusinessId('ORDER2024002');
        $orderReservation->setStatus(StockReservationStatus::PENDING);
        $orderReservation->setExpiresTime(new \DateTimeImmutable('+3 days'));
        $orderReservation->setBatchAllocations([
            'BATCH2024001' => 10,
        ]);
        $orderReservation->setOperator('order_system');
        $orderReservation->setNotes('客户订单预留库存');

        $manager->persist($orderReservation);
        $this->addReference(self::STOCK_RESERVATION_ORDER, $orderReservation);

        // 生产预定
        $productionReservation = new StockReservation();
        $spu2 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-RESERVE-002', title: '生产预定商品');
        $sku2 = $this->skuLoader->createSku($spu2, gtin: 'SKU-RESERVE-002');
        $productionReservation->setSku($sku2);
        $productionReservation->setQuantity(25);
        $productionReservation->setType(StockReservationType::SYSTEM);
        $productionReservation->setBusinessId('PRODUCTION2024001');
        $productionReservation->setStatus(StockReservationStatus::CONFIRMED);
        $productionReservation->setExpiresTime(new \DateTimeImmutable('+7 days'));
        $productionReservation->setBatchAllocations([
            'BATCH2024002' => 25,
        ]);
        $productionReservation->setOperator('production_planner');
        $productionReservation->setConfirmedTime(new \DateTimeImmutable());
        $productionReservation->setNotes('生产线预定原材料');

        $manager->persist($productionReservation);
        $this->addReference(self::STOCK_RESERVATION_PRODUCTION, $productionReservation);

        // 调拨预定
        $transferReservation = new StockReservation();
        $spu3 = $this->spuLoader->loadOrCreateSpu(gtin: 'SPU-RESERVE-003', title: '调拨预定商品');
        $sku3 = $this->skuLoader->createSku($spu3, gtin: 'SKU-RESERVE-003');
        $transferReservation->setSku($sku3);
        $transferReservation->setQuantity(15);
        $transferReservation->setType(StockReservationType::SYSTEM);
        $transferReservation->setBusinessId('TRANSFER2024003');
        $transferReservation->setStatus(StockReservationStatus::PENDING);
        $transferReservation->setExpiresTime(new \DateTimeImmutable('+2 days'));
        $transferReservation->setBatchAllocations([
            'BATCH2024003' => 15,
        ]);
        $transferReservation->setOperator('warehouse_staff_001');
        $transferReservation->setNotes('调拨预留库存');

        $manager->persist($transferReservation);
        $this->addReference(self::STOCK_RESERVATION_TRANSFER, $transferReservation);

        $manager->flush();
    }
}
