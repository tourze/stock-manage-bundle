<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\HttpFoundation\Response;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Exception\InvalidOperationException;

/**
 * @extends AbstractCrudController<StockBatch>
 */
#[AdminCrud(routePath: '/stock/batch', routeName: 'stock_batch')]
final class StockBatchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return StockBatch::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('库存批次')
            ->setEntityLabelInPlural('库存批次')
            ->setPageTitle('index', '库存批次管理')
            ->setPageTitle('edit', '编辑库存批次')
            ->setPageTitle('detail', '库存批次详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(30)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->disable(Action::DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            AssociationField::new('sku', 'SKU')->setColumns(4),
            TextField::new('batchNo', '批次号')->setColumns(3),
            ChoiceField::new('status', '状态')
                ->setChoices([
                    '待处理' => 'pending',
                    '可用' => 'available',
                    '已过期' => 'expired',
                    '已损坏' => 'damaged',
                    '已消耗' => 'consumed',
                ])
                ->setColumns(2),
            ChoiceField::new('qualityLevel', '质量等级')
                ->setChoices([
                    'S级' => 'S',
                    'A级' => 'A',
                    'B级' => 'B',
                    'C级' => 'C',
                ])
                ->setColumns(2),
            IntegerField::new('quantity', '总库存')->setColumns(2),
            IntegerField::new('availableQuantity', '可用库存')->setColumns(2),
            IntegerField::new('reservedQuantity', '预占库存')->setColumns(2),
            IntegerField::new('lockedQuantity', '锁定数量')->setColumns(2)->hideOnIndex(),
            NumberField::new('unitCost', '单位成本')->setNumDecimals(2)->setColumns(2),
            TextField::new('locationId', '位置')->setColumns(2)->hideOnIndex(),
            DateTimeField::new('productionDate', '生产日期')->setColumns(3)->hideOnIndex(),
            DateTimeField::new('expiryDate', '过期时间')->setColumns(3),
            DateTimeField::new('createTime', '创建时间')->setColumns(3)->onlyOnIndex(),
            DateTimeField::new('updateTime', '更新时间')->setColumns(3)->onlyOnIndex(),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('sku')
            ->add('batchNo')
            ->add(ChoiceFilter::new('status')->setChoices([
                '待处理' => 'pending',
                '可用' => 'available',
                '已过期' => 'expired',
                '已损坏' => 'damaged',
                '已消耗' => 'consumed',
            ]))
            ->add(ChoiceFilter::new('qualityLevel')->setChoices([
                'S级' => 'S',
                'A级' => 'A',
                'B级' => 'B',
                'C级' => 'C',
            ]))
            ->add(NumericFilter::new('quantity'))
            ->add(NumericFilter::new('availableQuantity'))
            ->add(NumericFilter::new('reservedQuantity'))
            ->add(NumericFilter::new('lockedQuantity'))
            ->add(NumericFilter::new('unitCost'))
            ->add('locationId')
            ->add(DateTimeFilter::new('productionDate'))
            ->add(DateTimeFilter::new('expiryDate'))
            ->add(DateTimeFilter::new('createTime'))
            ->add(DateTimeFilter::new('updateTime'))
        ;
    }

    public function createEntity(string $entityFqcn)
    {
        throw new InvalidOperationException('批次必须通过入库流程创建，不能直接新建。请使用"库存入库"功能。');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        throw new InvalidOperationException('批次不能直接删除，请通过出库流程处理库存变动。');
    }

    public function new(AdminContext $context): Response
    {
        $this->addFlash('danger', '批次必须通过入库流程创建。请使用"库存入库"功能。');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class,
        ]);
    }

    public function delete(AdminContext $context): Response
    {
        $this->addFlash('danger', '批次不能直接删除。请通过出库流程处理库存变动。');

        return $this->redirectToRoute('admin', [
            'crudAction' => 'index',
            'crudControllerFqcn' => self::class,
        ]);
    }
}
