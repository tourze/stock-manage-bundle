<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;
use Tourze\StockManageBundle\Repository\StockTransferRepository;

/**
 * @internal
 */
#[CoversClass(StockTransferRepository::class)]
#[RunTestsInSeparateProcesses]
class StockTransferRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // Repository test setup
    }

    private function createSku(string $spuId): Sku
    {
        // Create SPU first
        $spu = new Spu();
        $spu->setTitle('Test SPU for ' . $spuId);
        $spu->setGtin($spuId);

        // Create SKU with SPU relationship
        $sku = new Sku();
        $sku->setGtin($spuId);
        $sku->setSpu($spu);
        $sku->setUnit('个');

        $em = self::getService(EntityManagerInterface::class);
        $em->persist($spu);
        $em->persist($sku);
        $em->flush();

        return $sku;
    }

    protected function getRepository(): StockTransferRepository
    {
        return self::getService(StockTransferRepository::class);
    }

    protected function createNewEntity(): StockTransfer
    {
        $stockTransfer = new StockTransfer();
        $stockTransfer->setTransferNo('TRANS_' . uniqid());
        $stockTransfer->setSku($this->createSku('SPU_' . uniqid()));
        $stockTransfer->setFromLocation('FROM_LOC_' . uniqid());
        $stockTransfer->setToLocation('TO_LOC_' . uniqid());
        $stockTransfer->setItems([
            ['sku_id' => 1, 'quantity' => 50, 'unit_cost' => 10.0],
        ]);
        $stockTransfer->setInitiator('initiator_' . uniqid());
        $stockTransfer->setReceiver('receiver_' . uniqid());
        $stockTransfer->setStatus(StockTransferStatus::PENDING);
        $stockTransfer->setReason('Test transfer');

        return $stockTransfer;
    }

    public function testBasicRepositoryFunctionality(): void
    {
        $transfer = $this->createNewEntity();

        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);
        $em->persist($transfer);
        $em->flush();

        $this->assertNotNull($transfer->getId());

        $foundTransfer = $repository->find($transfer->getId());
        $this->assertNotNull($foundTransfer);
        $this->assertEquals($transfer->getTransferNo(), $foundTransfer->getTransferNo());
    }

    public function testSaveAndRemove(): void
    {
        $repository = $this->getRepository();
        $transfer = $this->createNewEntity();

        // Test save
        $repository->save($transfer);
        $this->assertNotNull($transfer->getId());

        $id = $transfer->getId();
        $found = $repository->find($id);
        $this->assertNotNull($found);

        // Test remove
        $repository->remove($transfer);
        $found = $repository->find($id);
        $this->assertNull($found);
    }

    public function testFindByTransferNo(): void
    {
        $repository = $this->getRepository();
        $transfer = $this->createNewEntity();
        $transferNo = 'UNIQUE_TRANS_001';
        $transfer->setTransferNo($transferNo);

        $repository->save($transfer);

        $found = $repository->findByTransferNo($transferNo);
        $this->assertNotNull($found);
        $this->assertEquals($transferNo, $found->getTransferNo());

        $notFound = $repository->findByTransferNo('NONEXISTENT_TRANS');
        $this->assertNull($notFound);
    }

    public function testFindBySku(): void
    {
        $repository = $this->getRepository();
        $sku = $this->createSku('TEST_SKU_001');

        $transfer1 = $this->createNewEntity();
        $transfer1->setSku($sku);

        $transfer2 = $this->createNewEntity();
        $transfer2->setSku($this->createSku('TEST_SKU_002'));

        $repository->save($transfer1);
        $repository->save($transfer2);

        $transfers = $repository->findBySku($sku);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
    }

    public function testFindByFromLocation(): void
    {
        $repository = $this->getRepository();
        $fromLocation = 'WAREHOUSE_A';

        $transfer1 = $this->createNewEntity();
        $transfer1->setFromLocation($fromLocation);

        $transfer2 = $this->createNewEntity();
        $transfer2->setFromLocation('WAREHOUSE_B');

        $repository->save($transfer1);
        $repository->save($transfer2);

        $transfers = $repository->findByFromLocation($fromLocation);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
    }

    public function testFindByToLocation(): void
    {
        $repository = $this->getRepository();
        $toLocation = 'WAREHOUSE_C';

        $transfer1 = $this->createNewEntity();
        $transfer1->setToLocation($toLocation);

        $transfer2 = $this->createNewEntity();
        $transfer2->setToLocation('WAREHOUSE_D');

        $repository->save($transfer1);
        $repository->save($transfer2);

        $transfers = $repository->findByToLocation($toLocation);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
    }

    public function testFindByStatus(): void
    {
        $repository = $this->getRepository();

        // 清理数据，确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $transfer1 = $this->createNewEntity();
        $transfer1->setStatus(StockTransferStatus::PENDING);

        $transfer2 = $this->createNewEntity();
        $transfer2->setStatus(StockTransferStatus::RECEIVED);

        $repository->save($transfer1);
        $repository->save($transfer2);

        $pendingTransfers = $repository->findByStatus(StockTransferStatus::PENDING);
        $completedTransfers = $repository->findByStatus(StockTransferStatus::RECEIVED);

        $this->assertCount(1, $pendingTransfers);
        $this->assertContains($transfer1, $pendingTransfers);

        $this->assertCount(1, $completedTransfers);
        $this->assertContains($transfer2, $completedTransfers);
    }

    public function testFindPendingTransfers(): void
    {
        $repository = $this->getRepository();

        // 清理数据，确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $transfer1 = $this->createNewEntity();
        $transfer1->setStatus(StockTransferStatus::PENDING);

        $transfer2 = $this->createNewEntity();
        $transfer2->setStatus(StockTransferStatus::IN_TRANSIT);

        $transfer3 = $this->createNewEntity();
        $transfer3->setStatus(StockTransferStatus::PENDING);

        $repository->save($transfer1);
        $repository->save($transfer2);
        $repository->save($transfer3);

        $pendingTransfers = $repository->findPendingTransfers();

        $this->assertCount(2, $pendingTransfers);
        $this->assertContains($transfer1, $pendingTransfers);
        $this->assertContains($transfer3, $pendingTransfers);
        $this->assertNotContains($transfer2, $pendingTransfers);
    }

    public function testFindInTransitTransfers(): void
    {
        $repository = $this->getRepository();

        // 清理数据，确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $transfer1 = $this->createNewEntity();
        $transfer1->setStatus(StockTransferStatus::IN_TRANSIT);
        $transfer1->setShippedTime(new \DateTimeImmutable());

        $transfer2 = $this->createNewEntity();
        $transfer2->setStatus(StockTransferStatus::PENDING);

        $transfer3 = $this->createNewEntity();
        $transfer3->setStatus(StockTransferStatus::IN_TRANSIT);
        $transfer3->setShippedTime(new \DateTimeImmutable());

        $repository->save($transfer1);
        $repository->save($transfer2);
        $repository->save($transfer3);

        $inTransitTransfers = $repository->findInTransitTransfers();

        $this->assertCount(2, $inTransitTransfers);
        $this->assertContains($transfer1, $inTransitTransfers);
        $this->assertContains($transfer3, $inTransitTransfers);
        $this->assertNotContains($transfer2, $inTransitTransfers);
    }

    public function testFindByInitiator(): void
    {
        $repository = $this->getRepository();
        $initiator = 'test_initiator';

        $transfer1 = $this->createNewEntity();
        $transfer1->setInitiator($initiator);

        $transfer2 = $this->createNewEntity();
        $transfer2->setInitiator('other_initiator');

        $repository->save($transfer1);
        $repository->save($transfer2);

        $transfers = $repository->findByInitiator($initiator);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
    }

    public function testFindByReceiver(): void
    {
        $repository = $this->getRepository();
        $receiver = 'test_receiver';

        $transfer1 = $this->createNewEntity();
        $transfer1->setReceiver($receiver);

        $transfer2 = $this->createNewEntity();
        $transfer2->setReceiver('other_receiver');

        $repository->save($transfer1);
        $repository->save($transfer2);

        $transfers = $repository->findByReceiver($receiver);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
    }

    public function testFindByDateRange(): void
    {
        $repository = $this->getRepository();
        $em = self::getService(EntityManagerInterface::class);

        $startDate = new \DateTimeImmutable('2023-01-01');
        $endDate = new \DateTimeImmutable('2023-01-31');

        $transfer1 = $this->createNewEntity();
        $transfer2 = $this->createNewEntity();

        $repository->save($transfer1);
        $repository->save($transfer2);

        // Manually set create time within range for testing
        $em->createQuery('UPDATE ' . StockTransfer::class . ' t SET t.createTime = :date WHERE t.id = :id')
            ->setParameter('date', new \DateTimeImmutable('2023-01-15'))
            ->setParameter('id', $transfer1->getId())
            ->execute()
        ;

        // Set create time outside range
        $em->createQuery('UPDATE ' . StockTransfer::class . ' t SET t.createTime = :date WHERE t.id = :id')
            ->setParameter('date', new \DateTimeImmutable('2023-02-15'))
            ->setParameter('id', $transfer2->getId())
            ->execute()
        ;

        $transfers = $repository->findByDateRange($startDate, $endDate);

        $this->assertCount(1, $transfers);
        $this->assertEquals($transfer1->getId(), $transfers[0]->getId());
    }

    public function testGetTransferStats(): void
    {
        $repository = $this->getRepository();

        // Clear existing data
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $transfer1 = $this->createNewEntity();
        $transfer1->setItems([
            ['sku_id' => 1, 'quantity' => 10, 'unit_cost' => 10.0],
        ]);
        $transfer1->setStatus(StockTransferStatus::PENDING);
        $transfer1->setFromLocation('LOC_A');

        $transfer2 = $this->createNewEntity();
        $transfer2->setItems([
            ['sku_id' => 1, 'quantity' => 20, 'unit_cost' => 10.0],
        ]);
        $transfer2->setStatus(StockTransferStatus::PENDING);
        $transfer2->setFromLocation('LOC_B');

        $repository->save($transfer1);
        $repository->save($transfer2);

        // Test without criteria
        $stats = $repository->getTransferStats();
        $this->assertEquals(2, $stats['total_transfers']);
        $this->assertEquals(30, $stats['total_quantity']);

        // Test with criteria
        $statsWithCriteria = $repository->getTransferStats(['from_location' => 'LOC_A']);
        $this->assertEquals(1, $statsWithCriteria['total_transfers']);
        $this->assertEquals(10, $statsWithCriteria['total_quantity']);
    }

    public function testGetStatusStatistics(): void
    {
        $repository = $this->getRepository();

        // Clear existing data
        $em = self::getService(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $transfer1 = $this->createNewEntity();
        $transfer1->setStatus(StockTransferStatus::PENDING);
        $transfer1->setItems([
            ['sku_id' => 1, 'quantity' => 10, 'unit_cost' => 10.0],
        ]);

        $transfer2 = $this->createNewEntity();
        $transfer2->setStatus(StockTransferStatus::PENDING);
        $transfer2->setItems([
            ['sku_id' => 1, 'quantity' => 20, 'unit_cost' => 10.0],
        ]);

        $transfer3 = $this->createNewEntity();
        $transfer3->setStatus(StockTransferStatus::RECEIVED);
        $transfer3->setItems([
            ['sku_id' => 1, 'quantity' => 15, 'unit_cost' => 10.0],
        ]);

        $repository->save($transfer1);
        $repository->save($transfer2);
        $repository->save($transfer3);

        $stats = $repository->getStatusStatistics();

        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('received', $stats);

        $this->assertEquals(2, $stats['pending']['count']);
        $this->assertEquals(30, $stats['pending']['total_quantity']);

        $this->assertEquals(1, $stats['received']['count']);
        $this->assertEquals(15, $stats['received']['total_quantity']);
    }

    public function testFindOverdueInTransitTransfers(): void
    {
        $repository = $this->getRepository();

        // 清理数据，确保测试隔离
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM ' . StockTransfer::class)->execute();

        $oldTransfer = $this->createNewEntity();
        $oldTransfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $oldTransfer->setShippedTime(new \DateTimeImmutable('-10 days'));

        $recentTransfer = $this->createNewEntity();
        $recentTransfer->setStatus(StockTransferStatus::IN_TRANSIT);
        $recentTransfer->setShippedTime(new \DateTimeImmutable('-2 days'));

        $pendingTransfer = $this->createNewEntity();
        $pendingTransfer->setStatus(StockTransferStatus::PENDING);

        $repository->save($oldTransfer);
        $repository->save($recentTransfer);
        $repository->save($pendingTransfer);

        $overdueTransfers = $repository->findOverdueInTransitTransfers(7);

        $this->assertCount(1, $overdueTransfers);
        $this->assertContains($oldTransfer, $overdueTransfers);
        $this->assertNotContains($recentTransfer, $overdueTransfers);
        $this->assertNotContains($pendingTransfer, $overdueTransfers);
    }

    public function testFindBetweenLocations(): void
    {
        $repository = $this->getRepository();
        $fromLocation = 'WAREHOUSE_A';
        $toLocation = 'WAREHOUSE_B';

        $transfer1 = $this->createNewEntity();
        $transfer1->setFromLocation($fromLocation);
        $transfer1->setToLocation($toLocation);

        $transfer2 = $this->createNewEntity();
        $transfer2->setFromLocation($fromLocation);
        $transfer2->setToLocation('WAREHOUSE_C');

        $transfer3 = $this->createNewEntity();
        $transfer3->setFromLocation('WAREHOUSE_D');
        $transfer3->setToLocation($toLocation);

        $repository->save($transfer1);
        $repository->save($transfer2);
        $repository->save($transfer3);

        $transfers = $repository->findBetweenLocations($fromLocation, $toLocation);

        $this->assertCount(1, $transfers);
        $this->assertContains($transfer1, $transfers);
        $this->assertNotContains($transfer2, $transfers);
        $this->assertNotContains($transfer3, $transfers);
    }
}
