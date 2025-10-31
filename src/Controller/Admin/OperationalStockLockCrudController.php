<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\OperationalStockLock;

/**
 * @extends AbstractCrudController<OperationalStockLock>
 */
#[AdminCrud(routePath: '/stock/operational-lock', routeName: 'stock_operational_lock')]
final class OperationalStockLockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OperationalStockLock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('操作库存锁定')
            ->setEntityLabelInPlural('操作库存锁定')
            ->setPageTitle('index', '操作库存锁定管理')
            ->setPageTitle('detail', '操作库存锁定详情')
            ->setPageTitle('new', '新增操作库存锁定')
            ->setPageTitle('edit', '编辑操作库存锁定')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE)
            ->setPermission(Action::EDIT, 'ROLE_ADMIN')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            ChoiceField::new('operationType', '操作类型')
                ->setChoices([
                    '盘点' => 'inventory',
                    '调整' => 'adjustment',
                    '维护' => 'maintenance',
                    '审计' => 'audit',
                ])
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('执行的操作活动类型'),
            TextField::new('operator', '操作人')
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('执行操作的用户或系统'),
            ChoiceField::new('priority', '优先级')
                ->setChoices([
                    '低' => 'low',
                    '普通' => 'normal',
                    '高' => 'high',
                    '紧急' => 'urgent',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('操作的优先级别'),
            ChoiceField::new('status', '状态')
                ->setChoices([
                    '进行中' => 'active',
                    '已完成' => 'completed',
                    '已取消' => 'cancelled',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('当前操作状态'),
            TextField::new('reason', '锁定原因')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('说明为什么要锁定这些库存进行操作'),
            TextField::new('department', '部门')
                ->setColumns(3)
                ->setHelp('负责执行操作的部门')
                ->hideOnIndex(),
            TextField::new('locationId', '位置ID')
                ->setColumns(2)
                ->setHelp('操作涉及的仓库位置')
                ->hideOnIndex(),
            IntegerField::new('estimatedDuration', '预计持续时间')
                ->setColumns(2)
                ->setHelp('预计操作持续时间（分钟）')
                ->hideOnIndex(),
            TextField::new('completedBy', '完成人')
                ->setColumns(3)
                ->setHelp('实际完成操作的用户')
                ->hideOnIndex(),
            DateTimeField::new('completedTime', '完成时间')
                ->setColumns(3)
                ->setHelp('操作实际完成的时间')
                ->hideOnIndex(),
            TextareaField::new('completionNotes', '完成备注')
                ->setColumns(6)
                ->setNumOfRows(3)
                ->setHelp('操作完成后的备注说明')
                ->hideOnIndex(),
            DateTimeField::new('releasedTime', '释放时间')
                ->setColumns(3)
                ->setHelp('锁定释放的时间')
                ->hideOnIndex(),
            TextField::new('releaseReason', '释放原因')
                ->setColumns(4)
                ->setHelp('释放锁定的原因说明')
                ->hideOnIndex(),
            CodeEditorField::new('batchIds', '批次ID列表')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('锁定的批次ID列表（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('operationResult', '操作结果')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('操作完成后的结果数据（JSON格式）')
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
            ->add(ChoiceFilter::new('operationType')->setChoices([
                '盘点' => 'inventory',
                '调整' => 'adjustment',
                '维护' => 'maintenance',
                '审计' => 'audit',
            ]))
            ->add('operator')
            ->add(ChoiceFilter::new('priority')->setChoices([
                '低' => 'low',
                '普通' => 'normal',
                '高' => 'high',
                '紧急' => 'urgent',
            ]))
            ->add(ChoiceFilter::new('status')->setChoices([
                '进行中' => 'active',
                '已完成' => 'completed',
                '已取消' => 'cancelled',
            ]))
            ->add('reason')
            ->add('department')
            ->add('locationId')
            ->add(NumericFilter::new('estimatedDuration'))
            ->add('completedBy')
            ->add(DateTimeFilter::new('completedTime'))
            ->add(DateTimeFilter::new('releasedTime'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
