<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Enum\StockChange;

/**
 * @extends AbstractCrudController<StockLog>
 */
#[AdminCrud(routePath: '/stock/log', routeName: 'stock_log')]
final class StockLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存日志')
            ->setEntityLabelInPlural('库存日志')
            ->setPageTitle('index', '库存操作日志')
            ->setPageTitle('detail', '库存日志详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(50)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->disable(Action::EDIT)
            ->disable(Action::DELETE)
            ->setPermission(Action::DETAIL, 'ROLE_USER')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $typeField = EnumField::new('type', '变动类型')
            ->setColumns(2)
            ->setHelp('库存变动的具体类型')
        ;
        $typeField->setEnumCases(StockChange::cases());

        return [
            IntegerField::new('id', 'ID')
                ->setColumns(1)
                ->onlyOnIndex(),
            $typeField,
            IntegerField::new('quantity', '变动数量')
                ->setColumns(2)
                ->setHelp('正数表示增加，负数表示减少')
                ->formatValue(function ($value): string {
                    if (null === $value) {
                        return '';
                    }
                    if (!is_scalar($value)) {
                        return '';
                    }
                    if (!is_numeric($value)) {
                        return (string) $value;
                    }
                    $numValue = (int) $value;

                    return $numValue >= 0 ? "+{$numValue}" : (string) $numValue;
                }),
            TextField::new('skuId', 'SKU ID')
                ->setColumns(3)
                ->setHelp('关联的SKU商品ID')
                ->hideOnIndex(),
            TextareaField::new('remark', '备注')
                ->setColumns(4)
                ->setNumOfRows(2)
                ->setHelp('操作的详细说明')
                ->hideOnIndex(),
            CodeEditorField::new('skuData', 'SKU数据')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('完整的SKU信息快照（JSON格式）')
                ->onlyOnDetail(),
            DateTimeField::new('createTime', '创建时间')
                ->setColumns(3)
                ->setHelp('日志记录的时间'),
            DateTimeField::new('updateTime', '更新时间')
                ->setColumns(3)
                ->onlyOnDetail(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type')->setChoices(array_reduce(
                StockChange::cases(),
                static fn (array $choices, StockChange $type): array => $choices + [$type->getLabel() => $type->value],
                []
            )))
            ->add(NumericFilter::new('quantity'))
            ->add('remark')
            ->add(DateTimeFilter::new('createTime'))
        ;
    }
}
