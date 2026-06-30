<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin')]
    public function index(): Response
    {
        // Redirection automatique vers le nouveau back-office moderne
        return $this->redirectToRoute('admin_modern_dashboard');
    }
}
