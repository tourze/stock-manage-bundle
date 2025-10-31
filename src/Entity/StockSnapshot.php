<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;

#[ORM\Entity]
#[ORM\Table(name: 'stock_snapshots', options: ['comment' => '库存快照表'])]
class StockSnapshot implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '快照号'])]
    #[Assert\NotBlank(message: '快照号不能为空')]
    #[Assert\Length(max: 50, maxMessage: '快照号不能超过50个字符')]
    private string $snapshotNo;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[IndexColumn(name: 'idx_snapshot_type')]
    #[ORM\Column(type: Types::STRING, length: 30, options: ['comment' => '快照类型'])]
    #[Assert\NotBlank(message: '类型不能为空')]
    #[Assert\Length(max: 30, maxMessage: '类型不能超过30个字符')]
    #[Assert\Choice(choices: ['daily', 'weekly', 'monthly', 'manual', 'system'], message: '类型必须是有效的快照类型')]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['comment' => '触发方式'])]
    #[Assert\NotBlank(message: '触发方式不能为空')]
    #[Assert\Length(max: 30, maxMessage: '触发方式不能超过30个字符')]
    #[Assert\Choice(choices: ['scheduled', 'manual', 'event', 'api'], message: '触发方式必须是有效的触发方式')]
    private string $triggerMethod;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '汇总信息'])]
    #[Assert\Type(type: 'array', message: '汇总信息必须是数组')]
    private array $summary = [];

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '详细信息'])]
    #[Assert\Type(type: 'array', message: '详细信息必须是数组')]
    private ?array $details = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '总数量'])]
    #[Assert\PositiveOrZero(message: '总数量不能为负数')]
    private int $totalQuantity = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['comment' => '总价值'])]
    #[Assert\PositiveOrZero(message: '总价值不能为负数')]
    private float $totalValue = 0.0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '商品数量'])]
    #[Assert\PositiveOrZero(message: '商品数量不能为负数')]
    private int $productCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '批次数量'])]
    #[Assert\PositiveOrZero(message: '批次数量不能为负数')]
    private int $batchCount = 0;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '位置ID'])]
    #[Assert\Length(max: 100, maxMessage: '位置ID不能超过100个字符')]
    private ?string $locationId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '操作人'])]
    #[Assert\Length(max: 100, maxMessage: '操作人不能超过100个字符')]
    private ?string $operator = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 2000, maxMessage: '备注不能超过2000个字符')]
    private ?string $notes = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array', message: '元数据必须是数组')]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '有效期至'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $validUntil = null;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSnapshotNo(): string
    {
        return $this->snapshotNo;
    }

    public function setSnapshotNo(string $snapshotNo): void
    {
        $this->snapshotNo = $snapshotNo;
    }

    public function getSku(): ?SKU
    {
        return $this->sku;
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getTriggerMethod(): string
    {
        return $this->triggerMethod;
    }

    public function setTriggerMethod(string $triggerMethod): void
    {
        $this->triggerMethod = $triggerMethod;
    }

    /** @return array<string, mixed> */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /** @param array<string, mixed> $summary */
    public function setSummary(array $summary): void
    {
        $this->summary = $summary;
    }

    /** @return array<string, mixed>|null */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /** @param array<string, mixed> $details */
    public function setDetails(?array $details): void
    {
        $this->details = $details;
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
    }

    public function setTotalQuantity(int $totalQuantity): void
    {
        $this->totalQuantity = $totalQuantity;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    public function setTotalValue(float $totalValue): void
    {
        $this->totalValue = $totalValue;
    }

    public function getProductCount(): int
    {
        return $this->productCount;
    }

    public function setProductCount(int $productCount): void
    {
        $this->productCount = $productCount;
    }

    public function getBatchCount(): int
    {
        return $this->batchCount;
    }

    public function setBatchCount(int $batchCount): void
    {
        $this->batchCount = $batchCount;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function setLocationId(?string $locationId): void
    {
        $this->locationId = $locationId;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
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
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed> $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function __toString(): string
    {
        return $this->snapshotNo;
    }
}
