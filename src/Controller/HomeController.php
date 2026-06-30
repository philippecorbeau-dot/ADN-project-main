<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\InvestmentComparisonRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(InvestmentComparisonRepository $repo): Response
    {
        $matrix = $repo->getMatrix();
        $response = $this->render('theme/homepage-wrapper.html.twig', [
            'comparison' => $matrix,
        ]);

        // Cache HTTP: courte durée pour la home (ex: 120s) + public
        $response->setPublic();
        $response->setMaxAge(120);
        $response->setSharedMaxAge(120);
        $response->headers->addCacheControlDirective('immutable', true);
        return $response;
    }
}
