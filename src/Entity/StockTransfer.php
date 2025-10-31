<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockTransferStatus;

#[ORM\Entity]
#[ORM\Table(name: 'stock_transfers', options: ['comment' => '库存调拨记录表'])]
class StockTransfer implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[IndexColumn(name: 'idx_transfer_no')]
    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '调拨单号'])]
    #[Assert\NotBlank(message: '调拨单号不能为空')]
    #[Assert\Length(max: 100, maxMessage: '调拨单号不能超过100个字符')]
    private string $transferNo;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '源位置'])]
    #[Assert\NotBlank(message: '源位置不能为空')]
    #[Assert\Length(max: 50, maxMessage: '源位置不能超过50个字符')]
    private string $fromLocation;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '目标位置'])]
    #[Assert\NotBlank(message: '目标位置不能为空')]
    #[Assert\Length(max: 50, maxMessage: '目标位置不能超过50个字符')]
    private string $toLocation;

    /**
     * @var list<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '调拨明细'])]
    #[Assert\Type(type: 'array', message: '调拨明细必须是数组')]
    private array $items = [];

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '调拨总数量'])]
    #[Assert\PositiveOrZero(message: '调拨总数量不能为负数')]
    private int $totalQuantity = 0;

    #[IndexColumn(name: 'idx_transfer_status')]
    #[ORM\Column(type: Types::STRING, length: 20, enumType: StockTransferStatus::class, options: ['comment' => '调拨状态'])]
    #[Assert\Choice(callback: [StockTransferStatus::class, 'cases'])]
    private StockTransferStatus $status = StockTransferStatus::PENDING;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '发起人'])]
    #[Assert\Length(max: 100, maxMessage: '发起人不能超过100个字符')]
    private ?string $initiator = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '接收人'])]
    #[Assert\Length(max: 100, maxMessage: '接收人不能超过100个字符')]
    private ?string $receiver = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '调拨原因'])]
    #[Assert\Length(max: 1000, maxMessage: '调拨原因不能超过1000个字符')]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '发出时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $shippedTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '接收时间'])]
    #[Assert\Valid]
    private ?\DateTimeImmutable $receivedTime = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '附加信息'])]
    #[Assert\Type(type: 'array', message: '附加信息必须是数组')]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->createTime = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTransferNo(): string
    {
        return $this->transferNo;
    }

    public function setTransferNo(string $transferNo): void
    {
        $this->transferNo = $transferNo;
    }

    public function getSku(): ?SKU
    {
        return $this->sku;
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    public function getFromLocation(): string
    {
        return $this->fromLocation;
    }

    public function setFromLocation(string $fromLocation): void
    {
        $this->fromLocation = $fromLocation;
    }

    public function getToLocation(): string
    {
        return $this->toLocation;
    }

    public function setToLocation(string $toLocation): void
    {
        $this->toLocation = $toLocation;
    }

    /** @return list<array<string, mixed>> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param list<array<string, mixed>> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->calculateTotalQuantity();
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
    }

    public function getStatus(): StockTransferStatus
    {
        return $this->status;
    }

    public function setStatus(StockTransferStatus $status): void
    {
        $this->status = $status;
    }

    public function getInitiator(): ?string
    {
        return $this->initiator;
    }

    public function setInitiator(?string $initiator): void
    {
        $this->initiator = $initiator;
    }

    public function getReceiver(): ?string
    {
        return $this->receiver;
    }

    public function setReceiver(?string $receiver): void
    {
        $this->receiver = $receiver;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getShippedTime(): ?\DateTimeImmutable
    {
        return $this->shippedTime;
    }

    public function setShippedTime(?\DateTimeImmutable $shippedTime): void
    {
        $this->shippedTime = $shippedTime;
    }

    public function getReceivedTime(): ?\DateTimeImmutable
    {
        return $this->receivedTime;
    }

    public function setReceivedTime(?\DateTimeImmutable $receivedTime): void
    {
        $this->receivedTime = $receivedTime;
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

    private function calculateTotalQuantity(): void
    {
        $this->totalQuantity = 0;

        foreach ($this->items as $item) {
            $quantity = $item['quantity'] ?? 0;
            assert(is_numeric($quantity));
            $this->totalQuantity += (int) $quantity;
        }
    }

    public function isPending(): bool
    {
        return StockTransferStatus::PENDING === $this->status;
    }

    public function isInTransit(): bool
    {
        return StockTransferStatus::IN_TRANSIT === $this->status;
    }

    public function isReceived(): bool
    {
        return StockTransferStatus::RECEIVED === $this->status;
    }

    public function isCancelled(): bool
    {
        return StockTransferStatus::CANCELLED === $this->status;
    }

    public function __toString(): string
    {
        return $this->transferNo;
    }
}
