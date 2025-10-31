<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Tourze\StockManageBundle\Entity\BundleStock;

/**
 * @extends AbstractCrudController<BundleStock>
 */
#[AdminCrud(routePath: '/stock/bundle-stock', routeName: 'stock_bundle_stock')]
final class BundleStockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BundleStock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('组合商品')
            ->setEntityLabelInPlural('组合商品')
            ->setPageTitle('index', '组合商品管理')
            ->setPageTitle('detail', '组合商品详情')
            ->setPageTitle('new', '新增组合商品')
            ->setPageTitle('edit', '编辑组合商品')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(20)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('bundleCode', '组合商品编码')
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('唯一的组合商品编码，用于系统识别'),
            TextField::new('bundleName', '组合商品名称')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('组合商品的显示名称'),
            ChoiceField::new('type', '组合类型')
                ->setChoices([
                    '固定组合' => 'fixed',
                    '灵活组合' => 'flexible',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('固定组合：项目不可变更；灵活组合：可选择性项目'),
            ChoiceField::new('status', '状态')
                ->setChoices([
                    '有效' => 'active',
                    '无效' => 'inactive',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('组合商品的启用状态'),
            TextareaField::new('description', '描述')
                ->setColumns(6)
                ->setNumOfRows(3)
                ->setHelp('组合商品的详细描述')
                ->hideOnIndex(),
            IntegerField::new('totalItemCount', '项目总数')
                ->setColumns(2)
                ->onlyOnIndex()
                ->setHelp('该组合包含的项目总数'),
            CollectionField::new('items', '组合项目')
                ->setTemplatePath('admin/bundle_stock/items_field.html.twig')
                ->onlyOnDetail()
                ->setHelp('组合商品包含的所有项目明细'),
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
            ->add('bundleCode')
            ->add('bundleName')
            ->add(ChoiceFilter::new('type')->setChoices([
                '固定组合' => 'fixed',
                '灵活组合' => 'flexible',
            ]))
            ->add(ChoiceFilter::new('status')->setChoices([
                '有效' => 'active',
                '无效' => 'inactive',
            ]))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
