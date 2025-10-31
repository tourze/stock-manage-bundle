<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockSnapshot;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * @phpstan-ignore-next-line complexity.classLike
 */
class SnapshotService implements SnapshotServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockBatchRepository $batchRepository,
    ) {
    }

    /**
     * 创建库存快照（接口实现）.
     */
    public function createSnapshot(SKU $sku, int $quantity, string $type, array $metadata = []): StockSnapshot
    {
        $operator = $metadata['operator'] ?? null;
        assert(is_string($operator) || null === $operator);

        $triggerMethod = $metadata['trigger_method'] ?? 'event_triggered';
        assert(is_string($triggerMethod));

        $notes = $metadata['notes'] ?? sprintf(
            'Event-driven snapshot for SKU %s (quantity: %d). Metadata: %s',
            $sku->getId(),
            $quantity,
            json_encode($metadata, JSON_THROW_ON_ERROR)
        );
        assert(is_string($notes));

        return $this->createSnapshotBySku($sku, $type, $triggerMethod, $notes, $operator);
    }

    /**
     * 为单个SPU创建快照.
     */
    private function createSnapshotBySku(
        SKU $sku,
        string $type,
        string $triggerMethod,
        ?string $notes = null,
        ?string $operator = null,
    ): StockSnapshot {
        $batches = $this->batchRepository->findBySku($sku);

        return $this->createSnapshotFromBatches($batches, $type, $triggerMethod, $notes, $operator);
    }

    /**
     * 创建全量库存快照.
     *
     * @param string      $type          快照类型
     * @param string      $triggerMethod 触发方式
     * @param string|null $notes         备注
     * @param string|null $operator      操作人
     */
    public function createFullSnapshot(
        string $type,
        string $triggerMethod,
        ?string $notes = null,
        ?string $operator = null,
    ): StockSnapshot {
        $batches = $this->batchRepository->findAll();

        return $this->createSnapshotFromBatches($batches, $type, $triggerMethod, $notes, $operator);
    }

    /**
     * 按位置创建库存快照.
     *
     * @param string      $locationId    位置ID
     * @param string      $type          快照类型
     * @param string      $triggerMethod 触发方式
     * @param string|null $notes         备注
     * @param string|null $operator      操作人
     */
    public function createSnapshotByLocation(
        string $locationId,
        string $type,
        string $triggerMethod,
        ?string $notes = null,
        ?string $operator = null,
    ): StockSnapshot {
        $batches = $this->batchRepository->findBy(['locationId' => $locationId]);

        $snapshot = $this->createSnapshotFromBatches($batches, $type, $triggerMethod, $notes, $operator);
        $snapshot->setLocationId($locationId);

        return $snapshot;
    }

    /**
     * 从批次数据创建快照.
     *
     * @param array<StockBatch> $batches
     */
    private function createSnapshotFromBatches(
        array $batches,
        string $type,
        string $triggerMethod,
        ?string $notes = null,
        ?string $operator = null,
    ): StockSnapshot {
        $snapshot = $this->initializeSnapshot($type, $triggerMethod, $notes, $operator);

        $this->populateSnapshotWithData($snapshot, $batches);
        $this->finalizeSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * 用数据填充快照.
     *
     * @param array<StockBatch> $batches
     */
    private function populateSnapshotWithData(StockSnapshot $snapshot, array $batches): void
    {
        $snapshotData = $this->processBatchData($batches);
        $this->populateSnapshotData($snapshot, $snapshotData);
    }

    /**
     * 完成快照创建.
     */
    private function finalizeSnapshot(StockSnapshot $snapshot): void
    {
        $this->setSnapshotValidity($snapshot);
        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();
    }

    /**
     * 初始化快照基础信息.
     */
    private function initializeSnapshot(string $type, string $triggerMethod, ?string $notes, ?string $operator): StockSnapshot
    {
        $snapshot = new StockSnapshot();
        $snapshot->setSnapshotNo($this->generateSnapshotNo($type));
        $snapshot->setType($type);
        $snapshot->setTriggerMethod($triggerMethod);
        $snapshot->setNotes($notes);
        $snapshot->setOperator($operator);

        return $snapshot;
    }

    /**
     * 处理批次数据并计算统计信息.
     *
     * @param array<StockBatch> $batches
     *
     * @return array{totalQuantity: int, totalValue: float, productData: array<string, mixed>, batchDetails: array<mixed>}
     */
    private function processBatchData(array $batches): array
    {
        return $this->calculateBatchStatistics($batches);
    }

    /**
     * 计算批次统计信息.
     *
     * @param array<StockBatch> $batches
     *
     * @return array{totalQuantity: int, totalValue: float, productData: array<string, mixed>, batchDetails: array<mixed>}
     */
    private function calculateBatchStatistics(array $batches): array
    {
        $totalQuantity = 0;
        $totalValue = 0.0;
        $productData = [];
        $batchDetails = [];

        foreach ($batches as $batch) {
            $batchResult = $this->processSingleBatch($batch);
            if (null !== $batchResult) {
                $totalQuantity += $batchResult['quantity'];
                $totalValue += $batchResult['value'];
                $productData = $this->updateProductData($productData, $batchResult);
                $batchDetails[] = $batchResult['batchDetail'];
            }
        }

        return [
            'totalQuantity' => $totalQuantity,
            'totalValue' => $totalValue,
            'productData' => $productData,
            'batchDetails' => $batchDetails,
        ];
    }

    /**
     * 处理单个批次.
     *
     * @return array{quantity: int, value: float, batchDetail: array<string, mixed>}|null
     */
    private function processSingleBatch(StockBatch $batch): ?array
    {
        if (!$this->isValidBatch($batch)) {
            return null;
        }

        return $this->createBatchResult($batch);
    }

    /**
     * 检查批次是否有效.
     */
    private function isValidBatch(StockBatch $batch): bool
    {
        $sku = $batch->getSku();
        if (null === $sku) {
            return false;
        }

        $quantity = $batch->getAvailableQuantity();

        return $quantity > 0;
    }

    /**
     * 创建批次结果.
     *
     * @return array{quantity: int, value: float, batchDetail: array<string, mixed>}
     */
    private function createBatchResult(StockBatch $batch): array
    {
        $sku = $batch->getSku();
        $quantity = $batch->getAvailableQuantity();
        $value = $quantity * $batch->getUnitCost();

        $batchDetail = [
            'batchNo' => $batch->getBatchNo(),
            'sku' => $sku,
            'quantity' => $quantity,
            'unitCost' => $batch->getUnitCost(),
            'value' => $value,
            'locationId' => $batch->getLocationId(),
        ];

        return [
            'quantity' => $quantity,
            'value' => $value,
            'batchDetail' => $batchDetail,
        ];
    }

    /**
     * 更新产品数据.
     *
     * @param array<string, mixed> $productData
     * @param array{quantity: int, value: float, batchDetail: array<string, mixed>} $batchResult
     * @return array<string, mixed>
     */
    private function updateProductData(array $productData, array $batchResult): array
    {
        $sku = $batchResult['batchDetail']['sku'];
        assert($sku instanceof SKU);

        $skuId = $sku->getId();

        $quantity = $batchResult['quantity'];
        $value = $batchResult['value'];

        $batchNo = $batchResult['batchDetail']['batchNo'];
        assert(is_string($batchNo));

        if (!isset($productData[$skuId])) {
            $productData[$skuId] = [
                'quantity' => 0,
                'value' => 0.0,
                'batches' => [],
            ];
        }

        assert(is_array($productData[$skuId]));
        assert(isset($productData[$skuId]['quantity']) && is_int($productData[$skuId]['quantity']));
        assert(isset($productData[$skuId]['value']) && is_float($productData[$skuId]['value']));
        assert(isset($productData[$skuId]['batches']) && is_array($productData[$skuId]['batches']));

        /** @var array{quantity: int, value: float, batches: array<string>} $currentProduct */
        $currentProduct = $productData[$skuId];
        $currentProduct['quantity'] += $quantity;
        $currentProduct['value'] += $value;
        $currentProduct['batches'][] = $batchNo;
        $productData[$skuId] = $currentProduct;

        return $productData;
    }

    /**
     * 填充快照数据.
     *
     * @param array{totalQuantity: int, totalValue: float, productData: array<string, mixed>, batchDetails: array<mixed>} $snapshotData
     */
    private function populateSnapshotData(StockSnapshot $snapshot, array $snapshotData): void
    {
        $productData = $snapshotData['productData'];
        $batchDetails = $snapshotData['batchDetails'];

        $snapshot->setTotalQuantity($snapshotData['totalQuantity']);
        $snapshot->setTotalValue($snapshotData['totalValue']);
        $snapshot->setProductCount(count($productData));
        $snapshot->setBatchCount(count($batchDetails));

        $summary = [
            'byProduct' => $productData,
            'totalProducts' => count($productData),
            'totalBatches' => count($batchDetails),
        ];
        $snapshot->setSummary($summary);

        // 转换 batchDetails 为关联数组格式
        $detailsData = ['batches' => $batchDetails];
        $snapshot->setDetails($detailsData);
    }

    /**
     * 设置快照有效期.
     */
    private function setSnapshotValidity(StockSnapshot $snapshot): void
    {
        $validUntil = new \DateTimeImmutable();
        $validUntil = $validUntil->modify('+30 days');
        $snapshot->setValidUntil($validUntil);
    }

    /**
     * 生成快照编号.
     */
    private function generateSnapshotNo(string $type): string
    {
        $prefix = match ($type) {
            'daily' => 'DAILY',
            'monthly' => 'MONTH',
            'inventory_count' => 'COUNT',
            'temporary' => 'TEMP',
            'emergency' => 'EMERG',
            default => 'SNAP',
        };

        return $prefix . '-' . date('YmdHis') . '-' . substr(uniqid(), -4);
    }

    /**
     * 比较两个快照.
     *
     * @return array{
     *     quantityChange: int,
     *     valueChange: float,
     *     quantityChangePercentage: float,
     *     valueChangePercentage: float,
     *     productChanges: array<string, array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}>,
     *     newProducts: array<string, array{quantity: int, value: float, batches: array<string>}>,
     *     removedProducts: array<string, array{quantity: int, value: float, batches: array<string>}>,
     *     snapshot1Date: string,
     *     snapshot2Date: string
     * }
     */
    /**
     * @return array{quantityChange: int, valueChange: float, quantityChangePercentage: float, valueChangePercentage: float, productChanges: array<string, array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}>, newProducts: array<string, array{quantity: int, value: float, batches: array<string>}>, removedProducts: array<string, array{quantity: int, value: float, batches: array<string>}>, snapshot1Date: string, snapshot2Date: string}
     */
    public function compareSnapshots(StockSnapshot $snapshot1, StockSnapshot $snapshot2): array
    {
        $productsData = $this->extractProductsData($snapshot1, $snapshot2);
        $changes = $this->calculateSnapshotChanges($snapshot1, $snapshot2);
        $productDiffs = $this->analyzeProductDifferences($productsData['products1'], $productsData['products2']);

        return $this->buildComparisonResult($changes, $productDiffs, $snapshot1, $snapshot2);
    }

    /**
     * 提取两个快照的产品数据.
     *
     * @return array{products1: array<string, mixed>, products2: array<string, mixed>}
     */
    private function extractProductsData(StockSnapshot $snapshot1, StockSnapshot $snapshot2): array
    {
        $summary1 = $snapshot1->getSummary();
        $summary2 = $snapshot2->getSummary();

        /** @var array<string, mixed> $products1 */
        $products1 = isset($summary1['byProduct']) && is_array($summary1['byProduct'])
            ? $summary1['byProduct']
            : [];

        /** @var array<string, mixed> $products2 */
        $products2 = isset($summary2['byProduct']) && is_array($summary2['byProduct'])
            ? $summary2['byProduct']
            : [];

        return [
            'products1' => $products1,
            'products2' => $products2,
        ];
    }

    /**
     * 构建比较结果.
     *
     * @param array{quantityChange: int, valueChange: float, quantityChangePercentage: float, valueChangePercentage: float} $changes
     * @param array{productChanges: array<string, array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}>, newProducts: array<string, array{quantity: int, value: float, batches: array<string>}>, removedProducts: array<string, array{quantity: int, value: float, batches: array<string>}>} $productDiffs
     * @return array{quantityChange: int, valueChange: float, quantityChangePercentage: float, valueChangePercentage: float, productChanges: array<string, array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}>, newProducts: array<string, array{quantity: int, value: float, batches: array<string>}>, removedProducts: array<string, array{quantity: int, value: float, batches: array<string>}>, snapshot1Date: string, snapshot2Date: string}
     */
    private function buildComparisonResult(array $changes, array $productDiffs, StockSnapshot $snapshot1, StockSnapshot $snapshot2): array
    {
        return [
            'quantityChange' => $changes['quantityChange'],
            'valueChange' => $changes['valueChange'],
            'quantityChangePercentage' => $changes['quantityChangePercentage'],
            'valueChangePercentage' => $changes['valueChangePercentage'],
            'productChanges' => $productDiffs['productChanges'],
            'newProducts' => $productDiffs['newProducts'],
            'removedProducts' => $productDiffs['removedProducts'],
            'snapshot1Date' => $snapshot1->getCreateTime()?->format('Y-m-d H:i:s') ?? '',
            'snapshot2Date' => $snapshot2->getCreateTime()?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * @return array{quantityChange: int, valueChange: float, quantityChangePercentage: float, valueChangePercentage: float}
     */
    private function calculateSnapshotChanges(StockSnapshot $snapshot1, StockSnapshot $snapshot2): array
    {
        $quantityChange = $snapshot2->getTotalQuantity() - $snapshot1->getTotalQuantity();
        $valueChange = $snapshot2->getTotalValue() - $snapshot1->getTotalValue();

        $quantityChangePercentage = $snapshot1->getTotalQuantity() > 0
            ? round(($quantityChange / $snapshot1->getTotalQuantity()) * 100, 2)
            : 0.0;

        $valueChangePercentage = $snapshot1->getTotalValue() > 0
            ? round(($valueChange / $snapshot1->getTotalValue()) * 100, 2)
            : 0.0;

        return [
            'quantityChange' => $quantityChange,
            'valueChange' => $valueChange,
            'quantityChangePercentage' => $quantityChangePercentage,
            'valueChangePercentage' => $valueChangePercentage,
        ];
    }

    /**
     * @param array<string, mixed> $products1
     * @param array<string, mixed> $products2
     *
     * @return array{
     *     productChanges: array<string, array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}>,
     *     newProducts: array<string, array{quantity: int, value: float, batches: array<string>}>,
     *     removedProducts: array<string, array{quantity: int, value: float, batches: array<string>}>
     * }
     */
    private function analyzeProductDifferences(array $products1, array $products2): array
    {
        $productChanges = [];
        $newProducts = [];

        foreach ($products2 as $skuId => $data2) {
            $result = $this->analyzeSingleProductDifference($skuId, $data2, $products1);

            if (isset($result['change'])) {
                $productChanges[(string) $skuId] = $result['change'];
            } elseif (isset($result['new'])) {
                $newProducts[(string) $skuId] = $result['new'];
            }
        }

        $removedProducts = $this->findRemovedProducts($products1, $products2);

        return [
            'productChanges' => $productChanges,
            'newProducts' => $newProducts,
            'removedProducts' => $removedProducts,
        ];
    }

    /**
     * 分析单个产品的差异.
     *
     * @param string|int $skuId
     * @param mixed $data2
     * @param array<string, mixed> $products1
     *
     * @return array{change?: array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}, new?: array{quantity: int, value: float, batches: array<string>}}
     */
    private function analyzeSingleProductDifference($skuId, $data2, array $products1): array
    {
        $skuId = (string) $skuId;
        assert(is_array($data2));

        if (isset($products1[$skuId])) {
            $data1 = $products1[$skuId];
            $this->validateProductDataForComparison($data1, $data2);
            assert(is_array($data1));
            assert(isset($data1['quantity']) && is_int($data1['quantity']));
            assert(isset($data1['value']) && is_float($data1['value']));
            assert(isset($data1['batches']) && is_array($data1['batches']));
            assert(isset($data2['quantity']) && is_int($data2['quantity']));
            assert(isset($data2['value']) && is_float($data2['value']));
            assert(isset($data2['batches']) && is_array($data2['batches']));

            /** @var array{quantity: int, value: float, batches: array<string>} $typedData1 */
            $typedData1 = $data1;
            /** @var array{quantity: int, value: float, batches: array<string>} $typedData2 */
            $typedData2 = $data2;

            return ['change' => $this->calculateProductChange($typedData1, $typedData2)];
        }

        return ['new' => $this->extractProductData($data2)];
    }

    /**
     * 验证产品数据用于比较.
     *
     * @param mixed $data1
     * @param mixed $data2
     */
    private function validateProductDataForComparison($data1, $data2): void
    {
        if (!is_array($data1) || !is_array($data2)) {
            throw new \InvalidArgumentException('Invalid product data for comparison');
        }
        if (!isset($data1['quantity'], $data1['value'], $data1['batches'])
            || !isset($data2['quantity'], $data2['value'], $data2['batches'])) {
            throw new \InvalidArgumentException('Missing required fields in product data');
        }
    }

    /**
     * @param array{quantity: int, value: float, batches: array<string>} $data1
     * @param array{quantity: int, value: float, batches: array<string>} $data2
     * @return array{quantityChange: int, valueChange: float, oldQuantity: int, newQuantity: int}
     */
    private function calculateProductChange(array $data1, array $data2): array
    {
        assert(isset($data2['quantity']) && is_int($data2['quantity']));
        assert(isset($data1['quantity']) && is_int($data1['quantity']));
        assert(isset($data2['value']) && is_float($data2['value']));
        assert(isset($data1['value']) && is_float($data1['value']));

        return [
            'quantityChange' => $data2['quantity'] - $data1['quantity'],
            'valueChange' => $data2['value'] - $data1['value'],
            'oldQuantity' => $data1['quantity'],
            'newQuantity' => $data2['quantity'],
        ];
    }

    /**
     * @param mixed $data
     *
     * @return array{quantity: int, value: float, batches: array<string>}
     */
    private function extractProductData($data): array
    {
        assert(is_array($data));
        assert(isset($data['quantity']) && is_int($data['quantity']));
        assert(isset($data['value']) && is_float($data['value']));
        assert(isset($data['batches']) && is_array($data['batches']));

        return [
            'quantity' => $data['quantity'],
            'value' => $data['value'],
            'batches' => array_values(array_filter($data['batches'], 'is_string')),
        ];
    }

    /**
     * @param array<mixed> $products1
     * @param array<mixed> $products2
     *
     * @return array<string, array{quantity: int, value: float, batches: array<string>}>
     */
    private function findRemovedProducts(array $products1, array $products2): array
    {
        /** @var array<string, array{quantity: int, value: float, batches: array<string>}> $removedProducts */
        $removedProducts = [];

        foreach ($products1 as $skuId => $data1) {
            // 将键显式转换为字符串，因为 PHP 可能将数字字符串转为整数键
            $skuId = (string) $skuId;
            if (!isset($products2[$skuId])) {
                $removedProducts[$skuId] = $this->extractProductData($data1);
            }
        }

        return $removedProducts;
    }

    /**
     * 获取最新快照.
     */
    public function getLatestSnapshot(): ?StockSnapshot
    {
        return $this->entityManager->getRepository(StockSnapshot::class)
            ->findOneBy([], ['createTime' => 'DESC'])
        ;
    }

    /**
     * 获取指定日期范围的快照.
     *
     * @return array<StockSnapshot>
     */
    public function getSnapshotsByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $qb = $this->entityManager->getRepository(StockSnapshot::class)->createQueryBuilder('s');
        $qb->where('s.createTime >= :startDate')
            ->andWhere('s.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.createTime', 'DESC')
        ;

        $result = $qb->getQuery()->getResult();
        assert(is_array($result));

        // 确保返回的都是StockSnapshot实例
        return array_map(function ($item) {
            if (!$item instanceof StockSnapshot) {
                throw new \RuntimeException('Query returned invalid result type');
            }

            return $item;
        }, $result);
    }

    /**
     * 删除过期快照.
     *
     * @param int $retentionDays 保留天数
     *
     * @return int 删除的数量
     */
    public function deleteOldSnapshots(int $retentionDays): int
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$retentionDays} days");

        $qb = $this->entityManager->getRepository(StockSnapshot::class)->createQueryBuilder('s');
        $qb->where('s.createTime < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
        ;

        $oldSnapshots = $qb->getQuery()->getResult();
        assert(is_array($oldSnapshots));

        $count = 0;
        foreach ($oldSnapshots as $snapshot) {
            assert($snapshot instanceof StockSnapshot);
            $this->entityManager->remove($snapshot);
            ++$count;
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    /**
     * 生成快照报告.
     *
     * @return array{
     *     snapshotNo: string,
     *     type: string,
     *     triggerMethod: string,
     *     date: string,
     *     operator: string|null,
     *     locationId: string|null,
     *     summary: array{totalQuantity: int, totalValue: float, productCount: int, batchCount: int},
     *     topProducts: array<string, array{quantity: int, value: float, batches: array<string>}>,
     *     notes: string|null,
     *     validUntil: string|null
     * }
     */
    public function generateSnapshotReport(StockSnapshot $snapshot): array
    {
        $summary = $snapshot->getSummary();
        $products = $summary['byProduct'] ?? [];
        assert(is_array($products));

        $topProducts = $this->extractTopProducts($products);

        return $this->buildSnapshotReport($snapshot, $topProducts);
    }

    /**
     * 提取前10名产品.
     *
     * @param array<mixed> $products
     *
     * @return array<string, array{quantity: int, value: float, batches: array<string>}>
     */
    private function extractTopProducts(array $products): array
    {
        // 按价值排序，获取前10的产品
        uasort($products, function ($a, $b) {
            assert(is_array($a) && is_array($b));
            assert(isset($a['value'], $b['value']));

            return $b['value'] <=> $a['value'];
        });

        $topProductsRaw = array_slice($products, 0, 10, true);
        /** @var array<string, array{quantity: int, value: float, batches: array<string>}> $topProducts */
        $topProducts = [];
        foreach ($topProductsRaw as $skuId => $data) {
            assert(is_array($data));
            /** @var array<string, mixed> $typedData */
            $typedData = $data;
            $topProducts[(string) $skuId] = $this->formatProductData($typedData);
        }

        return $topProducts;
    }

    /**
     * 格式化产品数据.
     *
     * @param array<string, mixed> $data
     *
     * @return array{quantity: int, value: float, batches: array<string>}
     */
    private function formatProductData(array $data): array
    {
        assert(isset($data['quantity']) && is_int($data['quantity']));
        assert(isset($data['value']) && is_float($data['value']));
        assert(isset($data['batches']) && is_array($data['batches']));

        return [
            'quantity' => $data['quantity'],
            'value' => $data['value'],
            'batches' => array_values(array_filter($data['batches'], 'is_string')),
        ];
    }

    /**
     * 构建快照报告.
     *
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $topProducts
     *
     * @return array{
     *     snapshotNo: string,
     *     type: string,
     *     triggerMethod: string,
     *     date: string,
     *     operator: string|null,
     *     locationId: string|null,
     *     summary: array{totalQuantity: int, totalValue: float, productCount: int, batchCount: int},
     *     topProducts: array<string, array{quantity: int, value: float, batches: array<string>}>,
     *     notes: string|null,
     *     validUntil: string|null
     * }
     */
    private function buildSnapshotReport(StockSnapshot $snapshot, array $topProducts): array
    {
        return [
            'snapshotNo' => $snapshot->getSnapshotNo(),
            'type' => $snapshot->getType(),
            'triggerMethod' => $snapshot->getTriggerMethod(),
            'date' => $snapshot->getCreateTime()?->format('Y-m-d H:i:s') ?? '',
            'operator' => $snapshot->getOperator(),
            'locationId' => $snapshot->getLocationId(),
            'summary' => [
                'totalQuantity' => $snapshot->getTotalQuantity(),
                'totalValue' => $snapshot->getTotalValue(),
                'productCount' => $snapshot->getProductCount(),
                'batchCount' => $snapshot->getBatchCount(),
            ],
            'topProducts' => $topProducts,
            'notes' => $snapshot->getNotes(),
            'validUntil' => null !== $snapshot->getValidUntil() ? $snapshot->getValidUntil()->format('Y-m-d') : null,
        ];
    }

    /**
     * 创建盘点快照（与实际库存对比）.
     *
     * @param array<string, int> $actualData 实际盘点数据 ['skuId' => quantity, ...]
     * @param string             $operator   盘点人
     * @param string|null        $notes      备注
     *
     * @return array<string, mixed> 差异报告
     */
    public function createInventoryCountSnapshot(array $actualData, string $operator, ?string $notes = null): array
    {
        $snapshot = $this->createFullSnapshot('inventory_count', 'manual', $notes, $operator);
        $systemData = $this->extractSystemDataFromSnapshot($snapshot);

        $differences = $this->calculateInventoryDifferences($actualData, $systemData);
        $this->updateSnapshotMetadata($snapshot, $actualData, $differences);

        $this->entityManager->flush();

        return $this->buildInventoryCountReport($snapshot, $differences);
    }

    /**
     * 从快照中提取系统数据.
     *
     * @return array<string, array{quantity: int, value: float, batches: array<string>}>
     */
    private function extractSystemDataFromSnapshot(StockSnapshot $snapshot): array
    {
        $systemDataRaw = $snapshot->getSummary()['byProduct'] ?? [];
        assert(is_array($systemDataRaw));

        /** @var array<string, array{quantity: int, value: float, batches: array<string>}> $systemData */
        $systemData = [];
        foreach ($systemDataRaw as $skuId => $data) {
            assert(is_array($data));
            /** @var array<string, mixed> $typedData */
            $typedData = $data;
            $systemData[(string) $skuId] = $this->formatProductData($typedData);
        }

        return $systemData;
    }

    /**
     * 构建盘点报告.
     *
     * @param array{items: array<string, mixed>, totalQuantity: int, totalValue: float} $differences
     *
     * @return array<string, mixed>
     */
    private function buildInventoryCountReport(StockSnapshot $snapshot, array $differences): array
    {
        return [
            'snapshot' => $snapshot,
            'differences' => $differences['items'],
            'totalDifferenceQuantity' => $differences['totalQuantity'],
            'totalDifferenceValue' => $differences['totalValue'],
            'summary' => [
                'totalDifferenceQuantity' => $differences['totalQuantity'],
                'totalDifferenceValue' => $differences['totalValue'],
                'differenceCount' => count($differences['items']),
            ],
        ];
    }

    /**
     * @param array<string, int>                                                        $actualData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array{items: array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>, totalQuantity: int, totalValue: float}
     */
    private function calculateInventoryDifferences(array $actualData, array $systemData): array
    {
        $differenceData = $this->gatherAllDifferences($actualData, $systemData);
        $totals = $this->calculateDifferenceTotals($differenceData, $systemData);

        return [
            'items' => $differenceData['allDifferences'],
            'totalQuantity' => $totals['quantity'],
            'totalValue' => round($totals['value'], 2),
        ];
    }

    /**
     * 收集所有差异.
     *
     * @param array<string, int>                                                        $actualData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array{allDifferences: array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>, actualDifferences: array<array{differenceValue: float, differenceQuantity: int}>, missingDifferences: array<array{differenceValue: float, systemQuantity: int}>}
     */
    private function gatherAllDifferences(array $actualData, array $systemData): array
    {
        $differences = $this->calculateActualInventoryDifferences($actualData, $systemData);
        $missingDifferences = $this->findMissingProducts($actualData, $systemData);

        return [
            'allDifferences' => array_merge($differences['items'], $missingDifferences),
            'actualDifferences' => $differences['items'],
            'missingDifferences' => $missingDifferences,
        ];
    }

    /**
     * 计算差异总计.
     *
     * @param array{allDifferences: array<string, mixed>, actualDifferences: array<array{differenceValue: float, differenceQuantity: int}>, missingDifferences: array<array{differenceValue: float, systemQuantity: int}>} $differenceData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array{quantity: int, value: float}
     */
    private function calculateDifferenceTotals(array $differenceData, array $systemData): array
    {
        $totalQuantity = $this->calculateTotalDifferenceQuantity(
            $differenceData['actualDifferences'],
            $differenceData['missingDifferences']
        );
        $totalValue = $this->calculateTotalDifferenceValue(
            $differenceData['actualDifferences'],
            $differenceData['missingDifferences'],
            $systemData
        );

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
        ];
    }

    /**
     * 计算实际盘点差异.
     *
     * @param array<string, int>                                                        $actualData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array{items: array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>, totalQuantity: int, totalValue: float}
     */
    private function calculateActualInventoryDifferences(array $actualData, array $systemData): array
    {
        $differenceItems = $this->collectDifferenceItems($actualData, $systemData);
        $totals = $this->calculateDifferenceTotalsFromItems($differenceItems);

        return [
            'items' => $differenceItems,
            'totalQuantity' => $totals['quantity'],
            'totalValue' => $totals['value'],
        ];
    }

    /**
     * 收集差异项目.
     *
     * @param array<string, int>                                                        $actualData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>
     */
    private function collectDifferenceItems(array $actualData, array $systemData): array
    {
        $differences = [];
        foreach ($actualData as $skuId => $actualQuantity) {
            $differenceData = $this->calculateSkuDifference((string) $skuId, $actualQuantity, $systemData);
            if (null !== $differenceData) {
                $differences[(string) $skuId] = $differenceData;
            }
        }

        return $differences;
    }

    /**
     * 从差异项目计算总计.
     *
     * @param array<string, array{differenceQuantity: int, differenceValue: float}> $differenceItems
     *
     * @return array{quantity: int, value: float}
     */
    private function calculateDifferenceTotalsFromItems(array $differenceItems): array
    {
        $totalQuantity = 0;
        $totalValue = 0.0;

        foreach ($differenceItems as $difference) {
            $totalQuantity += abs($difference['differenceQuantity']);
            $totalValue += abs($difference['differenceValue']);
        }

        return [
            'quantity' => $totalQuantity,
            'value' => $totalValue,
        ];
    }

    /**
     * 计算总差异数量.
     *
     * @param array<array{differenceQuantity: int}> $differences
     * @param array<array{systemQuantity: int}> $missingDifferences
     */
    private function calculateTotalDifferenceQuantity(array $differences, array $missingDifferences): int
    {
        $total = 0;
        foreach ($differences as $difference) {
            $total += abs($difference['differenceQuantity']);
        }
        foreach ($missingDifferences as $difference) {
            $total += $difference['systemQuantity'];
        }

        return $total;
    }

    /**
     * 计算总差异价值.
     *
     * @param array<array{differenceValue: float}> $differences
     * @param array<array{differenceValue: float}> $missingDifferences
     * @param array<array{value: float}> $systemData
     */
    private function calculateTotalDifferenceValue(array $differences, array $missingDifferences, array $systemData): float
    {
        $total = 0.0;

        foreach ($differences as $difference) {
            $total += abs($difference['differenceValue']);
        }

        foreach ($missingDifferences as $difference) {
            $total += abs($difference['differenceValue']);
        }

        return $total;
    }

    /**
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}|null
     */
    private function calculateSkuDifference(string $skuId, int $actualQuantity, array $systemData): ?array
    {
        $systemQuantity = $systemData[$skuId]['quantity'] ?? 0;
        $differenceQuantity = $actualQuantity - $systemQuantity;

        if (0 === $differenceQuantity) {
            return null;
        }

        $averageCost = $systemQuantity > 0
            ? $systemData[$skuId]['value'] / $systemQuantity
            : 0.0;
        $differenceValue = $differenceQuantity * $averageCost;

        return [
            'systemQuantity' => $systemQuantity,
            'actualQuantity' => $actualQuantity,
            'differenceQuantity' => $differenceQuantity,
            'differenceValue' => round($differenceValue, 2),
            'differencePercentage' => $systemQuantity > 0
                ? round(($differenceQuantity / $systemQuantity) * 100, 2)
                : 100.0,
        ];
    }

    /**
     * @param array<string, int>                                                        $actualData
     * @param array<string, array{quantity: int, value: float, batches: array<string>}> $systemData
     *
     * @return array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>
     */
    private function findMissingProducts(array $actualData, array $systemData): array
    {
        $missing = [];

        foreach ($systemData as $skuId => $data) {
            if (!isset($actualData[$skuId]) && $data['quantity'] > 0) {
                $missing[$skuId] = [
                    'systemQuantity' => $data['quantity'],
                    'actualQuantity' => 0,
                    'differenceQuantity' => -$data['quantity'],
                    'differenceValue' => -$data['value'],
                    'differencePercentage' => -100.0,
                ];
            }
        }

        return $missing;
    }

    /**
     * @param array<string, int>                                                                                                                                                                                $actualData
     * @param array{items: array<string, array{systemQuantity: int, actualQuantity: int, differenceQuantity: int, differenceValue: float, differencePercentage: float}>, totalQuantity: int, totalValue: float} $differences
     */
    private function updateSnapshotMetadata(StockSnapshot $snapshot, array $actualData, array $differences): void
    {
        $metadata = [
            'inventoryCount' => [
                'actualData' => $actualData,
                'differences' => $differences['items'],
                'totalDifferenceQuantity' => $differences['totalQuantity'],
                'totalDifferenceValue' => $differences['totalValue'],
                'differenceCount' => count($differences['items']),
            ],
        ];
        $snapshot->setMetadata($metadata);
    }
}
