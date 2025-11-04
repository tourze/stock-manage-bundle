<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Enum\StockChange;

/**
 * 库存操作日志.
 */
#[ORM\Table(name: 'stock_log', options: ['comment' => '库存操作日志'])]
#[ORM\Entity]
class StockLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, enumType: StockChange::class, options: ['comment' => '库存变动类型'])]
    #[Assert\NotNull(message: '库存变动类型不能为空')]
    #[Assert\Choice(callback: [StockChange::class, 'cases'], message: '库存变动类型必须是有效的类型')]
    private StockChange $type;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '变动数量'])]
    #[Assert\NotNull(message: '变动数量不能为空')]
    #[Assert\Range(min: -999999, max: 999999, notInRangeMessage: '变动数量必须在合理范围内')]
    private int $quantity;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => 'SKU信息'])]
    #[Assert\Type(type: 'array', message: 'SKU信息必须是数组格式')]
    #[Assert\NotBlank(message: 'SKU信息不能为空')]
    private array $skuData = [];

    #[ORM\Column(type: Types::STRING, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Length(max: 500, maxMessage: '备注不能超过500个字符')]
    private ?string $remark = null;

    /**
     * 临时存储SKU对象，用于业务逻辑处理.
     */
    #[Assert\Valid()]
    #[Assert\Type(type: SKU::class, message: 'SKU对象类型错误')]
    private ?SKU $skuObject = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): StockChange
    {
        return $this->type;
    }

    public function setType(StockChange $type): void
    {
        $this->type = $type;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getSku(): ?SKU
    {
        // 优先返回临时存储的SKU对象
        if (null !== $this->skuObject) {
            return $this->skuObject;
        }

        if ([] === $this->skuData) {
            return null;
        }

        // 这里需要根据实际的SKU实现来创建对象
        // 暂时返回null，实际项目中需要根据skuData重建SKU对象
        // 或者注入SkuService来根据ID获取SKU对象
        return null;
    }

    public function setSku(SKU $sku): void
    {
        // 同时存储SKU对象和数据
        $this->skuObject = $sku;
        $this->skuData = [
            'id' => $sku->getId(),
            // 可以根据需要存储更多SKU信息
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSkuData(): array
    {
        return $this->skuData;
    }

    /**
     * @param array<string, mixed> $skuData
     */
    public function setSkuData(array $skuData): void
    {
        $this->skuData = $skuData;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }

    /**
     * 获取SKU ID（用于那些只需要ID的场景）.
     */
    public function getSkuId(): ?string
    {
        $id = $this->skuData['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function __toString(): string
    {
        return sprintf('StockLog#%s: %s %d', $this->getId() ?? 'new', $this->getType()->value, $this->getQuantity());
    }
}
