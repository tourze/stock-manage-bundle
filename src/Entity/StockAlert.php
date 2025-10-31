<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;

/**
 * 库存预警实体
 * 用于记录各种库存预警信息
 * 贫血模型：只包含数据和getter/setter，不包含业务逻辑.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock_alerts', options: ['comment' => '库存预警表'])]
class StockAlert implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[IndexColumn(name: 'idx_alert_type')]
    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockAlertType::class, options: ['comment' => '预警类型'])]
    #[Assert\NotNull(message: '预警类型不能为空')]
    #[Assert\Choice(callback: [StockAlertType::class, 'cases'], message: '预警类型必须是有效的预警类型')]
    private StockAlertType $alertType;

    #[IndexColumn(name: 'idx_severity')]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: StockAlertSeverity::class, options: ['comment' => '严重程度：low=低，medium=中，high=高，critical=严重'])]
    #[Assert\Choice(callback: [StockAlertSeverity::class, 'cases'], message: '严重程度必须是有效的级别')]
    private StockAlertSeverity $severity = StockAlertSeverity::MEDIUM;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: StockAlertStatus::class, options: ['comment' => '状态：active=激活，resolved=已解决，dismissed=已忽略'])]
    #[Assert\Choice(callback: [StockAlertStatus::class, 'cases'], message: '状态必须是有效的状态')]
    private StockAlertStatus $status = StockAlertStatus::ACTIVE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '阈值'])]
    #[Assert\PositiveOrZero(message: '阈值不能为负数')]
    private ?float $thresholdValue = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '当前值'])]
    #[Assert\PositiveOrZero(message: '当前值不能为负数')]
    private ?float $currentValue = null;

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '预警消息'])]
    #[Assert\NotBlank(message: '预警消息不能为空')]
    #[Assert\Length(max: 500, maxMessage: '预警消息不能超过500个字符')]
    private string $message;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '解决备注'])]
    #[Assert\Length(max: 500, maxMessage: '解决备注不能超过500个字符')]
    private ?string $resolvedNote = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '位置ID'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展数据'])]
    #[Assert\Type(type: 'array', message: '扩展数据必须是数组')]
    private ?array $metadata = null;

    #[IndexColumn(name: 'idx_triggered_at')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '触发时间'])]
    #[Assert\NotNull(message: '触发时间不能为空')]
    private \DateTimeImmutable $triggeredAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '解决时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
        $this->triggeredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?SKU
    {
        return $this->sku;
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    public function getAlertType(): StockAlertType
    {
        return $this->alertType;
    }

    public function setAlertType(StockAlertType $alertType): void
    {
        $this->alertType = $alertType;
    }

    public function getSeverity(): StockAlertSeverity
    {
        return $this->severity;
    }

    public function setSeverity(StockAlertSeverity $severity): void
    {
        $this->severity = $severity;
    }

    public function getStatus(): StockAlertStatus
    {
        return $this->status;
    }

    public function setStatus(StockAlertStatus $status): void
    {
        $this->status = $status;

        if (StockAlertStatus::RESOLVED === $status) {
            $this->resolvedAt = new \DateTimeImmutable();
        }
    }

    public function getThresholdValue(): ?float
    {
        return $this->thresholdValue;
    }

    public function setThresholdValue(?float $thresholdValue): void
    {
        $this->thresholdValue = $thresholdValue;
    }

    public function getCurrentValue(): ?float
    {
        return $this->currentValue;
    }

    public function setCurrentValue(?float $currentValue): void
    {
        $this->currentValue = $currentValue;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getResolvedNote(): ?string
    {
        return $this->resolvedNote;
    }

    public function setResolvedNote(?string $resolvedNote): void
    {
        $this->resolvedNote = $resolvedNote;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function setLocationId(?string $locationId): void
    {
        $this->locationId = $locationId;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getTriggeredAt(): \DateTimeImmutable
    {
        return $this->triggeredAt;
    }

    public function setTriggeredAt(\DateTimeImmutable $triggeredAt): void
    {
        $this->triggeredAt = $triggeredAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): void
    {
        $this->resolvedAt = $resolvedAt;
    }

    public function getSpuId(): ?string
    {
        return $this->sku?->getId();
    }

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->alertType->value, $this->message);
    }
}
