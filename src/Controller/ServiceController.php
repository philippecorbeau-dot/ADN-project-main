<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceController extends AbstractController
{
    private function loadCatalog(): array
    {
        $path = dirname(__DIR__, 2) . '/config/services_catalog.json';
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier de catalogue introuvable: ' . $path);
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Catalogue JSON invalide');
        }
        return $data;
    }

    #[Route('/services', name: 'services_index', methods: ['GET'])]
    public function index(): Response
    {
        $response = $this->render('services/index.html.twig', [
            'catalog' => $this->loadCatalog(),
        ]);
        $response->setPublic();
        $response->setMaxAge(600);
        $response->setSharedMaxAge(600);
        $response->headers->addCacheControlDirective('immutable', true);
        return $response;
    }

    #[Route('/services/gouvernance-familiale', name: 'services_legacy_gouvernance_redirect', methods: ['GET'])]
    public function legacyGouvernanceRedirect(UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        $url = $urlGenerator->generate('service_category', [
            'category' => 'conseil-strategique-du-dirigeant',
        ]);
        return $this->redirect($url, 301);
    }

    #[Route('/services/{category}', name: 'service_category', methods: ['GET'])]
    public function category(string $category): Response
    {
        $catalog = $this->loadCatalog();
        if (!array_key_exists($category, $catalog)) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        $response = $this->render('services/category.html.twig', [
            'categoryKey' => $category,
            'category' => $catalog[$category],
        ]);
        $response->setPublic();
        $response->setMaxAge(1200);
        $response->setSharedMaxAge(1200);
        $response->headers->addCacheControlDirective('immutable', true);
        return $response;
    }

    #[Route('/services/{category}/{slug}', name: 'service_detail', methods: ['GET'])]
    public function detail(string $category, string $slug, UrlGeneratorInterface $urlGenerator): Response
    {
        $catalog = $this->loadCatalog();
        if (!array_key_exists($category, $catalog)) {
            throw new NotFoundHttpException('Catégorie introuvable');
        }

        // Redirections explicites pour éléments supprimés
        $removed = [
            'structures-fiduciaires',
            'due-diligence',
            'gestion-sous-mandat',
        ];
        if (in_array($slug, $removed, true)) {
            $url = $urlGenerator->generate('service_category', ['category' => $category]);
            return $this->redirect($url, 301);
        }

        // Alias éventuel : "cession" -> "session" si l'utilisateur tape l'un ou l'autre
        if ($category === 'conseil-strategique-du-dirigeant' && $slug === 'cession') {
            $url = $urlGenerator->generate('service_detail', [
                'category' => $category,
                'slug' => 'session',
            ]);
            return $this->redirect($url, 301);
        }

        $categoryData = $catalog[$category];
        if (!array_key_exists($slug, $categoryData['items'])) {
            throw new NotFoundHttpException('Service introuvable');
        }

        $item = $categoryData['items'][$slug];

        $response = $this->render('services/detail.html.twig', [
            'categoryKey' => $category,
            'categoryLabel' => $categoryData['label'],
            'serviceSlug' => $slug,
            'serviceLabel' => is_array($item) ? ($item['label'] ?? $slug) : $item,
            'serviceMetaDescription' => is_array($item) ? ($item['meta_description'] ?? null) : null,
        ]);
        $response->setPublic();
        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);
        $response->headers->addCacheControlDirective('immutable', true);
        return $response;
    }
}


