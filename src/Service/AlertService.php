<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

/**
 * 库存预警服务
 * 负责库存预警的创建、管理和通知
 * 遵循扁平化架构：所有业务逻辑在Service层实现.
 */
class AlertService implements AlertServiceInterface
{
    private const SEVERITY_LEVELS = [
        'low',      // 低
        'medium',   // 中
        'high',     // 高
        'critical', // 严重
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockBatchRepository $batchRepository,
    ) {
    }

    /**
     * 发送低库存预警.
     */
    public function sendLowStockAlert(SKU $sku, int $currentQuantity, int $threshold): void
    {
        $this->createAlert([
            'sku' => $sku,
            'alert_type' => StockAlertType::LOW_STOCK,
            'severity' => $this->calculateLowStockSeverity($currentQuantity, $threshold),
            'threshold_value' => $threshold,
            'current_value' => $currentQuantity,
            'message' => sprintf(
                'Low stock alert for SKU %s: Current stock %d is below threshold %d',
                $sku->getId(),
                $currentQuantity,
                $threshold
            ),
        ]);
    }

    /**
     * 创建预警.
     *
     * @param array{
     *     sku?: SKU,
     *     spu_id?: string,
     *     alert_type: StockAlertType,
     *     severity?: string,
     *     threshold_value?: float,
     *     current_value?: float,
     *     message: string,
     *     location_id?: string,
     *     metadata?: array<string, mixed>
     * } $data
     *
     * @throws InvalidArgumentException
     */
    public function createAlert(array $data): StockAlert
    {
        $this->validateAlertData($data);

        $alert = new StockAlert();

        if (!isset($data['sku'])) {
            throw new InvalidArgumentException('SKU is required');
        }

        $alert->setSku($data['sku']);

        $alert->setAlertType($data['alert_type']);
        $alert->setMessage($data['message']);

        if (isset($data['severity'])) {
            $alert->setSeverity(StockAlertSeverity::from($data['severity']));
        }

        if (isset($data['threshold_value'])) {
            $alert->setThresholdValue($data['threshold_value']);
        }

        if (isset($data['current_value'])) {
            $alert->setCurrentValue($data['current_value']);
        }

        if (isset($data['location_id'])) {
            $alert->setLocationId($data['location_id']);
        }

        if (isset($data['metadata'])) {
            $alert->setMetadata($data['metadata']);
        }

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * 检查低库存预警.
     *
     * @param array<string, int> $thresholds spu_id => 阈值
     *
     * @return array<array{spu_id: string, alert_type: StockAlertType, current_stock: int, threshold: int, severity: string, message: string}>
     */
    public function checkLowStockAlerts(array $thresholds): array
    {
        $alerts = [];

        foreach ($thresholds as $spuId => $threshold) {
            $currentStock = $this->batchRepository->getTotalAvailableQuantity($spuId);

            if ($currentStock <= $threshold) {
                $severity = $this->calculateAlertSeverity(StockAlertType::LOW_STOCK, $currentStock, $threshold);

                $alerts[] = [
                    'spu_id' => $spuId,
                    'alert_type' => StockAlertType::LOW_STOCK,
                    'current_stock' => $currentStock,
                    'threshold' => $threshold,
                    'severity' => $severity,
                    'message' => sprintf(
                        'Low stock alert for SKU %s: Current stock %d is below threshold %d',
                        $spuId,
                        $currentStock,
                        $threshold
                    ),
                ];
            }
        }

        return $alerts;
    }

    /**
     * 检查高库存预警.
     *
     * @param array<string, int> $thresholds spu_id => 阈值
     *
     * @return array<array{spu_id: string, alert_type: StockAlertType, current_stock: int, threshold: int, severity: string, message: string}>
     */
    public function checkHighStockAlerts(array $thresholds): array
    {
        $alerts = [];

        foreach ($thresholds as $spuId => $threshold) {
            $currentStock = $this->batchRepository->getTotalAvailableQuantity($spuId);

            if ($currentStock >= $threshold) {
                $alerts[] = [
                    'spu_id' => $spuId,
                    'alert_type' => StockAlertType::HIGH_STOCK,
                    'current_stock' => $currentStock,
                    'threshold' => $threshold,
                    'severity' => 'medium',
                    'message' => sprintf(
                        'High stock alert for SKU %s: Current stock %d exceeds threshold %d',
                        $spuId,
                        $currentStock,
                        $threshold
                    ),
                ];
            }
        }

        return $alerts;
    }

    /**
     * 检查过期预警.
     *
     * @return array<array{sku: ?SKU, alert_type: StockAlertType, batch_no: string, expiry_date: string, days_remaining: int, severity: string, message: string}>
     */
    public function checkExpiryAlerts(int $warningDays = 30): array
    {
        $expiringBatches = $this->batchRepository->findBatchesExpiringSoon($warningDays);
        $alerts = [];

        foreach ($expiringBatches as $batch) {
            $alertData = $this->createExpiryAlertData($batch);
            if (null !== $alertData) {
                $alerts[] = $alertData;
            }
        }

        return $alerts;
    }

    /**
     * @return array{sku: ?SKU, alert_type: StockAlertType, batch_no: string, expiry_date: string, days_remaining: int, severity: string, message: string}|null
     */
    private function createExpiryAlertData(object $batch): ?array
    {
        // $batch is already typed as object in the parameter
        assert(method_exists($batch, 'getExpiryDate'));
        assert(method_exists($batch, 'getBatchNo'));
        assert(method_exists($batch, 'getSku'));

        $expiryDate = $batch->getExpiryDate();
        if (null === $expiryDate) {
            return null;
        }
        assert($expiryDate instanceof \DateTimeInterface);

        $now = new \DateTime();
        $diff = $now->diff($expiryDate);
        assert(false !== $diff->days);
        $daysRemaining = (int) $diff->days;
        $severity = $this->calculateExpirySeverity($daysRemaining);
        $sku = $batch->getSku();
        assert($sku instanceof SKU || null === $sku);

        $batchNo = $batch->getBatchNo();
        assert(is_string($batchNo));

        $skuId = null !== $sku ? $sku->getId() : 'Unknown';

        return [
            'sku' => $sku,
            'alert_type' => StockAlertType::EXPIRY_WARNING,
            'batch_no' => $batchNo,
            'expiry_date' => $expiryDate->format('Y-m-d'),
            'days_remaining' => $daysRemaining,
            'severity' => $severity,
            'message' => sprintf(
                'Batch %s for SKU %s will expire in %d days',
                $batchNo,
                $skuId,
                $daysRemaining
            ),
        ];
    }

    /**
     * 生成补货建议.
     *
     * @param array{
     *     target_days?: int,
     *     daily_usage?: float,
     *     safety_stock?: int
     * } $parameters
     *
     * @return array{
     *     spuId: string,
     *     currentStock: int,
     *     suggestedQuantity: int,
     *     urgency: string,
     *     reason: string
     * }
     */
    public function generateRestockSuggestion(string $spuId, array $parameters = []): array
    {
        $currentStock = $this->batchRepository->getTotalAvailableQuantity($spuId);
        $targetDays = $parameters['target_days'] ?? 30;
        $dailyUsage = $parameters['daily_usage'] ?? 1;
        $safetyStock = $parameters['safety_stock'] ?? 10;

        $targetStock = intval($dailyUsage * $targetDays + $safetyStock);
        $suggestedQuantity = max(0, $targetStock - $currentStock);

        // 计算紧急程度
        $daysRemaining = $dailyUsage > 0 ? intval($currentStock / $dailyUsage) : 999;
        $urgency = match (true) {
            $daysRemaining <= 3 => 'critical',
            $daysRemaining <= 7 => 'high',
            $daysRemaining <= 14 => 'medium',
            default => 'low',
        };

        $reason = sprintf(
            'Current stock (%d) can last %d days at current usage rate (%.1f/day). Suggested to restock %d units for %d-day supply.',
            $currentStock,
            $daysRemaining,
            $dailyUsage,
            $suggestedQuantity,
            $targetDays
        );

        return [
            'spuId' => $spuId,
            'currentStock' => $currentStock,
            'suggestedQuantity' => $suggestedQuantity,
            'urgency' => $urgency,
            'reason' => $reason,
        ];
    }

    /**
     * 更新预警.
     *
     * @param array{
     *     severity?: string,
     *     status?: string,
     *     threshold_value?: float,
     *     current_value?: float,
     *     resolved_note?: string,
     *     metadata?: array<string, mixed>
     * } $updateData
     */
    public function updateAlert(StockAlert $alert, array $updateData): StockAlert
    {
        if (isset($updateData['severity'])) {
            $alert->setSeverity(StockAlertSeverity::from($updateData['severity']));
        }

        if (isset($updateData['status'])) {
            $alert->setStatus(StockAlertStatus::from($updateData['status']));
        }

        if (isset($updateData['threshold_value'])) {
            $alert->setThresholdValue($updateData['threshold_value']);
        }

        if (isset($updateData['current_value'])) {
            $alert->setCurrentValue($updateData['current_value']);
        }

        if (isset($updateData['resolved_note'])) {
            $alert->setResolvedNote($updateData['resolved_note']);
        }

        if (isset($updateData['metadata'])) {
            $alert->setMetadata($updateData['metadata']);
        }

        $alert->setUpdateTime(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $alert;
    }

    /**
     * 删除预警.
     */
    public function deleteAlert(StockAlert $alert): void
    {
        $this->entityManager->remove($alert);
        $this->entityManager->flush();
    }

    /**
     * 获取活跃预警.
     *
     * @return array<StockAlert>
     */
    public function getActiveAlerts(): array
    {
        return $this->entityManager->getRepository(StockAlert::class)
            ->findBy(['status' => StockAlertStatus::ACTIVE], ['triggeredAt' => 'DESC'])
        ;
    }

    /**
     * 按类型获取预警.
     *
     * @return array<StockAlert>
     */
    public function getAlertsByType(StockAlertType $alertType): array
    {
        return $this->entityManager->getRepository(StockAlert::class)
            ->findBy(['alertType' => $alertType], ['triggeredAt' => 'DESC'])
        ;
    }

    /**
     * 获取预警汇总.
     *
     * @return array{
     *     totalAlerts: int,
     *     activeAlerts: int,
     *     byType: array<string, int>,
     *     bySeverity: array<string, int>
     * }
     */
    public function getAlertSummary(): array
    {
        $conn = $this->entityManager->getConnection();

        // 总数和活跃数
        $totalResult = $conn->executeQuery('SELECT COUNT(*) as total, COUNT(CASE WHEN status = "active" THEN 1 END) as active FROM stock_alerts')->fetchAssociative();
        if (false === $totalResult) {
            $totalResult = ['total' => 0, 'active' => 0];
        }

        // 按类型统计
        $typeResults = $conn->executeQuery('SELECT alert_type, COUNT(*) as count FROM stock_alerts WHERE status = "active" GROUP BY alert_type')->fetchAllAssociative();
        /** @var array<string, int> $byType */
        $byType = [];
        foreach ($typeResults as $row) {
            if (isset($row['alert_type'], $row['count'])) {
                $alertType = $row['alert_type'];
                $count = $row['count'];
                assert(is_string($alertType));
                assert(is_int($count) || is_string($count));
                $byType[$alertType] = (int) $count;
            }
        }

        // 按严重程度统计
        $severityResults = $conn->executeQuery('SELECT severity, COUNT(*) as count FROM stock_alerts WHERE status = "active" GROUP BY severity')->fetchAllAssociative();
        /** @var array<string, int> $bySeverity */
        $bySeverity = [];
        foreach ($severityResults as $row) {
            if (isset($row['severity'], $row['count'])) {
                $severity = $row['severity'];
                $count = $row['count'];
                assert(is_string($severity));
                assert(is_int($count) || is_string($count));
                $bySeverity[$severity] = (int) $count;
            }
        }

        $total = $totalResult['total'] ?? 0;
        $active = $totalResult['active'] ?? 0;
        assert(is_int($total) || is_string($total));
        assert(is_int($active) || is_string($active));

        return [
            'totalAlerts' => (int) $total,
            'activeAlerts' => (int) $active,
            'byType' => $byType,
            'bySeverity' => $bySeverity,
        ];
    }

    /**
     * 计算预警严重程度.
     */
    public function calculateAlertSeverity(StockAlertType $alertType, float $currentValue, float $thresholdValue): string
    {
        return match ($alertType) {
            StockAlertType::LOW_STOCK, StockAlertType::OUT_OF_STOCK => $this->calculateLowStockSeverity($currentValue, $thresholdValue),
            StockAlertType::HIGH_STOCK => 'medium', // 高库存通常不是严重问题
            StockAlertType::EXPIRY_WARNING => $this->calculateExpirySeverity($currentValue), // currentValue 为剩余天数
            StockAlertType::QUALITY_ISSUE => 'critical', // 质量问题总是严重问题
        };
    }

    /**
     * 检查预警功能是否启用.
     */
    public function isAlertEnabled(): bool
    {
        return (bool) ($_ENV['STOCK_ALERT_ENABLED'] ?? true);
    }

    /**
     * 获取支持的预警类型.
     *
     * @return array<StockAlertType>
     */
    public function getSupportedAlertTypes(): array
    {
        return StockAlertType::cases();
    }

    /**
     * 获取默认阈值
     *
     * @return array{low_stock: int, high_stock: int, expiry_warning_days: int}
     */
    public function getDefaultThresholds(): array
    {
        $lowThreshold = $_ENV['STOCK_ALERT_LOW_THRESHOLD'] ?? 100;
        $highThreshold = $_ENV['STOCK_ALERT_HIGH_THRESHOLD'] ?? 10000;
        $expiryDays = $_ENV['STOCK_ALERT_EXPIRY_DAYS'] ?? 30;

        assert(is_int($lowThreshold) || is_string($lowThreshold) || is_numeric($lowThreshold));
        assert(is_int($highThreshold) || is_string($highThreshold) || is_numeric($highThreshold));
        assert(is_int($expiryDays) || is_string($expiryDays) || is_numeric($expiryDays));

        return [
            'low_stock' => (int) $lowThreshold,
            'high_stock' => (int) $highThreshold,
            'expiry_warning_days' => (int) $expiryDays,
        ];
    }

    /**
     * 计算低库存严重程度.
     */
    private function calculateLowStockSeverity(float $currentStock, float $threshold): string
    {
        if ($currentStock <= 0) {
            return 'critical';
        }

        $percentage = $currentStock / $threshold;

        return match (true) {
            $percentage <= 0.1 => 'critical', // 10% 以下
            $percentage <= 0.2 => 'high',     // 20% 以下
            $percentage <= 0.5 => 'medium',   // 50% 以下
            default => 'low',
        };
    }

    /**
     * 计算过期预警严重程度.
     */
    private function calculateExpirySeverity(float $daysRemaining): string
    {
        return match (true) {
            $daysRemaining <= 3 => 'critical',
            $daysRemaining <= 7 => 'high',
            $daysRemaining <= 15 => 'medium',
            default => 'low',
        };
    }

    /**
     * 验证预警数据.
     *
     * @param array{sku?: SKU, spu_id?: string, alert_type?: StockAlertType, message?: string} $data
     *
     * @throws InvalidArgumentException
     */
    private function validateAlertData(array $data): void
    {
        if (!isset($data['sku'])) {
            throw new InvalidArgumentException('SKU is required');
        }

        if (!isset($data['alert_type']) || '' === $data['alert_type']) {
            throw new InvalidArgumentException('Alert type is required');
        }

        if (!($data['alert_type'] instanceof StockAlertType)) {
            throw new InvalidArgumentException('Invalid alert type. Must be a StockAlertType enum');
        }

        if (!isset($data['message']) || '' === $data['message']) {
            throw new InvalidArgumentException('Message is required');
        }

        if (isset($data['severity']) && !in_array($data['severity'], self::SEVERITY_LEVELS, true)) {
            throw new InvalidArgumentException(sprintf('Invalid severity level. Must be one of: %s', implode(', ', self::SEVERITY_LEVELS)));
        }
    }
}
