<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity]
#[ORM\Table(name: 'operational_stock_locks', options: ['comment' => '操作库存锁定表'])]
#[ORM\Index(columns: ['operation_type', 'status'], name: 'operational_stock_locks_idx_operation_status')]
class OperationalStockLock implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '锁定批次ID列表'])]
    #[Assert\Type(type: 'array', message: '批次ID列表必须是数组')]
    private array $batchIds = [];

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '操作类型'])]
    #[Assert\NotBlank(message: '操作类型不能为空')]
    #[Assert\Length(max: 50, maxMessage: '操作类型不能超过50个字符')]
    #[Assert\Choice(choices: ['inventory', 'adjustment', 'maintenance', 'audit'], message: '操作类型必须是有效的操作类型')]
    private string $operationType;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '操作人'])]
    #[Assert\NotBlank(message: '操作人不能为空')]
    #[Assert\Length(max: 100, maxMessage: '操作人不能超过100个字符')]
    #[IndexColumn]
    private string $operator;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '锁定原因'])]
    #[Assert\NotBlank(message: '原因不能为空')]
    #[Assert\Length(max: 255, maxMessage: '原因不能超过255个字符')]
    private string $reason;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '状态'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Length(max: 20, maxMessage: '状态不能超过20个字符')]
    #[Assert\Choice(choices: ['active', 'completed', 'cancelled'], message: '状态必须是有效的操作状态')]
    private string $status = 'active';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '优先级'])]
    #[Assert\NotBlank(message: '优先级不能为空')]
    #[Assert\Length(max: 20, maxMessage: '优先级不能超过20个字符')]
    #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'], message: '优先级必须是有效的优先级')]
    private string $priority = 'normal';

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '预计持续时间(分钟)'])]
    #[Assert\PositiveOrZero(message: '预计持续时间不能为负数')]
    private ?int $estimatedDuration = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '部门'])]
    #[Assert\Length(max: 100, maxMessage: '部门不能超过100个字符')]
    #[IndexColumn]
    private ?string $department = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '位置ID'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $completedTime = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '完成人'])]
    #[Assert\Length(max: 100, maxMessage: '完成人不能超过100个字符')]
    private ?string $completedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '完成备注'])]
    #[Assert\Length(max: 2000, maxMessage: '完成备注不能超过2000个字符')]
    private ?string $completionNotes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '释放时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $releasedTime = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '释放原因'])]
    #[Assert\Length(max: 255, maxMessage: '释放原因不能超过255个字符')]
    private ?string $releaseReason = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '操作结果'])]
    #[Assert\Type(type: 'array', message: '操作结果必须是数组')]
    private ?array $operationResult = null;

    public function __construct()
    {
        $this->status = 'active';
        $this->priority = 'normal';
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

    public function getOperationType(): string
    {
        return $this->operationType;
    }

    public function setOperationType(string $operationType): void
    {
        $this->operationType = $operationType;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
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

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): void
    {
        $this->priority = $priority;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): void
    {
        $this->estimatedDuration = $estimatedDuration;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): void
    {
        $this->department = $department;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function setLocationId(?string $locationId): void
    {
        $this->locationId = $locationId;
    }

    public function getCompletedTime(): ?\DateTimeImmutable
    {
        return $this->completedTime;
    }

    public function setCompletedTime(?\DateTimeImmutable $completedTime): void
    {
        $this->completedTime = $completedTime;
    }

    public function getCompletedBy(): ?string
    {
        return $this->completedBy;
    }

    public function setCompletedBy(?string $completedBy): void
    {
        $this->completedBy = $completedBy;
    }

    public function getCompletionNotes(): ?string
    {
        return $this->completionNotes;
    }

    public function setCompletionNotes(?string $completionNotes): void
    {
        $this->completionNotes = $completionNotes;
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

    /** @return array<string, mixed>|null */
    public function getOperationResult(): ?array
    {
        return $this->operationResult;
    }

    /** @param array<string, mixed> $operationResult */
    public function setOperationResult(?array $operationResult): void
    {
        $this->operationResult = $operationResult;
    }

    public function isActive(): bool
    {
        return 'active' === $this->status;
    }

    public function __toString(): string
    {
        return sprintf('OpLock[%s]-%s', $this->operator, $this->operationType);
    }
}
