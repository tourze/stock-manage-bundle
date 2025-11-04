<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;

/**
 * 虚拟库存实体
 * 用于管理预售、期货等非实际库存
 * 贫血模型：只包含数据和getter/setter，不包含业务逻辑.
 */
#[ORM\Entity]
#[ORM\Table(name: 'virtual_stocks', options: ['comment' => '虚拟库存表'])]
class VirtualStock implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: true)]
    private ?SKU $sku = null;

    #[IndexColumn(name: 'idx_virtual_type')]
    #[ORM\Column(type: Types::STRING, length: 30, options: ['comment' => '虚拟库存类型'])]
    #[Assert\NotBlank(message: '虚拟库存类型不能为空')]
    #[Assert\Length(max: 30, maxMessage: '虚拟库存类型不能超过30个字符')]
    #[Assert\Choice(choices: ['presale', 'futures', 'dropship', 'backorder'], message: '虚拟库存类型必须是有效的类型')]
    private string $virtualType;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '虚拟库存数量'])]
    #[Assert\PositiveOrZero(message: '虚拟库存数量不能为负数')]
    private int $quantity = 0;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '状态：active=有效，inactive=无效，converted=已转换'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Length(max: 20, maxMessage: '状态不能超过20个字符')]
    #[Assert\Choice(choices: ['active', 'inactive', 'converted'], message: '状态必须是active、inactive或converted')]
    private string $status = 'active';

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '描述'])]
    #[Assert\Length(max: 500, maxMessage: '描述不能超过500个字符')]
    private ?string $description = null;

    #[IndexColumn(name: 'idx_expected_date')]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '预期到达日期'])]
    #[Assert\Type(type: \DateTimeImmutable::class, message: '预期到达日期必须是有效的日期时间')]
    private ?\DateTimeImmutable $expectedDate = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '关联业务ID（订单号、采购单号等）'])]
    #[Assert\Length(max: 50, maxMessage: '业务ID不能超过50个字符')]
    private ?string $businessId = null;

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

    public function getVirtualType(): string
    {
        return $this->virtualType;
    }

    public function setVirtualType(string $virtualType): void
    {
        $this->virtualType = $virtualType;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getExpectedDate(): ?\DateTimeImmutable
    {
        return $this->expectedDate;
    }

    public function setExpectedDate(?\DateTimeImmutable $expectedDate): void
    {
        $this->expectedDate = $expectedDate;
    }

    public function getBusinessId(): ?string
    {
        return $this->businessId;
    }

    public function setBusinessId(?string $businessId): void
    {
        $this->businessId = $businessId;
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

    public function __toString(): string
    {
        $skuId = null !== $this->sku ? $this->sku->getId() : 'Unknown';

        return sprintf('%s (%s)', $skuId, $this->virtualType);
    }
}
