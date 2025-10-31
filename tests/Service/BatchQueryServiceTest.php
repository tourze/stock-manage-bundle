<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Tests\Service;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Repository\StockBatchRepository;
use Tourze\StockManageBundle\Service\BatchQueryService;

/**
 * @internal
 */
#[CoversClass(BatchQueryService::class)]
class BatchQueryServiceTest extends TestCase
{
    private BatchQueryService $service;

    private StockBatchRepository&MockObject $repository;

    public function testGetAllBatches(): void
    {
        $batches = [
            $this->createStockBatch('B001', 'SKU-001'),
            $this->createStockBatch('B002', 'SKU-002'),
        ];

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($batches)
        ;

        $result = $this->service->getAllBatches();

        $this->assertCount(2, $result);
        $this->assertSame($batches, $result);
    }

    private function createStockBatch(string $batchNo, string $skuId): StockBatch
    {
        $sku = $this->createMock(SKU::class);
        $sku->method('getId')->willReturn($skuId);

        $batch = new StockBatch();
        $batch->setBatchNo($batchNo);
        $batch->setSku($sku);
        $batch->setQuantity(100);
        $batch->setAvailableQuantity(100);
        $batch->setUnitCost(10.50);

        return $batch;
    }

    public function testFindBatchByNo(): void
    {
        $batch = $this->createStockBatch('B001', 'SKU-001');

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['batchNo' => 'B001'])
            ->willReturn($batch)
        ;

        $result = $this->service->findBatchByNo('B001');

        $this->assertSame($batch, $result);
    }

    public function testFindBatchByNoReturnsNull(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['batchNo' => 'NOTFOUND'])
            ->willReturn(null)
        ;

        $result = $this->service->findBatchByNo('NOTFOUND');

        $this->assertNull($result);
    }

    public function testFindBatchesBySkuId(): void
    {
        $batches = [
            $this->createStockBatch('B001', 'SKU-001'),
            $this->createStockBatch('B002', 'SKU-001'),
        ];

        /** @var QueryBuilder&MockObject $queryBuilder */
        $queryBuilder = $this->createMock(QueryBuilder::class);
        /** @var Query&MockObject $query */
        $query = $this->createMock(Query::class);

        $this->repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('b')
            ->willReturn($queryBuilder)
        ;

        $queryBuilder->expects($this->once())
            ->method('join')
            ->with('b.sku', 's')
            ->willReturn($queryBuilder)
        ;

        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('s.id = :skuId')
            ->willReturn($queryBuilder)
        ;

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('skuId', 'SKU-001')
            ->willReturn($queryBuilder)
        ;

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('b.createTime', 'DESC')
            ->willReturn($queryBuilder)
        ;

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query)
        ;

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn($batches)
        ;

        $result = $this->service->findBatchesBySkuId('SKU-001');

        $this->assertCount(2, $result);
        $this->assertSame($batches, $result);
    }

    protected function setUp(): void
    {
        $this->repository = $this->createMock(StockBatchRepository::class);
        $this->service = new BatchQueryService($this->repository);
    }
}
