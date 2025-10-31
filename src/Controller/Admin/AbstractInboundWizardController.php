<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\Service\Attribute\Required;
use Tourze\ProductServiceContracts\SkuLoaderInterface;
use Tourze\StockManageBundle\Exception\InvalidArgumentException;
use Tourze\StockManageBundle\Service\InboundService;

abstract class AbstractInboundWizardController extends AbstractController
{
    protected InboundService $inboundService;

    protected SkuLoaderInterface $skuLoader;

    #[Required]
    public function setInboundService(InboundService $inboundService): void
    {
        $this->inboundService = $inboundService;
    }

    #[Required]
    public function setSkuLoader(SkuLoaderInterface $skuLoader): void
    {
        $this->skuLoader = $skuLoader;
    }

    /**
     * 处理表单数据.
     *
     * @param array<string, mixed> $formData
     *
     * @return array<string, mixed>
     */
    protected function processFormData(array $formData, string $type): array
    {
        $data = $this->buildBaseData($formData, $type);
        $items = $formData['items'] ?? [];
        assert(is_array($items));

        // 确保items数组中的每个元素都是array<string, mixed>
        /** @var array<array<string, mixed>> $validatedItems */
        $validatedItems = array_map(function ($item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Each item must be an array');
            }

            /** @var array<string, mixed> $item */
            return $item;
        }, $items);

        $data['items'] = $this->processItems($validatedItems);

        return $data;
    }

    /**
     * 构建基础数据结构.
     *
     * @param array<string, mixed> $formData
     *
     * @return array<string, mixed>
     */
    protected function buildBaseData(array $formData, string $type): array
    {
        $referenceNoField = $this->getReferenceNoField($type);

        $data = [
            $referenceNoField => $formData['reference_no'] ?? '',
            'operator' => $formData['operator'] ?? $this->getUser()?->getUserIdentifier() ?? 'system',
            'location_id' => $formData['location_id'] ?? null,
            'notes' => $formData['notes'] ?? null,
            'items' => [],
        ];

        if ('transfer' === $type) {
            $data['from_location'] = $formData['from_location'] ?? null;
        }

        return $data;
    }

    /**
     * 获取参考号字段名.
     */
    protected function getReferenceNoField(string $type): string
    {
        return match ($type) {
            'purchase' => 'purchase_order_no',
            'production' => 'production_order_no',
            'return' => 'return_order_no',
            'transfer' => 'transfer_no',
            default => 'reference_no',
        };
    }

    /**
     * 处理项目明细数据.
     *
     * @param array<array<string, mixed>> $itemsData
     *
     * @return array<array<string, mixed>>
     */
    protected function processItems(array $itemsData): array
    {
        $items = [];

        foreach ($itemsData as $itemData) {
            if ($this->isValidItem($itemData)) {
                $items[] = $this->buildItemData($itemData);
            }
        }

        return $items;
    }

    /**
     * 验证项目数据是否有效.
     *
     * @param array<string, mixed> $itemData
     */
    private function isValidItem(array $itemData): bool
    {
        return isset($itemData['sku_id']) && '' !== $itemData['sku_id']
            && isset($itemData['quantity']) && '' !== $itemData['quantity'];
    }

    /**
     * 构建单个项目数据.
     *
     * @param array<string, mixed> $itemData
     *
     * @return array<string, mixed>
     */
    private function buildItemData(array $itemData): array
    {
        assert(isset($itemData['sku_id']));
        assert(is_string($itemData['sku_id']));
        $sku = $this->loadSku($itemData['sku_id']);

        assert(isset($itemData['quantity']));
        assert(is_numeric($itemData['quantity']));
        $item = [
            'sku' => $sku,
            'quantity' => (int) $itemData['quantity'],
            'quality_level' => $itemData['quality_level'] ?? 'A',
        ];

        return $this->addOptionalFields($item, $itemData);
    }

    /**
     * 加载 SKU 对象
     */
    private function loadSku(string $skuId): object
    {
        $sku = $this->skuLoader->loadSkuByIdentifier($skuId);
        if (null === $sku) {
            throw new InvalidArgumentException(sprintf('SKU not found: %s', $skuId));
        }

        return $sku;
    }

    /**
     * 添加可选字段到项目数据.
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $itemData
     *
     * @return array<string, mixed>
     */
    private function addOptionalFields(array $item, array $itemData): array
    {
        if (isset($itemData['batch_no']) && '' !== $itemData['batch_no']) {
            $item['batch_no'] = $itemData['batch_no'];
        }

        if (isset($itemData['unit_cost']) && is_numeric($itemData['unit_cost'])) {
            $item['unit_cost'] = (float) $itemData['unit_cost'];
        }

        if (isset($itemData['production_date']) && '' !== $itemData['production_date']) {
            assert(is_string($itemData['production_date']));
            $item['production_date'] = new \DateTimeImmutable($itemData['production_date']);
        }

        if (isset($itemData['expiry_date']) && '' !== $itemData['expiry_date']) {
            assert(is_string($itemData['expiry_date']));
            $item['expiry_date'] = new \DateTimeImmutable($itemData['expiry_date']);
        }

        return $item;
    }
}
