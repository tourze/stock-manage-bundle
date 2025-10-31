<?php

declare(strict_types=1);

namespace Tourze\StockManageBundle\Controller\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StockInboundReturnWizardController extends AbstractInboundWizardController
{
    #[Route(path: '/admin/stock/inbound-wizard/return', name: 'admin_stock_inbound_return_wizard', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                /** @var array<string, mixed> $formData */
                $formData = $request->request->all();
                $data = $this->processFormData($formData, 'return');
                $inbound = $this->inboundService->returnInbound($data);

                $this->addFlash('success', sprintf('退货入库单 %s 创建成功！', $inbound->getReferenceNo()));

                return $this->redirectToRoute('admin_stock_inbound_return_wizard');
            } catch (\Exception $e) {
                $this->addFlash('danger', '入库失败：' . $e->getMessage());
            }
        }

        return $this->render('admin/stock/inbound/return_wizard.html.twig', [
            'generatedBatchNo' => $this->inboundService->generateBatchNo('return'),
        ]);
    }
}
