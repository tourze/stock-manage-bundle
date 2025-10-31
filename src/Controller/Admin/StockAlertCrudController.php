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
use Tourze\StockManageBundle\Entity\StockAlert;
use Tourze\StockManageBundle\Enum\StockAlertSeverity;
use Tourze\StockManageBundle\Enum\StockAlertStatus;
use Tourze\StockManageBundle\Enum\StockAlertType;

/**
 * @extends AbstractCrudController<StockAlert>
 */
#[AdminCrud(routePath: '/stock/alert', routeName: 'stock_alert')]
final class StockAlertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockAlert::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存预警')
            ->setEntityLabelInPlural('库存预警')
            ->setPageTitle('index', '库存预警管理')
            ->setPageTitle('detail', '库存预警详情')
            ->setPageTitle('new', '新增库存预警')
            ->setPageTitle('edit', '编辑库存预警')
            ->setDefaultSort(['createTime' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            ChoiceField::new('alertType', '预警类型')
                ->setChoices(array_reduce(
                    StockAlertType::cases(),
                    static fn (array $choices, StockAlertType $type): array => $choices + [$type->getLabel() => $type],
                    []
                ))
                ->setColumns(3),
            TextField::new('sku', 'SKU')->setColumns(3),
            ChoiceField::new('severity', '严重级别')
                ->setChoices(array_reduce(
                    StockAlertSeverity::cases(),
                    static fn (array $choices, StockAlertSeverity $severity): array => $choices + [$severity->value => $severity],
                    []
                ))
                ->setColumns(2),
            ChoiceField::new('status', '状态')
                ->setChoices(array_reduce(
                    StockAlertStatus::cases(),
                    static fn (array $choices, StockAlertStatus $status): array => $choices + [$status->value => $status],
                    []
                ))
                ->setColumns(2),
            NumberField::new('thresholdValue', '阈值')->setNumDecimals(2)->setColumns(2),
            NumberField::new('currentValue', '当前值')->setNumDecimals(2)->setColumns(2),
            TextareaField::new('message', '预警消息')->setColumns(6)->hideOnIndex(),
            TextField::new('locationId', '位置')->setColumns(3)->hideOnIndex(),
            TextareaField::new('resolvedNote', '解决备注')->setColumns(6)->hideOnIndex(),
            DateTimeField::new('triggeredAt', '触发时间')->setColumns(3)->onlyOnIndex(),
            DateTimeField::new('resolvedAt', '解决时间')->setColumns(3)->onlyOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('alertType')->setChoices(array_reduce(
                StockAlertType::cases(),
                static fn (array $choices, StockAlertType $type): array => $choices + [$type->getLabel() => $type],
                []
            )))
            ->add('sku')
            ->add(ChoiceFilter::new('severity')->setChoices(array_reduce(
                StockAlertSeverity::cases(),
                static fn (array $choices, StockAlertSeverity $severity): array => $choices + [$severity->value => $severity],
                []
            )))
            ->add(ChoiceFilter::new('status')->setChoices(array_reduce(
                StockAlertStatus::cases(),
                static fn (array $choices, StockAlertStatus $status): array => $choices + [$status->value => $status],
                []
            )))
            ->add(NumericFilter::new('thresholdValue'))
            ->add(NumericFilter::new('currentValue'))
            ->add('locationId')
            ->add(DateTimeFilter::new('triggeredAt'))
            ->add(DateTimeFilter::new('resolvedAt'))
        ;
    }
}
