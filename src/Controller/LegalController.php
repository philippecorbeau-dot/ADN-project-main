<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LegalController extends AbstractController
{
    #[Route('/mentions-legales', name: 'legal_mentions')]
    public function mentions(): Response
    {
        return $this->render('legal/mentions.html.twig', [
            'title' => 'Mentions légales',
        ]);
    }

    #[Route('/politique-de-confidentialite', name: 'legal_privacy')]
    public function privacy(): Response
    {
        return $this->render('legal/privacy.html.twig', [
            'title' => 'Politique de confidentialité',
        ]);
    }

    #[Route('/conditions-generales-de-vente', name: 'legal_terms')]
    public function terms(): Response
    {
        return $this->render('legal/terms.html.twig', [
            'title' => 'Conditions Générales de Vente (CGV)',
        ]);
    }

    #[Route('/cookies', name: 'legal_cookies')]
    public function cookies(): Response
    {
        return $this->render('legal/cookies.html.twig', [
            'title' => 'Cookies',
        ]);
    }
}
