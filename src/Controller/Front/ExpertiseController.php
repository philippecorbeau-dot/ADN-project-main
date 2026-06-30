<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExpertiseController extends AbstractController
{
    #[Route('/expertise', name: 'app_expertise')]
    public function index(): Response
    {
        return $this->render('front/expertise/index.html.twig', [
            'controller_name' => 'ExpertiseController',
        ]);
    }
}
