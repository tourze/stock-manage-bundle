<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\StockInbound;
use Tourze\StockManageBundle\Enum\StockInboundType;

/**
 * @extends AbstractCrudController<StockInbound>
 */
#[AdminCrud(routePath: '/stock/inbound', routeName: 'stock_inbound')]
final class StockInboundCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockInbound::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存入库单')
            ->setEntityLabelInPlural('库存入库单')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(50)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            ChoiceField::new('type', '入库类型')
                ->setChoices(array_reduce(
                    StockInboundType::cases(),
                    static fn (array $choices, StockInboundType $type): array => $choices + [$type->value => $type],
                    []
                ))
                ->setColumns(3),
            TextField::new('referenceNo', '参考单号')->setColumns(3),
            AssociationField::new('sku', 'SKU'),
            IntegerField::new('totalQuantity', '总数量')->setColumns(2),
            NumberField::new('totalAmount', '总金额')->setNumDecimals(2)->setColumns(2),
            TextField::new('operator', '操作人')->setColumns(2),
            TextField::new('locationId', '位置')->setColumns(2)->hideOnIndex(),
            TextareaField::new('remark', '备注')->setColumns(6)->hideOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices(array_reduce(
                StockInboundType::cases(),
                static fn (array $choices, StockInboundType $type): array => $choices + [$type->value => $type],
                []
            )))
            ->add('referenceNo')
            ->add('sku')
            ->add(NumericFilter::new('totalQuantity'))
            ->add(NumericFilter::new('totalAmount'))
            ->add('operator')
            ->add('locationId')
        ;
    }
}
