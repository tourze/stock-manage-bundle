<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockAdjustmentStatus;
use Tourze\StockManageBundle\Enum\StockAdjustmentType;
use Tourze\StockManageBundle\Repository\StockAdjustmentRepository;

#[ORM\Entity(repositoryClass: StockAdjustmentRepository::class)]
#[ORM\Table(name: 'stock_adjustments', options: ['comment' => '库存调整表'])]
class StockAdjustment implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键id'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true, options: ['comment' => '调整单号'])]
    #[Assert\NotBlank(message: '调整单号不能为空')]
    #[Assert\Length(max: 50, maxMessage: '调整单号不能超过50个字符')]
    #[IndexColumn(name: 'idx_adjustment_no')]
    private string $adjustmentNo;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockAdjustmentType::class, options: ['comment' => '调整类型'])]
    #[Assert\NotNull(message: '调整类型不能为空')]
    #[Assert\Choice(callback: [StockAdjustmentType::class, 'cases'], message: '调整类型必须是有效的类型')]
    #[IndexColumn(name: 'idx_adjustment_type')]
    private StockAdjustmentType $type;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockAdjustmentStatus::class, options: ['comment' => '调整状态'])]
    #[Assert\NotNull(message: '调整状态不能为空')]
    #[Assert\Choice(callback: [StockAdjustmentStatus::class, 'cases'], message: '调整状态必须是有效的状态')]
    private StockAdjustmentStatus $status = StockAdjustmentStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '调整明细'])]
    #[Assert\Type(type: 'array', message: '调整明细必须是数组')]
    private array $items = [];

    #[ORM\Column(type: Types::TEXT, options: ['comment' => '调整原因'])]
    #[Assert\NotBlank(message: '调整原因不能为空')]
    #[Assert\Length(max: 2000, maxMessage: '调整原因不能超过2000个字符')]
    private string $reason;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '总调整数量'])]
    #[Assert\Range(min: -999999, max: 999999, notInRangeMessage: '总调整数量必须在合理范围内')]
    private int $totalAdjusted = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '成本影响金额'])]
    #[Assert\Range(min: -9999999.99, max: 9999999.99, notInRangeMessage: '成本影响金额必须在合理范围内')]
    private float $costImpact = 0.00;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '仓库位置'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '操作人'])]
    #[Assert\NotBlank(message: '操作人不能为空')]
    #[Assert\Length(max: 50, maxMessage: '操作人不能超过50个字符')]
    private string $operator;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '审批人'])]
    #[Assert\Length(max: 50, maxMessage: '审批人不能超过50个字符')]
    private ?string $approver = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '审批时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $approvedTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 1000, maxMessage: '备注不能超过1000个字符')]
    private ?string $notes = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '附件'])]
    #[Assert\Type(type: 'array', message: '附件必须是数组')]
    private ?array $attachments = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array', message: '元数据必须是数组')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $completedTime = null;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdjustmentNo(): string
    {
        return $this->adjustmentNo;
    }

    public function setAdjustmentNo(string $adjustmentNo): void
    {
        $this->adjustmentNo = $adjustmentNo;
    }

    public function getType(): StockAdjustmentType
    {
        return $this->type;
    }

    public function setType(StockAdjustmentType $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): StockAdjustmentStatus
    {
        return $this->status;
    }

    public function setStatus(StockAdjustmentStatus $status): void
    {
        $this->status = $status;
    }

    public function getSku(): ?SKU
    {
        return $this->sku;
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    /** @return array<string, mixed> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param array<string, mixed> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->calculateTotals();
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getTotalAdjusted(): int
    {
        return $this->totalAdjusted;
    }

    public function getCostImpact(): float
    {
        return $this->costImpact;
    }

    public function setCostImpact(float $costImpact): void
    {
        $this->costImpact = $costImpact;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function setLocationId(?string $locationId): void
    {
        $this->locationId = $locationId;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    public function getApprover(): ?string
    {
        return $this->approver;
    }

    public function setApprover(?string $approver): void
    {
        $this->approver = $approver;
    }

    public function getApprovedTime(): ?\DateTimeImmutable
    {
        return $this->approvedTime;
    }

    public function setApprovedTime(?\DateTimeImmutable $approvedTime): void
    {
        $this->approvedTime = $approvedTime;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    /** @return array<string, mixed>|null */
    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    /** @param array<string, mixed> $attachments */
    public function setAttachments(?array $attachments): void
    {
        $this->attachments = $attachments;
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

    public function getCompletedTime(): ?\DateTimeImmutable
    {
        return $this->completedTime;
    }

    public function setCompletedTime(?\DateTimeImmutable $completedTime): void
    {
        $this->completedTime = $completedTime;
    }

    public function isPending(): bool
    {
        return StockAdjustmentStatus::PENDING === $this->status;
    }

    public function isProcessing(): bool
    {
        return StockAdjustmentStatus::PROCESSING === $this->status;
    }

    public function isCompleted(): bool
    {
        return StockAdjustmentStatus::COMPLETED === $this->status;
    }

    public function isCancelled(): bool
    {
        return StockAdjustmentStatus::CANCELLED === $this->status;
    }

    private function calculateTotals(): void
    {
        $this->totalAdjusted = 0;
        foreach ($this->items as $item) {
            assert(is_array($item));
            if (isset($item['adjustment_quantity'])) {
                $quantity = $item['adjustment_quantity'];
                assert(is_numeric($quantity));
                $this->totalAdjusted += (int) $quantity;
            } elseif (isset($item['difference'])) {
                $difference = $item['difference'];
                assert(is_numeric($difference));
                $this->totalAdjusted += (int) $difference;
            }
        }
    }

    public function __toString(): string
    {
        return $this->adjustmentNo;
    }
}
