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
use Tourze\StockManageBundle\Entity\BusinessStockLock;

/**
 * @extends AbstractCrudController<BusinessStockLock>
 */
#[AdminCrud(routePath: '/stock/business-lock', routeName: 'stock_business_lock')]
final class BusinessStockLockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BusinessStockLock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('业务库存锁定')
            ->setEntityLabelInPlural('业务库存锁定')
            ->setPageTitle('index', '业务库存锁定管理')
            ->setPageTitle('detail', '业务库存锁定详情')
            ->setPageTitle('new', '新增业务库存锁定')
            ->setPageTitle('edit', '编辑业务库存锁定')
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
            ChoiceField::new('type', '锁定类型')
                ->setChoices([
                    '订单锁定' => 'order',
                    '促销锁定' => 'promotion',
                    '系统锁定' => 'system',
                    '手动锁定' => 'manual',
                ])
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('业务锁定的类型分类'),
            TextField::new('businessId', '业务ID')
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('关联的业务单据ID（如订单号、促销ID等）'),
            ChoiceField::new('status', '锁定状态')
                ->setChoices([
                    '有效' => 'active',
                    '已过期' => 'expired',
                    '已释放' => 'released',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('当前锁定状态'),
            TextField::new('reason', '锁定原因')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('说明为什么要锁定这些库存'),
            IntegerField::new('totalLockedQuantity', '锁定总数量')
                ->setColumns(2)
                ->onlyOnIndex()
                ->setHelp('所有批次锁定数量的总和'),
            TextField::new('createdBy', '创建人')
                ->setColumns(3)
                ->setHelp('执行锁定操作的用户')
                ->hideOnIndex(),
            TextField::new('releasedBy', '释放人')
                ->setColumns(3)
                ->setHelp('执行释放操作的用户')
                ->hideOnIndex(),
            DateTimeField::new('expiresTime', '过期时间')
                ->setColumns(3)
                ->setHelp('锁定的过期时间，为空表示永不过期')
                ->hideOnIndex(),
            DateTimeField::new('releasedTime', '释放时间')
                ->setColumns(3)
                ->setHelp('实际释放的时间')
                ->hideOnIndex(),
            TextareaField::new('releaseReason', '释放原因')
                ->setColumns(6)
                ->setNumOfRows(2)
                ->setHelp('说明释放锁定的原因')
                ->hideOnIndex(),
            CodeEditorField::new('batchIds', '批次ID列表')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('锁定的批次ID列表（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('quantities', '数量列表')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('对应批次的锁定数量列表（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('metadata', '扩展元数据')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('业务相关的扩展信息（JSON格式）')
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
            ->add(ChoiceFilter::new('type')->setChoices([
                '订单锁定' => 'order',
                '促销锁定' => 'promotion',
                '系统锁定' => 'system',
                '手动锁定' => 'manual',
            ]))
            ->add('businessId')
            ->add(ChoiceFilter::new('status')->setChoices([
                '有效' => 'active',
                '已过期' => 'expired',
                '已释放' => 'released',
            ]))
            ->add('reason')
            ->add('createdBy')
            ->add('releasedBy')
            ->add(DateTimeFilter::new('expiresTime'))
            ->add(DateTimeFilter::new('releasedTime'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
