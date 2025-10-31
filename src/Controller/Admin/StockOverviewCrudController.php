<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @extends AbstractCrudController<StockBatch>
 */
#[AdminCrud(routePath: '/stock/overview', routeName: 'stock_overview')]
final class StockOverviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockBatch::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存总览')
            ->setEntityLabelInPlural('库存总览')
            ->setPageTitle('index', '库存总览')
            ->setDefaultSort(['updateTime' => 'DESC'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('sku', 'SKU'),
            TextField::new('batchNo', '批次号'),
            IntegerField::new('availableQuantity', '可用库存'),
            IntegerField::new('reservedQuantity', '预占库存'),
            IntegerField::new('quantity', '总库存'),
            DateTimeField::new('expiryDate', '过期时间'),
            DateTimeField::new('createTime', '创建时间'),
            DateTimeField::new('updateTime', '更新时间'),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('sku')
            ->add('batchNo')
            ->add(NumericFilter::new('availableQuantity'))
            ->add(NumericFilter::new('quantity'))
            ->add(DateTimeFilter::new('expiryDate'))
            ->add(DateTimeFilter::new('createTime'))
        ;
    }
}
