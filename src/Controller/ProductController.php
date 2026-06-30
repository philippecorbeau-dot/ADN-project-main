<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/produits', name: 'product_')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    #[Route('/scpi', name: 'scpi')]
    public function scpi(): Response
    {
        return $this->render('product/scpi.html.twig', [
            'title' => 'SCPI - Société Civile de Placement Immobilier',
            'breadcrumb' => [
                ['label' => 'Accueil', 'path' => 'app_home'],
                ['label' => 'Produits', 'path' => null],
                ['label' => 'SCPI', 'path' => null]
            ]
        ]);
    }

    #[Route('/pea-pme', name: 'pea_pme')]
    public function peaPme(): Response
    {
        return $this->render('product/pea-pme.html.twig', [
            'title' => 'PEA-PME - Plan d\'Épargne en Actions PME',
            'breadcrumb' => [
                ['label' => 'Accueil', 'path' => 'app_home'],
                ['label' => 'Produits', 'path' => null],
                ['label' => 'PEA-PME', 'path' => null]
            ]
        ]);
    }

    #[Route('/assurance-vie', name: 'assurance_vie')]
    public function assuranceVie(): Response
    {
        return $this->render('product/assurance-vie.html.twig', [
            'title' => 'Assurance-vie - Solutions d\'épargne et de transmission',
            'breadcrumb' => [
                ['label' => 'Accueil', 'path' => 'app_home'],
                ['label' => 'Produits', 'path' => null],
                ['label' => 'Assurance-vie', 'path' => null]
            ]
        ]);
    }

    #[Route('/per', name: 'per')]
    public function per(): Response
    {
        return $this->render('product/per.html.twig', [
            'title' => 'PER - Plan d\'Épargne Retraite',
            'breadcrumb' => [
                ['label' => 'Accueil', 'path' => 'app_home'],
                ['label' => 'Produits', 'path' => null],
                ['label' => 'PER', 'path' => null]
            ]
        ]);
    }
}
