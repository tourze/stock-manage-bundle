<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Tourze\StockManageBundle\Entity\VirtualStock;

/**
 * @extends AbstractCrudController<VirtualStock>
 */
#[AdminCrud(routePath: '/stock/virtual', routeName: 'stock_virtual')]
final class VirtualStockCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return VirtualStock::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('虚拟库存')
            ->setEntityLabelInPlural('虚拟库存')
            ->setPageTitle('index', '虚拟库存管理')
            ->setPageTitle('detail', '虚拟库存详情')
            ->setPageTitle('new', '新增虚拟库存')
            ->setPageTitle('edit', '编辑虚拟库存')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IntegerField::new('id', 'ID')
                ->setColumns(2)
                ->onlyOnIndex(),
            AssociationField::new('sku', 'SKU')
                ->setColumns(4)
                ->setRequired(true)
                ->setHelp('关联的SKU商品'),
            ChoiceField::new('virtualType', '虚拟库存类型')
                ->setChoices([
                    '预售' => 'presale',
                    '期货' => 'futures',
                    '代销' => 'dropship',
                    '缺货预订' => 'backorder',
                ])
                ->setColumns(3)
                ->setRequired(true)
                ->setHelp('虚拟库存的业务类型'),
            IntegerField::new('quantity', '虚拟库存数量')
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('虚拟库存的可用数量'),
            ChoiceField::new('status', '状态')
                ->setChoices([
                    '有效' => 'active',
                    '无效' => 'inactive',
                    '已转换' => 'converted',
                ])
                ->setColumns(2)
                ->setRequired(true)
                ->setHelp('虚拟库存的当前状态'),
            DateTimeField::new('expectedDate', '预期到达日期')
                ->setColumns(3)
                ->setHelp('预计转为实际库存的日期')
                ->hideOnIndex(),
            TextField::new('businessId', '业务ID')
                ->setColumns(3)
                ->setHelp('关联的业务单据ID（订单号、采购单号等）')
                ->hideOnIndex(),
            TextField::new('locationId', '位置ID')
                ->setColumns(2)
                ->setHelp('关联的仓库位置')
                ->hideOnIndex(),
            TextareaField::new('description', '描述')
                ->setColumns(6)
                ->setNumOfRows(3)
                ->setHelp('虚拟库存的详细描述和说明')
                ->hideOnIndex(),
            CodeEditorField::new('attributes', '扩展属性')
                ->setLanguage('javascript')
                ->setColumns(6)
                ->setHelp('虚拟库存的扩展属性信息（JSON格式）')
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
            ->add('sku')
            ->add(ChoiceFilter::new('virtualType')->setChoices([
                '预售' => 'presale',
                '期货' => 'futures',
                '代销' => 'dropship',
                '缺货预订' => 'backorder',
            ]))
            ->add(NumericFilter::new('quantity'))
            ->add(ChoiceFilter::new('status')->setChoices([
                '有效' => 'active',
                '无效' => 'inactive',
                '已转换' => 'converted',
            ]))
            ->add(DateTimeFilter::new('expectedDate'))
            ->add('businessId')
            ->add('locationId')
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }
}
