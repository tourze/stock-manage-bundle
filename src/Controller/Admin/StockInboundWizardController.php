<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StockInboundWizardController extends AbstractController
{
    #[Route(path: '/admin/stock/inbound-wizard', name: 'admin_stock_inbound_wizard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/stock/inbound/wizard_index.html.twig');
    }
}
