<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;
use Tourze\StockManageBundle\Repository\StockReservationRepository;

#[ORM\Entity(repositoryClass: StockReservationRepository::class)]
#[ORM\Table(name: 'stock_reservations', options: ['comment' => '库存预定表'])]
class StockReservation implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '预定数量'])]
    #[Assert\PositiveOrZero(message: '预定数量不能为负数')]
    private int $quantity;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockReservationType::class, options: ['comment' => '预定类型'])]
    #[Assert\NotNull(message: '类型不能为空')]
    #[Assert\Choice(callback: [StockReservationType::class, 'cases'], message: '类型必须是有效的预定类型')]
    private StockReservationType $type;

    #[IndexColumn(name: 'idx_business_id')]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '业务ID'])]
    #[Assert\NotBlank(message: '业务ID不能为空')]
    #[Assert\Length(max: 100, maxMessage: '业务ID不能超过100个字符')]
    private string $businessId;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: StockReservationStatus::class, options: ['comment' => '预定状态'])]
    #[Assert\NotNull(message: '预定状态不能为空')]
    #[Assert\Choice(callback: [StockReservationStatus::class, 'cases'], message: '预定状态必须是有效的状态')]
    private StockReservationStatus $status = StockReservationStatus::PENDING;

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '过期时间'])]
    #[Assert\NotNull(message: '过期时间不能为空')]
    private \DateTimeImmutable $expiresTime;

    /**
     * @var array<string, int>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '批次分配'])]
    #[Assert\Type(type: 'array', message: '批次分配必须是数组')]
    private ?array $batchAllocations = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '确认时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $confirmedTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '释放时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $releasedTime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '释放原因'])]
    #[Assert\Length(max: 255, maxMessage: '释放原因不能超过255个字符')]
    private ?string $releaseReason = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '操作人'])]
    #[Assert\Length(max: 100, maxMessage: '操作人不能超过100个字符')]
    private ?string $operator = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 1000, maxMessage: '备注不能超过1000个字符')]
    private ?string $notes = null;

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

    public function getSpuId(): ?string
    {
        return $this->sku?->getId();
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getType(): StockReservationType
    {
        return $this->type;
    }

    public function setType(StockReservationType $type): void
    {
        $this->type = $type;
    }

    public function getBusinessId(): string
    {
        return $this->businessId;
    }

    public function setBusinessId(string $businessId): void
    {
        $this->businessId = $businessId;
    }

    public function getStatus(): StockReservationStatus
    {
        return $this->status;
    }

    public function setStatus(StockReservationStatus $status): void
    {
        $this->status = $status;
    }

    public function getExpiresTime(): \DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(\DateTimeImmutable $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
    }

    /** @return array<string, int>|null */
    public function getBatchAllocations(): ?array
    {
        return $this->batchAllocations;
    }

    /** @param array<string, int> $batchAllocations */
    public function setBatchAllocations(?array $batchAllocations): void
    {
        $this->batchAllocations = $batchAllocations;
    }

    public function getConfirmedTime(): ?\DateTimeImmutable
    {
        return $this->confirmedTime;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedTime;
    }

    public function setConfirmedTime(?\DateTimeImmutable $confirmedTime): void
    {
        $this->confirmedTime = $confirmedTime;
    }

    public function getReleasedTime(): ?\DateTimeImmutable
    {
        return $this->releasedTime;
    }

    public function getReleasedAt(): ?\DateTimeImmutable
    {
        return $this->releasedTime;
    }

    public function setReleasedTime(?\DateTimeImmutable $releasedTime): void
    {
        $this->releasedTime = $releasedTime;
    }

    public function getReleaseReason(): ?string
    {
        return $this->releaseReason;
    }

    public function setReleaseReason(?string $releaseReason): void
    {
        $this->releaseReason = $releaseReason;
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

    public function isExpired(): bool
    {
        return $this->expiresTime < new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return StockReservationStatus::PENDING === $this->status && !$this->isExpired();
    }

    public function getAllocatedQuantity(): int
    {
        if (null === $this->batchAllocations) {
            return 0;
        }

        $total = 0;
        foreach ($this->batchAllocations as $allocation) {
            $total += (int) $allocation;
        }

        return $total;
    }

    public function __toString(): string
    {
        return sprintf('Reservation[%s]', $this->businessId);
    }
}
