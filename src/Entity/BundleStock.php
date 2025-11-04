<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

/**
 * 组合商品实体
 * 用于定义由多个SKU组成的套餐或组合产品
 * 贫血模型：只包含数据和getter/setter，不包含业务逻辑.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bundle_stocks', options: ['comment' => '组合商品库存表'])]
class BundleStock implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '主键ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true, options: ['comment' => '组合商品编码'])]
    #[Assert\NotBlank(message: '组合商品编码不能为空')]
    #[Assert\Length(max: 100, maxMessage: '组合商品编码不能超过100个字符')]
    #[IndexColumn]
    private string $bundleCode;

    #[ORM\Column(type: Types::STRING, length: 200, options: ['comment' => '组合商品名称'])]
    #[Assert\NotBlank(message: '组合商品名称不能为空')]
    #[Assert\Length(max: 200, maxMessage: '组合商品名称不能超过200个字符')]
    private string $bundleName;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '描述'])]
    #[Assert\Length(max: 500, maxMessage: '描述不能超过500个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '类型：fixed=固定组合，flexible=灵活组合'])]
    #[Assert\NotBlank(message: '类型不能为空')]
    #[Assert\Length(max: 20, maxMessage: '类型不能超过20个字符')]
    #[Assert\Choice(choices: ['fixed', 'flexible'], message: '类型必须是fixed或flexible')]
    private string $type = 'fixed';

    /**
     * @var Collection<int, BundleItem>
     */
    #[ORM\OneToMany(mappedBy: 'bundleStock', targetEntity: BundleItem::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[ORM\OrderBy(value: ['sort_order' => 'ASC'])]
    private Collection $items;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 20, options: ['comment' => '状态：active=有效，inactive=无效'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Length(max: 20, maxMessage: '状态不能超过20个字符')]
    #[Assert\Choice(choices: ['active', 'inactive'], message: '状态必须是active或inactive')]
    private string $status = 'active';

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBundleCode(): string
    {
        return $this->bundleCode;
    }

    public function setBundleCode(string $bundleCode): void
    {
        $this->bundleCode = $bundleCode;
    }

    public function getBundleName(): string
    {
        return $this->bundleName;
    }

    public function setBundleName(string $bundleName): void
    {
        $this->bundleName = $bundleName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return Collection<int, BundleItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BundleItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setBundleStock($this);
        }

        return $this;
    }

    public function removeItem(BundleItem $item): self
    {
        if ($this->items->removeElement($item)) {
            // set the owning side to null (unless already changed)
            if ($item->getBundleStock() === $this) {
                $item->setBundleStock(null);
            }
        }

        return $this;
    }

    public function clearItems(): self
    {
        foreach ($this->items as $item) {
            $this->removeItem($item);
        }

        return $this;
    }

    /**
     * 获取非可选项目.
     *
     * @return Collection<int, BundleItem>
     */
    public function getRequiredItems(): Collection
    {
        return $this->items->filter(fn (BundleItem $item) => !$item->isOptional());
    }

    /**
     * 获取可选项目.
     *
     * @return Collection<int, BundleItem>
     */
    public function getOptionalItems(): Collection
    {
        return $this->items->filter(fn (BundleItem $item) => $item->isOptional());
    }

    /**
     * 获取组合中的总项目数.
     */
    public function getTotalItemCount(): int
    {
        return $this->items->count();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function __toString(): string
    {
        return $this->bundleName;
    }
}
