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
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;
use Tourze\StockManageBundle\Entity\StockTransfer;
use Tourze\StockManageBundle\Enum\StockTransferStatus;

/**
 * @extends AbstractCrudController<StockTransfer>
 */
#[AdminCrud(routePath: '/stock/transfer', routeName: 'stock_transfer')]
final class StockTransferCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockTransfer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存调拨')
            ->setEntityLabelInPlural('库存调拨')
            ->setPageTitle('index', '库存调拨管理')
            ->setPageTitle('detail', '库存调拨详情')
            ->setPageTitle('new', '新增库存调拨')
            ->setPageTitle('edit', '编辑库存调拨')
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
        $statusField = EnumField::new('status', '调拨状态')
            ->setColumns(2)
            ->setHelp('当前调拨单的处理状态')
        ;
        $statusField->setEnumCases(StockTransferStatus::cases());

        return [
            TextField::new('transferNo', '调拨单号')
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('唯一的调拨单据编号'),
            AssociationField::new('sku', 'SKU')
                ->setColumns(3)
                ->setHelp('关联的SKU商品，为空表示多商品调拨'),
            TextField::new('fromLocation', '源位置')
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('库存调出的源仓库位置'),
            TextField::new('toLocation', '目标位置')
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('库存调入的目标仓库位置'),
            IntegerField::new('totalQuantity', '调拨总数量')
                ->setColumns(2)
                ->setHelp('所有商品的调拨数量总和'),
            $statusField,
            TextField::new('initiator', '发起人')
                ->setColumns(3)
                ->setHelp('发起调拨申请的用户')
                ->hideOnIndex(),
            TextField::new('receiver', '接收人')
                ->setColumns(3)
                ->setHelp('目标位置的接收确认人')
                ->hideOnIndex(),
            DateTimeField::new('shippedTime', '发出时间')
                ->setColumns(3)
                ->setHelp('库存实际发出的时间')
                ->hideOnIndex(),
            DateTimeField::new('receivedTime', '接收时间')
                ->setColumns(3)
                ->setHelp('目标位置确认接收的时间')
                ->hideOnIndex(),
            TextareaField::new('reason', '调拨原因')
                ->setColumns(6)
                ->setNumOfRows(3)
                ->setHelp('说明为什么需要进行此次调拨')
                ->hideOnIndex(),
            CodeEditorField::new('items', '调拨明细')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('详细的调拨商品清单（JSON格式）')
                ->onlyOnDetail(),
            CodeEditorField::new('metadata', '附加信息')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('调拨相关的扩展信息（JSON格式）')
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
            ->add('transferNo')
            ->add('sku')
            ->add('fromLocation')
            ->add('toLocation')
            ->add(NumericFilter::new('totalQuantity'))
            ->add(ChoiceFilter::new('status')->setChoices(array_reduce(
                StockTransferStatus::cases(),
                static fn (array $choices, StockTransferStatus $status): array => $choices + [$status->getLabel() => $status->value],
                []
            )))
            ->add('initiator')
            ->add('receiver')
            ->add(DateTimeFilter::new('shippedTime'))
            ->add(DateTimeFilter::new('receivedTime'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
