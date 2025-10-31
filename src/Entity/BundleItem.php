<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;

/**
 * 组合商品项目实体
 * 表示组合商品中的一个具体项目，包含SKU和数量信息.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bundle_items', options: ['comment' => '组合商品项目表'])]
class BundleItem implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null; // @phpstan-ignore-line property.unusedType (ORM auto-generated)

    #[ORM\ManyToOne(targetEntity: BundleStock::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'bundle_stock_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '关联的组合商品不能为空')]
    private ?BundleStock $bundleStock;

    #[ORM\ManyToOne(targetEntity: SKU::class)]
    #[ORM\JoinColumn(name: 'sku_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: 'SKU不能为空')]
    private SKU $sku;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '数量'])]
    #[Assert\Positive(message: '数量必须大于0')]
    private int $quantity;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否可选项目', 'default' => false])]
    #[Assert\Type(type: 'bool', message: '可选标志必须是布尔值')]
    private bool $optional = false;

    #[ORM\Column(name: 'sort_order', type: Types::INTEGER, options: ['comment' => '排序序号', 'default' => 0])]
    #[Assert\PositiveOrZero(message: '排序序号不能为负数')]
    private int $sortOrder = 0;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBundleStock(): ?BundleStock
    {
        return $this->bundleStock;
    }

    public function setBundleStock(?BundleStock $bundleStock): void
    {
        $this->bundleStock = $bundleStock;
    }

    public function getSku(): SKU
    {
        return $this->sku;
    }

    public function setSku(SKU $sku): void
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

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function setOptional(bool $optional): void
    {
        $this->optional = $optional;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function __toString(): string
    {
        return sprintf('SKU x%d', $this->quantity);
    }
}
