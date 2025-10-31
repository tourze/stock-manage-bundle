<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\StockSnapshot;

/**
 * @extends AbstractCrudController<StockSnapshot>
 */
#[AdminCrud(routePath: '/stock/snapshot', routeName: 'stock_snapshot')]
final class StockSnapshotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockSnapshot::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存快照')
            ->setEntityLabelInPlural('库存快照')
            ->setPageTitle('index', '库存快照管理')
            ->setPageTitle('detail', '库存快照详情')
            ->setPageTitle('new', '新增库存快照')
            ->setPageTitle('edit', '编辑库存快照')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('snapshotNo', '快照号')
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('唯一的快照编号，用于标识此次快照'),
            ChoiceField::new('type', '快照类型')
                ->setChoices([
                    '日快照' => 'daily',
                    '周快照' => 'weekly',
                    '月快照' => 'monthly',
                    '手动快照' => 'manual',
                    '系统快照' => 'system',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('快照生成的频率和类型'),
            ChoiceField::new('triggerMethod', '触发方式')
                ->setChoices([
                    '定时任务' => 'scheduled',
                    '手动触发' => 'manual',
                    '事件触发' => 'event',
                    'API触发' => 'api',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('快照是如何被生成的'),
            AssociationField::new('sku', 'SKU')
                ->setColumns(3)
                ->setHelp('关联的特定SKU，为空表示全量快照')
                ->hideOnIndex(),
            IntegerField::new('totalQuantity', '总数量')
                ->setColumns(2)
                ->setHelp('快照记录的库存总数量'),
            NumberField::new('totalValue', '总价值')
                ->setColumns(2)
                ->setNumDecimals(2)
                ->setHelp('快照记录的库存总价值'),
            IntegerField::new('productCount', '商品数量')
                ->setColumns(2)
                ->setHelp('包含的商品种类数量'),
            IntegerField::new('batchCount', '批次数量')
                ->setColumns(2)
                ->setHelp('包含的批次总数量'),
            TextField::new('locationId', '位置ID')
                ->setColumns(2)
                ->setHelp('快照涉及的仓库位置')
                ->hideOnIndex(),
            TextField::new('operator', '操作人')
                ->setColumns(3)
                ->setHelp('执行快照操作的用户')
                ->hideOnIndex(),
            DateTimeField::new('validUntil', '有效期至')
                ->setColumns(3)
                ->setHelp('快照数据的有效期限')
                ->hideOnIndex(),
            TextareaField::new('notes', '备注')
                ->setColumns(6)
                ->setNumOfRows(2)
                ->setHelp('快照的详细说明或备注信息')
                ->hideOnIndex(),
            CodeEditorField::new('summary', '汇总信息')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('快照的汇总统计信息（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('details', '详细信息')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('快照的详细明细数据（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('metadata', '元数据')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('快照相关的扩展元数据（JSON格式）')
                ->onlyOnDetail(),
            DateTimeField::new('createTime', '创建时间')
                ->setColumns(3)
                ->onlyOnIndex(),
            DateTimeField::new('updateTime', '更新时间')
                ->setColumns(3)
                ->onlyOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('snapshotNo')
            ->add(ChoiceFilter::new('type')->setChoices([
                '日快照' => 'daily',
                '周快照' => 'weekly',
                '月快照' => 'monthly',
                '手动快照' => 'manual',
                '系统快照' => 'system',
            ]))
            ->add(ChoiceFilter::new('triggerMethod')->setChoices([
                '定时任务' => 'scheduled',
                '手动触发' => 'manual',
                '事件触发' => 'event',
                'API触发' => 'api',
            ]))
            ->add('sku')
            ->add(NumericFilter::new('totalQuantity'))
            ->add(NumericFilter::new('totalValue'))
            ->add(NumericFilter::new('productCount'))
            ->add(NumericFilter::new('batchCount'))
            ->add('locationId')
            ->add('operator')
            ->add(DateTimeFilter::new('validUntil'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
