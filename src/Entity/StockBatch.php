<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Repository\StockBatchRepository;

#[ORM\Entity(repositoryClass: StockBatchRepository::class)]
#[ORM\Table(name: 'stock_batches', options: ['comment' => '库存批次表'])]
class StockBatch implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\ManyToOne(targetEntity: SKU::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[IndexColumn(name: 'idx_batch_no')]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '批次号'])]
    #[Assert\NotBlank(message: '批次号不能为空')]
    #[Assert\Length(max: 100, maxMessage: '批次号不能超过100个字符')]
    private string $batchNo = '';

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '总数量'])]
    #[Assert\PositiveOrZero(message: '总数量不能为负数')]
    private int $quantity = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '可用数量'])]
    #[Assert\PositiveOrZero(message: '可用数量不能为负数')]
    private int $availableQuantity = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '预留数量'])]
    #[Assert\PositiveOrZero(message: '预留数量不能为负数')]
    private int $reservedQuantity = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '锁定数量'])]
    #[Assert\PositiveOrZero(message: '锁定数量不能为负数')]
    private int $lockedQuantity = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['comment' => '单位成本'])]
    #[Assert\PositiveOrZero(message: '单位成本不能为负数')]
    private float $unitCost = 0.00;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '质量等级'])]
    #[Assert\NotBlank(message: '质量等级不能为空')]
    #[Assert\Length(max: 20, maxMessage: '质量等级不能超过20个字符')]
    #[Assert\Choice(choices: ['S', 'A', 'B', 'C'], message: '质量等级必须是S、A、B或C')]
    private string $qualityLevel = 'A';

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '状态'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Length(max: 20, maxMessage: '状态不能超过20个字符')]
    #[Assert\Choice(choices: ['pending', 'available', 'expired', 'damaged', 'consumed'], message: '状态必须是有效的批次状态')]
    private string $status = 'pending';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '生产日期'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $productionDate = null;

    #[IndexColumn(name: 'idx_expiry_date')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '过期日期'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $expiryDate = null;

    #[IndexColumn(name: 'idx_location')]
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '位置ID'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展属性'])]
    #[Assert\Type(type: 'array', message: '扩展属性必须是数组')]
    private ?array $attributes = null;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
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

    public function getBatchNo(): string
    {
        return $this->batchNo;
    }

    public function setBatchNo(string $batchNo): void
    {
        $this->batchNo = $batchNo;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getAvailableQuantity(): int
    {
        return $this->availableQuantity;
    }

    public function setAvailableQuantity(int $availableQuantity): void
    {
        $this->availableQuantity = $availableQuantity;
    }

    public function getReservedQuantity(): int
    {
        return $this->reservedQuantity;
    }

    public function setReservedQuantity(int $reservedQuantity): void
    {
        $this->reservedQuantity = $reservedQuantity;
    }

    public function getLockedQuantity(): int
    {
        return $this->lockedQuantity;
    }

    public function setLockedQuantity(int $lockedQuantity): void
    {
        $this->lockedQuantity = $lockedQuantity;
    }

    public function getUnitCost(): float
    {
        return $this->unitCost;
    }

    public function setUnitCost(float $unitCost): void
    {
        $this->unitCost = $unitCost;
    }

    public function getQualityLevel(): string
    {
        return $this->qualityLevel;
    }

    public function setQualityLevel(string $qualityLevel): void
    {
        $this->qualityLevel = $qualityLevel;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getProductionDate(): ?\DateTimeImmutable
    {
        return $this->productionDate;
    }

    public function setProductionDate(?\DateTimeImmutable $productionDate): void
    {
        $this->productionDate = $productionDate;
    }

    public function getExpiryDate(): ?\DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(?\DateTimeImmutable $expiryDate): void
    {
        $this->expiryDate = $expiryDate;
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
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /** @param array<string, mixed> $attributes */
    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getSpuId(): ?string
    {
        return $this->sku?->getId();
    }

    public function __toString(): string
    {
        return $this->batchNo;
    }
}
