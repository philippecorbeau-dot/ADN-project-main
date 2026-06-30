<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour les redirections SEO des anciennes URLs WordPress
 * 
 * Ce contrôleur gère les redirections 301 des anciennes URLs du site WordPress
 * vers les nouvelles URLs du site Symfony, préservant ainsi le référencement.
 */
class LegacyRedirectController extends AbstractController
{
    /**
     * Redirection des anciennes URLs d'articles WordPress
     * 
     * Pattern WordPress : /YYYY/MM/DD/slug/
     * Pattern Symfony   : /actualites/article/slug
     * 
     * @param string $year  Année de publication (ignorée)
     * @param string $month Mois de publication (ignoré)
     * @param string $day   Jour de publication (ignoré)
     * @param string $slug  Slug de l'article (conservé)
     */
    #[Route('/{year}/{month}/{day}/{slug}', 
        name: 'legacy_wordpress_article',
        requirements: [
            'year' => '\d{4}',
            'month' => '\d{2}',
            'day' => '\d{2}',
            'slug' => '[a-z0-9\-]+'
        ],
        methods: ['GET']
    )]
    public function redirectWordPressArticle(string $year, string $month, string $day, string $slug): RedirectResponse
    {
        // Redirection 301 (permanente) vers la nouvelle URL
        return $this->redirectToRoute('news_show', ['slug' => $slug], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Redirection de l'ancienne page /articles/ vers /actualites
     */
    #[Route('/articles', name: 'legacy_wordpress_articles', methods: ['GET'])]
    #[Route('/articles/', name: 'legacy_wordpress_articles_slash', methods: ['GET'])]
    public function redirectWordPressArticlesList(): RedirectResponse
    {
        return $this->redirectToRoute('news_index', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * Redirection des anciennes catégories WordPress si nécessaire
     * Pattern : /category/nom-categorie/
     */
    #[Route('/category/{slug}', name: 'legacy_wordpress_category', methods: ['GET'])]
    #[Route('/category/{slug}/', name: 'legacy_wordpress_category_slash', methods: ['GET'])]
    public function redirectWordPressCategory(string $slug): RedirectResponse
    {
        // Redirection vers la page des actualités avec le filtre catégorie
        return $this->redirectToRoute('news_category', ['slug' => $slug], Response::HTTP_MOVED_PERMANENTLY);
    }
}

