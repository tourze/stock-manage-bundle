<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\BundleItem;

/**
 * @extends AbstractCrudController<BundleItem>
 */
#[AdminCrud(routePath: '/stock/bundle-item', routeName: 'stock_bundle_item')]
final class BundleItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BundleItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('组合商品项目')
            ->setEntityLabelInPlural('组合商品项目')
            ->setPageTitle('index', '组合商品项目管理')
            ->setPageTitle('detail', '组合商品项目详情')
            ->setPageTitle('new', '新增组合商品项目')
            ->setPageTitle('edit', '编辑组合商品项目')
            ->setDefaultSort(['sortOrder' => 'ASC', 'createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('bundleStock', '组合商品')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('选择此项目所属的组合商品'),
            AssociationField::new('sku', 'SKU')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('选择此项目关联的SKU商品'),
            IntegerField::new('quantity', '数量')
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('此SKU在组合中的数量，必须大于0'),
            BooleanField::new('optional', '可选项目')
                ->setColumns(2)
                ->setHelp('是否为组合中的可选项目'),
            IntegerField::new('sortOrder', '排序序号')
                ->setColumns(2)
                ->setHelp('用于控制项目在组合中的显示顺序')
                ->hideOnIndex(),
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
            ->add('bundleStock')
            ->add('sku')
            ->add(NumericFilter::new('quantity'))
            ->add(BooleanFilter::new('optional'))
            ->add(NumericFilter::new('sortOrder'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
