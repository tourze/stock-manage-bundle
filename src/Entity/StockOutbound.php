<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockOutboundType;

#[ORM\Entity]
#[ORM\Table(name: 'stock_outbounds', options: ['comment' => '出库记录表'])]
class StockOutbound implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockOutboundType::class, options: ['comment' => '出库类型'])]
    #[Assert\Choice(callback: [StockOutboundType::class, 'cases'], message: '出库类型必须是有效的类型')]
    #[IndexColumn(name: 'idx_outbound_type')]
    private StockOutboundType $type;

    #[IndexColumn(name: 'idx_outbound_ref_no')]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '业务单号'])]
    #[Assert\NotBlank(message: '业务单号不能为空')]
    #[Assert\Length(max: 100, maxMessage: '业务单号不能超过100个字符')]
    private string $referenceNo;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '出库明细'])]
    #[Assert\Type(type: 'array', message: '出库明细必须是数组')]
    private array $items = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '批次分配明细'])]
    #[Assert\Type(type: 'array', message: '批次分配明细必须是数组')]
    private array $allocations = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['comment' => '出库成本'])]
    #[Assert\PositiveOrZero(message: '出库成本不能为负数')]
    #[Assert\Length(max: 18, maxMessage: '出库成本不能超过18个字符')]
    private string $totalCost = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '出库总数量'])]
    #[Assert\PositiveOrZero(message: '出库总数量不能为负数')]
    private int $totalQuantity = 0;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '操作人'])]
    #[Assert\Length(max: 100, maxMessage: '操作人不能超过100个字符')]
    private ?string $operator = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '仓库位置'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 1000, maxMessage: '备注不能超过1000个字符')]
    private ?string $remark = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '附加信息'])]
    #[Assert\Type(type: 'array', message: '附加信息必须是数组')]
    private ?array $metadata = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): StockOutboundType
    {
        return $this->type;
    }

    public function setType(StockOutboundType $type): void
    {
        $this->type = $type;
    }

    public function getReferenceNo(): string
    {
        return $this->referenceNo;
    }

    public function setReferenceNo(string $referenceNo): void
    {
        $this->referenceNo = $referenceNo;
    }

    public function getBusinessNo(): string
    {
        return $this->referenceNo;
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
        $this->calculateTotalQuantity();
    }

    /** @return array<string, mixed> */
    public function getAllocations(): array
    {
        return $this->allocations;
    }

    /** @param array<string, mixed> $allocations */
    public function setAllocations(array $allocations): void
    {
        $this->allocations = $allocations;
        $this->calculateTotalCost();
    }

    public function getTotalCost(): string
    {
        return $this->totalCost;
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
    }

    public function setTotalQuantity(int $totalQuantity): void
    {
        $this->totalQuantity = $totalQuantity;
    }

    public function setTotalCost(string $totalCost): void
    {
        $this->totalCost = $totalCost;
    }

    /** @param array<string, mixed> $requestedItems */
    public function setRequestedItems(array $requestedItems): void
    {
        // 这个方法可能是为了兼容性而保留的
        $this->setItems($requestedItems);
    }

    /** @param array<string, mixed> $allocatedItems */
    public function setAllocatedItems(array $allocatedItems): void
    {
        // 这个方法可能是为了兼容性而保留的
        $this->setAllocations($allocatedItems);
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
    }

    public function getLocationId(): ?string
    {
        return $this->locationId;
    }

    public function setLocationId(?string $locationId): void
    {
        $this->locationId = $locationId;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }

    /** @return array<string, mixed>|null */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /** @param array<string, mixed>|null $metadata */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    private function calculateTotalQuantity(): void
    {
        $this->totalQuantity = 0;

        foreach ($this->items as $item) {
            assert(is_array($item));
            $quantity = $item['quantity'] ?? 0;
            assert(is_numeric($quantity));
            $this->totalQuantity += (int) $quantity;
        }
    }

    private function calculateTotalCost(): void
    {
        $cost = 0.0;

        foreach ($this->allocations as $allocation) {
            assert(is_array($allocation));
            $quantity = $allocation['quantity'] ?? 0;
            $unitCost = $allocation['unit_cost'] ?? 0;
            assert(is_numeric($quantity) && is_numeric($unitCost));
            $cost += ((float) $quantity) * ((float) $unitCost);
        }

        $this->totalCost = number_format($cost, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->referenceNo;
    }
}
