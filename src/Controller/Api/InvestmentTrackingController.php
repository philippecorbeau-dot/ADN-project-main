<?php

namespace App\Controller\Api;

use App\Entity\InvestmentOpportunityClick;
use App\Repository\InvestmentOpportunityClickRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/investment', name: 'api_investment_')]
class InvestmentTrackingController extends AbstractController
{
    #[Route('/track-click', name: 'track_click', methods: ['POST'])]
    public function trackClick(Request $request, InvestmentOpportunityClickRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $productType = $data['product'] ?? null;
        $action = $data['action'] ?? null;
        
        // Validation des données
        if (!$productType || !$action) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }
        
        // Validation des valeurs autorisées
        if (!in_array($productType, array_keys(InvestmentOpportunityClick::PRODUCT_TYPES))) {
            return new JsonResponse(['error' => 'Invalid product type'], 400);
        }
        
        if (!in_array($action, [InvestmentOpportunityClick::ACTION_DISCOVER, InvestmentOpportunityClick::ACTION_DOCUMENTS])) {
            return new JsonResponse(['error' => 'Invalid action'], 400);
        }
        
        // Enregistrer le clic
        try {
            $click = $repository->recordClick(
                $productType,
                $action,
                $this->getUser(),
                $request->getClientIp(),
                $request->headers->get('User-Agent'),
                $request->headers->get('Referer')
            );
            
            return new JsonResponse([
                'success' => true,
                'click_id' => $click->getId(),
                'message' => 'Click tracked successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to track click'], 500);
        }
    }
    
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(InvestmentOpportunityClickRepository $repository): JsonResponse
    {
        // Accessible seulement aux admins
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }
        
        $stats = [
            'total_clicks' => $repository->getTotalClicks(),
            'clicks_by_product' => $repository->getClickStatsByProduct(),
            'clicks_by_action' => $repository->getClickStatsByAction(),
            'top_products' => $repository->getTopProducts(),
            'clicks_last_7_days' => $repository->getTotalClicksSince(new \DateTime('-7 days')),
            'clicks_last_30_days' => $repository->getTotalClicksSince(new \DateTime('-30 days')),
        ];
        
        return new JsonResponse($stats);
    }
}

