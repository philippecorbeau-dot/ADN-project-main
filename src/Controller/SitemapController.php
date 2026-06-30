<?php

namespace App\Controller;

use App\Repository\Blog\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur pour générer le sitemap XML dynamique
 * Important pour le SEO et l'indexation Google
 */
class SitemapController extends AbstractController
{
    private function loadServicesCatalog(): array
    {
        $path = dirname(__DIR__, 2) . '/config/services_catalog.json';
        if (!is_file($path)) {
            return [];
        }
        $json = file_get_contents($path);
        return json_decode($json, true) ?? [];
    }

    /**
     * Hôte canonique du site (sans www) — utilisé pour construire les URLs
     * absolues du sitemap quel que soit l'hôte d'accès.
     */
    private const CANONICAL_HOST = 'https://adnfamilyoffice.fr';

    /**
     * Génère une URL absolue canonique pour une route, en utilisant toujours
     * le hostname canonique défini ci-dessus (évite les variantes www/non-www).
     */
    private function canonicalUrl(UrlGeneratorInterface $urlGenerator, string $route, array $params = []): ?string
    {
        try {
            $path = $urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH);
            return self::CANONICAL_HOST . $path;
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Route('/sitemap.xml', name: 'sitemap_xml', defaults: ['_format' => 'xml'])]
    public function sitemap(UrlGeneratorInterface $urlGenerator, PostRepository $postRepository): Response
    {
        $urls = [];
        $hostname = self::CANONICAL_HOST;

        // Page d'accueil - priorité maximale
        if ($loc = $this->canonicalUrl($urlGenerator, 'app_home')) {
            $urls[] = [
                'loc' => $loc,
                'lastmod' => date('Y-m-d'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ];
        }

        // Pages principales
        $mainPages = [
            ['route' => 'services_index', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['route' => 'app_expertise', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['route' => 'app_contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['route' => 'news_index', 'priority' => '0.8', 'changefreq' => 'daily'],
        ];

        foreach ($mainPages as $page) {
            if ($loc = $this->canonicalUrl($urlGenerator, $page['route'])) {
                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => $page['changefreq'],
                    'priority' => $page['priority'],
                ];
            }
        }

        // Pages de catégories de services
        $catalog = $this->loadServicesCatalog();
        foreach ($catalog as $categoryKey => $categoryData) {
            if ($loc = $this->canonicalUrl($urlGenerator, 'service_category', ['category' => $categoryKey])) {
                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            }

            // Pages de détail de services
            if (isset($categoryData['items']) && is_array($categoryData['items'])) {
                foreach ($categoryData['items'] as $serviceKey => $serviceData) {
                    if ($loc = $this->canonicalUrl($urlGenerator, 'service_detail', [
                        'category' => $categoryKey,
                        'slug' => $serviceKey,
                    ])) {
                        $urls[] = [
                            'loc' => $loc,
                            'lastmod' => date('Y-m-d'),
                            'changefreq' => 'monthly',
                            'priority' => '0.7',
                        ];
                    }
                }
            }
        }

        // Articles de blog (status=1, publication_date_start <= maintenant, disable_in_sitemap=0)
        $blogPosts = $postRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->andWhere('p.disableInSitemap = false')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.publicationDateStart', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($blogPosts as $post) {
            if ($loc = $this->canonicalUrl($urlGenerator, 'news_show', ['slug' => $post->getSeoSlug()])) {
                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => $post->getUpdatedAt()->format('Y-m-d'),
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                ];
            }
        }

        // Pages légales
        $legalPages = [
            'legal_mentions',
            'legal_privacy',
            'legal_terms',
            'legal_cookies',
        ];

        foreach ($legalPages as $route) {
            if ($loc = $this->canonicalUrl($urlGenerator, $route)) {
                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => date('Y-m-d'),
                    'changefreq' => 'yearly',
                    'priority' => '0.3',
                ];
            }
        }

        // Générer le XML
        $xml = $this->renderView('sitemap/sitemap.xml.twig', [
            'urls' => $urls,
            'hostname' => $hostname,
        ]);

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');
        
        // Cache public de 24h pour le sitemap
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->setSharedMaxAge(86400);

        return $response;
    }

    #[Route('/robots.txt', name: 'robots_txt', defaults: ['_format' => 'txt'])]
    public function robots(UrlGeneratorInterface $urlGenerator): Response
    {
        // Utilise toujours l'hôte canonique pour le sitemap (évite www/non-www)
        $sitemapUrl = $this->canonicalUrl($urlGenerator, 'sitemap_xml') ?? (self::CANONICAL_HOST . '/sitemap.xml');
        
        $content = <<<ROBOTS
# Robots.txt pour ADN Family Office
# https://adnfamilyoffice.fr

User-agent: *
Allow: /

# Interdire les pages d'administration et utilisateur privé
Disallow: /admin/
Disallow: /admin_modern/
Disallow: /user/
Disallow: /login
Disallow: /register
Disallow: /reset-password

# Interdire les ressources techniques
Disallow: /_profiler/
Disallow: /_wdt/

# Sitemap
Sitemap: {$sitemapUrl}

# Directives pour les crawlers spécifiques
User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /

# Délai de crawl pour éviter la surcharge
Crawl-delay: 1
ROBOTS;

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        
        // Cache de 7 jours
        $response->setPublic();
        $response->setMaxAge(604800);

        return $response;
    }
}

