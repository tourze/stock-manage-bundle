<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;

final class StockLogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $stockLogs = [
            [
                'type' => StockChange::INBOUND,
                'quantity' => 100,
                'skuData' => ['id' => 'sku-001'],
                'remark' => '入库操作',
            ],
            [
                'type' => StockChange::OUTBOUND,
                'quantity' => -50,
                'skuData' => ['id' => 'sku-001'],
                'remark' => '出库操作',
            ],
            [
                'type' => StockChange::ADJUSTMENT,
                'quantity' => 10,
                'skuData' => ['id' => 'sku-002'],
                'remark' => '盘点调整',
            ],
            [
                'type' => StockChange::RESERVED,
                'quantity' => -20,
                'skuData' => ['id' => 'sku-002'],
                'remark' => '预留库存',
            ],
            [
                'type' => StockChange::RESERVED_RELEASE,
                'quantity' => 15,
                'skuData' => ['id' => 'sku-002'],
                'remark' => '释放预留',
            ],
        ];

        foreach ($stockLogs as $index => $logData) {
            $stockLog = new StockLog();
            $stockLog->setType($logData['type']);
            $stockLog->setQuantity($logData['quantity']);
            $stockLog->setSkuData($logData['skuData']);
            $stockLog->setRemark($logData['remark']);

            $manager->persist($stockLog);
            $this->addReference('stock-log-' . ($index + 1), $stockLog);
        }

        $manager->flush();
    }
}
