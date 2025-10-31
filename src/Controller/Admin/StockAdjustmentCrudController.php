<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\StockAdjustment;
use Tourze\StockManageBundle\Enum\StockAdjustmentStatus;
use Tourze\StockManageBundle\Enum\StockAdjustmentType;

/**
 * @extends AbstractCrudController<StockAdjustment>
 */
#[AdminCrud(routePath: '/stock/adjustment', routeName: 'stock_adjustment')]
final class StockAdjustmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockAdjustment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存调整')
            ->setEntityLabelInPlural('库存调整')
            ->setPageTitle('index', '库存调整管理')
            ->setPageTitle('detail', '库存调整详情')
            ->setPageTitle('new', '新增库存调整')
            ->setPageTitle('edit', '编辑库存调整')
            ->setDefaultSort(['createTime' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('adjustmentNo', '调整单号')->setColumns(4),
            ChoiceField::new('type', '调整类型')
                ->setChoices(array_reduce(
                    StockAdjustmentType::cases(),
                    static fn (array $choices, StockAdjustmentType $type): array => $choices + [$type->value => $type],
                    []
                ))
                ->setColumns(3),
            ChoiceField::new('status', '状态')
                ->setChoices(array_reduce(
                    StockAdjustmentStatus::cases(),
                    static fn (array $choices, StockAdjustmentStatus $status): array => $choices + [$status->value => $status],
                    []
                ))
                ->setColumns(3),
            NumberField::new('totalAdjusted', '调整数量')->setColumns(2),
            NumberField::new('costImpact', '成本影响')->setNumDecimals(2)->setColumns(3),
            TextField::new('operator', '操作人')->setColumns(3),
            TextField::new('approver', '审批人')->setColumns(3)->hideOnIndex(),
            TextField::new('locationId', '位置')->setColumns(2)->hideOnIndex(),
            TextareaField::new('reason', '调整原因')->setColumns(6)->hideOnIndex(),
            DateTimeField::new('createTime', '创建时间')->setColumns(3)->onlyOnIndex(),
            DateTimeField::new('updateTime', '更新时间')->setColumns(3)->onlyOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('adjustmentNo')
            ->add(ChoiceFilter::new('type')->setChoices(array_reduce(
                StockAdjustmentType::cases(),
                static fn (array $choices, StockAdjustmentType $type): array => $choices + [$type->value => $type],
                []
            )))
            ->add(ChoiceFilter::new('status')->setChoices(array_reduce(
                StockAdjustmentStatus::cases(),
                static fn (array $choices, StockAdjustmentStatus $status): array => $choices + [$status->value => $status],
                []
            )))
            ->add(NumericFilter::new('totalAdjusted'))
            ->add(NumericFilter::new('costImpact'))
            ->add('operator')
            ->add('approver')
            ->add('locationId')
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
