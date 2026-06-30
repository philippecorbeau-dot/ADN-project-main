<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use App\Services\TwelveDataService;
use Symfony\Component\HttpFoundation\RequestStack;

#[Route('/admin/bourse')]
#[IsGranted('ROLE_USER')]
class BourseController extends AbstractController
{
    private $twelveDataService;
    private $requestStack;
    
    // Rôles autorisés pour ce module
    private const ALLOWED_ROLES = ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_ADMIN_MARKETING'];

    public function __construct(TwelveDataService $twelveDataService, RequestStack $requestStack)
    {
        $this->twelveDataService = $twelveDataService;
        $this->requestStack = $requestStack;
    }
    
    /**
     * Vérifie si l'utilisateur a accès au module bourse
     */
    private function checkAccess(): void
    {
        foreach (self::ALLOWED_ROLES as $role) {
            if ($this->isGranted($role)) {
                return;
            }
        }
        throw new AccessDeniedException('Vous n\'avez pas accès à ce module.');
    }

    #[Route('', name: 'admin_bourse', methods: ['GET'])]
    public function index(): Response
    {
        $this->checkAccess();
        $apiKey = $this->twelveDataService->getApiKey();
        
        if (!$apiKey) {
            $this->addFlash('error', 'Clé API Twelve Data non configurée');
            return $this->render('admin/bourse.html.twig', [
                'stocks' => [],
                'error' => 'Clé API non configurée',
                'market' => 'us'
            ]);
        }

        $market = $this->requestStack->getCurrentRequest()->query->get('market', 'us');
        
        switch ($market) {
            case 'cac40':
                $stocks = $this->twelveDataService->getCAC40Stocks();
                break;
            case 'china':
                $stocks = $this->twelveDataService->getChineseStocks();
                break;
            case 'germany':
                $stocks = $this->twelveDataService->getGermanStocks();
                break;
            case 'indices':
                $stocks = $this->twelveDataService->getIndices();
                break;
            default:
                $stocks = $this->twelveDataService->getDefaultStocks();
                break;
        }

        return $this->render('admin/bourse.html.twig', [
            'stocks' => $stocks,
            'error' => null,
            'market' => $market
        ]);
    }
} 