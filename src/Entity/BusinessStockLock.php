<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\CreatedByAware;

#[ORM\Entity]
#[ORM\Table(name: 'business_stock_locks', options: ['comment' => '业务库存锁定表'])]
#[ORM\Index(columns: ['business_id', 'status'], name: 'business_stock_locks_idx_business_status')]
class BusinessStockLock implements \Stringable
{
    use CreatedByAware;
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '批次ID列表'])]
    #[Assert\Type(type: 'array', message: '批次ID列表必须是数组')]
    private array $batchIds = [];

    /**
     * @var array<int>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '数量列表'])]
    #[Assert\Type(type: 'array', message: '数量列表必须是数组')]
    private array $quantities = [];

    #[ORM\Column(type: Types::STRING, length: 30, options: ['comment' => '锁定类型'])]
    #[Assert\NotBlank(message: '类型不能为空')]
    #[Assert\Length(max: 30, maxMessage: '类型不能超过30个字符')]
    #[Assert\Choice(choices: ['order', 'promotion', 'system', 'manual'], message: '类型必须是有效的锁定类型')]
    #[IndexColumn]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '业务ID'])]
    #[Assert\NotBlank(message: '业务ID不能为空')]
    #[Assert\Length(max: 100, maxMessage: '业务ID不能超过100个字符')]
    private string $businessId;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '锁定原因'])]
    #[Assert\NotBlank(message: '原因不能为空')]
    #[Assert\Length(max: 255, maxMessage: '原因不能超过255个字符')]
    private string $reason;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '锁定状态'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Length(max: 20, maxMessage: '状态不能超过20个字符')]
    #[Assert\Choice(choices: ['active', 'expired', 'released'], message: '状态必须是有效的锁定状态')]
    private string $status = 'active';

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '过期时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $expiresTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '释放时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $releasedTime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '释放原因'])]
    #[Assert\Length(max: 255, maxMessage: '释放原因不能超过255个字符')]
    private ?string $releaseReason = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '释放人'])]
    #[Assert\Length(max: 100, maxMessage: '释放人不能超过100个字符')]
    private ?string $releasedBy = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '扩展元数据'])]
    #[Assert\Type(type: 'array', message: '元数据必须是数组')]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->status = 'active';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return array<string> */
    public function getBatchIds(): array
    {
        return $this->batchIds;
    }

    /** @param array<string> $batchIds */
    public function setBatchIds(array $batchIds): void
    {
        $this->batchIds = $batchIds;
    }

    /** @return array<int> */
    public function getQuantities(): array
    {
        return $this->quantities;
    }

    /** @param array<int> $quantities */
    public function setQuantities(array $quantities): void
    {
        $this->quantities = $quantities;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
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

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getExpiresTime(): ?\DateTimeImmutable
    {
        return $this->expiresTime;
    }

    public function setExpiresTime(?\DateTimeImmutable $expiresTime): void
    {
        $this->expiresTime = $expiresTime;
    }

    public function getReleasedTime(): ?\DateTimeImmutable
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

    public function getReleasedBy(): ?string
    {
        return $this->releasedBy;
    }

    public function setReleasedBy(?string $releasedBy): void
    {
        $this->releasedBy = $releasedBy;
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

    public function getTotalLockedQuantity(): int
    {
        $total = 0;
        foreach ($this->quantities as $quantity) {
            $total += (int) $quantity;
        }

        return $total;
    }

    public function isExpired(): bool
    {
        if (null === $this->expiresTime) {
            return false;
        }

        return $this->expiresTime < new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return 'active' === $this->status && !$this->isExpired();
    }

    public function __toString(): string
    {
        return sprintf('BusinessLock[%s]-%s', $this->businessId, $this->type);
    }
}
