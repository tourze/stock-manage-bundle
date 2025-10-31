<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockInboundType;

#[ORM\Entity]
#[ORM\Table(name: 'stock_inbounds', options: ['comment' => '入库记录表'])]
class StockInbound implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[IndexColumn(name: 'idx_inbound_type')]
    #[ORM\Column(type: Types::STRING, length: 30, enumType: StockInboundType::class, options: ['comment' => '入库类型'])]
    #[Assert\Choice(callback: [StockInboundType::class, 'cases'], message: '入库类型必须是有效的类型')]
    private StockInboundType $type;

    #[IndexColumn(name: 'idx_reference_no')]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '业务单号'])]
    #[Assert\NotBlank(message: '业务单号不能为空')]
    #[Assert\Length(max: 100, maxMessage: '业务单号不能超过100个字符')]
    private string $referenceNo;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    /**
     * @var array<int|string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '入库明细'])]
    #[Assert\Type(type: 'array', message: '入库明细必须是数组')]
    private array $items = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, options: ['comment' => '入库总金额'])]
    #[Assert\PositiveOrZero(message: '入库总金额不能为负数')]
    #[Assert\Length(max: 18, maxMessage: '入库总金额不能超过18个字符')]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '入库总数量'])]
    #[Assert\PositiveOrZero(message: '入库总数量不能为负数')]
    private int $totalQuantity = 0;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true, options: ['comment' => '操作人'])]
    #[Assert\Length(max: 100, maxMessage: '操作人不能超过100个字符')]
    private ?string $operator = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '仓库位置'])]
    #[Assert\Length(max: 50, maxMessage: '位置ID不能超过50个字符')]
    private ?string $locationId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 2000, maxMessage: '备注不能超过2000个字符')]
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

    public function getType(): StockInboundType
    {
        return $this->type;
    }

    public function setType(StockInboundType $type): void
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

    public function getSku(): ?SKU
    {
        return $this->sku;
    }

    public function setSku(?SKU $sku): void
    {
        $this->sku = $sku;
    }

    /** @return array<mixed> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param array<mixed> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
        $this->calculateTotals();
    }

    /** @param array<mixed> $item */
    public function addItem(array $item): void
    {
        $this->items[] = $item;
        $this->calculateTotals();
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function getTotalQuantity(): int
    {
        return $this->totalQuantity;
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

    private function calculateTotals(): void
    {
        $this->totalQuantity = 0;
        $this->totalAmount = '0.00';

        $amount = 0.0;
        foreach ($this->items as $item) {
            assert(is_array($item));
            $quantity = $item['quantity'] ?? 0;
            $unitCost = $item['unit_cost'] ?? 0;
            assert(is_numeric($quantity) && is_numeric($unitCost));
            $this->totalQuantity += (int) $quantity;
            $amount += ((float) $quantity) * ((float) $unitCost);
        }

        $this->totalAmount = number_format($amount, 2, '.', '');
    }

    public function __toString(): string
    {
        return $this->referenceNo;
    }

    public function setTotalQuantity(int $totalQuantity): void
    {
        $this->totalQuantity = $totalQuantity;
    }

    public function setTotalAmount(string $totalAmount): void
    {
        $this->totalAmount = $totalAmount;
    }
}
