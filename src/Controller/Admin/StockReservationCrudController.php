<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\StockReservation;
use Tourze\StockManageBundle\Enum\StockReservationStatus;
use Tourze\StockManageBundle\Enum\StockReservationType;

/**
 * @extends AbstractCrudController<StockReservation>
 */
#[AdminCrud(routePath: '/stock/reservation', routeName: 'stock_reservation')]
final class StockReservationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockReservation::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存预占记录')
            ->setEntityLabelInPlural('库存预占记录')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(50)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('sku', 'SKU')->setColumns(3),
            IntegerField::new('quantity', '预占数量')->setColumns(2),
            ChoiceField::new('type', '预占类型')
                ->setChoices(array_reduce(
                    StockReservationType::cases(),
                    static fn (array $choices, StockReservationType $type): array => $choices + [$type->getLabel() => $type],
                    []
                ))
                ->setColumns(3),
            TextField::new('businessId', '业务ID')->setColumns(3),
            ChoiceField::new('status', '状态')
                ->setChoices(array_reduce(
                    StockReservationStatus::cases(),
                    static fn (array $choices, StockReservationStatus $status): array => $choices + [$status->getLabel() => $status],
                    []
                ))
                ->setColumns(2),
            TextField::new('operator', '操作人')->setColumns(3),
            DateTimeField::new('expiresTime', '过期时间')->setColumns(3),
            DateTimeField::new('confirmedTime', '确认时间')->setColumns(3)->onlyOnIndex(),
            DateTimeField::new('releasedTime', '释放时间')->setColumns(3)->onlyOnIndex(),
            TextField::new('releaseReason', '释放原因')->setColumns(3)->hideOnIndex(),
            TextareaField::new('notes', '备注')->setColumns(6)->hideOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('sku')
            ->add(NumericFilter::new('quantity'))
            ->add(ChoiceFilter::new('type')->setChoices(array_reduce(
                StockReservationType::cases(),
                static fn (array $choices, StockReservationType $type): array => $choices + [$type->getLabel() => $type],
                []
            )))
            ->add('businessId')
            ->add(ChoiceFilter::new('status')->setChoices(array_reduce(
                StockReservationStatus::cases(),
                static fn (array $choices, StockReservationStatus $status): array => $choices + [$status->getLabel() => $status],
                []
            )))
            ->add('operator')
            ->add(DateTimeFilter::new('expiresTime'))
            ->add(DateTimeFilter::new('confirmedTime'))
            ->add(DateTimeFilter::new('releasedTime'))
        ;
    }
}
