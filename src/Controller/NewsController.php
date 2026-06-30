<?php

namespace App\Controller;

use App\Entity\Blog\Post;
use App\Entity\Blog\Category;
use App\Repository\Blog\PostRepository;
use App\Repository\Blog\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/actualites', name: 'news_')]
class NewsController extends AbstractController
{
    private PostRepository $postRepository;
    private CategoryRepository $categoryRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        PostRepository $postRepository,
        CategoryRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->postRepository = $postRepository;
        $this->categoryRepository = $categoryRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        // Récupération des articles publiés
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.user', 'u')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.publicationDateStart', 'DESC');

        // Filtrage par catégorie si demandé
        $categorySlug = $request->query->get('category');
        $selectedCategory = null;
        if ($categorySlug) {
            $selectedCategory = $this->categoryRepository->findOneBy(['seoSlug' => $categorySlug]);
            if ($selectedCategory) {
                $queryBuilder->andWhere('p.category = :category')
                    ->setParameter('category', $selectedCategory);
            }
        }

        // Pagination
        $posts = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            12 // 12 articles par page
        );

        // Récupération des catégories pour le menu
        $categories = $this->categoryRepository->findAll();

        // Articles à la une (priorité aux articles marqués "à la Une", sinon les 3 derniers)
        $featuredPosts = $this->postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->where('p.status = :status')
            ->andWhere('p.isFeatured = :featured')
            ->setParameter('status', 1)
            ->setParameter('featured', true)
            ->orderBy('p.publicationDateStart', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        
        // Si aucun article "à la Une", prendre les 3 derniers articles
        if (count($featuredPosts) < 2) {
            $featuredPosts = $this->postRepository->createQueryBuilder('p')
                ->leftJoin('p.category', 'c')
                ->where('p.status = :status')
                ->setParameter('status', 1)
                ->orderBy('p.publicationDateStart', 'DESC')
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();
        }

        // Articles populaires (simulation - à améliorer avec un système de vues)
        $popularPosts = $this->postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return $this->render('news/index.html.twig', [
            'posts' => $posts,
            'categories' => $categories,
            'selectedCategory' => $selectedCategory,
            'featuredPosts' => $featuredPosts,
            'popularPosts' => $popularPosts,
            'seo' => [
                'title' => $selectedCategory 
                    ? 'Actualités ' . $selectedCategory->getName() . ' - ADN Family Office'
                    : 'Actualités financières et patrimoniales - ADN Family Office',
                'description' => $selectedCategory
                    ? 'Découvrez nos dernières actualités sur ' . $selectedCategory->getName() . '. Conseils d\'experts, analyses de marché et stratégies d\'investissement.'
                    : 'Restez informé des dernières actualités financières et patrimoniales. Analyses d\'experts, tendances de marché et conseils en investissement.',
                'canonical' => $this->generateUrl('news_index', $categorySlug ? ['category' => $categorySlug] : []),
                'keywords' => $selectedCategory
                    ? $selectedCategory->getName() . ', actualités financières, investissement, patrimoine'
                    : 'actualités financières, investissement, patrimoine, family office, gestion de patrimoine'
            ]
        ]);
    }

    #[Route('/article/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        // Récupération de l'article
        $post = $this->postRepository->findOneBy([
            'seoSlug' => $slug,
            'status' => 1
        ]);

        if (!$post || $post->getPublicationDateStart() > new \DateTime()) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        // Articles similaires (même catégorie)
        $relatedPosts = $this->postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->andWhere('p.category = :category')
            ->andWhere('p.id != :currentId')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->setParameter('category', $post->getCategory())
            ->setParameter('currentId', $post->getId())
            ->orderBy('p.publicationDateStart', 'DESC')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        // Articles récents pour la sidebar
        $recentPosts = $this->postRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->andWhere('p.id != :currentId')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->setParameter('currentId', $post->getId())
            ->orderBy('p.publicationDateStart', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Génération des données structurées JSON-LD
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->getTitle(),
            'description' => $post->getSeoDescription(),
            'author' => [
                '@type' => 'Organization',
                'name' => 'ADN Family Office'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'ADN Family Office',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->generateUrl('app_home', [], true) . 'build/theme/images/ADN_Logo_enlarged_x5.png'
                ]
            ],
            'datePublished' => $post->getPublicationDateStart()->format('c'),
            'dateModified' => $post->getUpdatedAt() ? $post->getUpdatedAt()->format('c') : $post->getPublicationDateStart()->format('c'),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $this->generateUrl('news_show', ['slug' => $post->getSeoSlug()], true)
            ]
        ];

        if ($post->getImageName()) {
            $jsonLd['image'] = [
                '@type' => 'ImageObject',
                'url' => $this->generateUrl('app_home', [], true) . 'uploads/images/' . $post->getImageName(),
                'alt' => $post->getImageAlt() ?: $post->getTitle()
            ];
        }

        return $this->render('news/show.html.twig', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'recentPosts' => $recentPosts,
            'jsonLd' => $jsonLd,
            'seo' => [
                'title' => $post->getSeoTitle() ?: $post->getTitle() . ' - ADN Family Office',
                'description' => $post->getSeoDescription(),
                'canonical' => $this->generateUrl('news_show', ['slug' => $post->getSeoSlug()], true),
                'keywords' => $post->getCategory()->getName() . ', ' . implode(', ', array_slice(explode(' ', $post->getTitle()), 0, 5)),
                'ogImage' => $post->getImageName() ? $this->generateUrl('app_home', [], true) . 'uploads/images/' . $post->getImageName() : null,
                'publishedTime' => $post->getPublicationDateStart()->format('c'),
                'modifiedTime' => $post->getUpdatedAt() ? $post->getUpdatedAt()->format('c') : null
            ]
        ]);
    }

    #[Route('/category/{slug}', name: 'category', methods: ['GET'])]
    public function category(string $slug, Request $request, PaginatorInterface $paginator): Response
    {
        // Récupération de la catégorie
        $category = $this->categoryRepository->findOneBy(['seoSlug' => $slug]);
        
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        // Récupération des articles de la catégorie
        $queryBuilder = $this->postRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.user', 'u')
            ->where('p.status = :status')
            ->andWhere('p.publicationDateStart <= :now')
            ->andWhere('p.category = :category')
            ->setParameter('status', 1)
            ->setParameter('now', new \DateTime())
            ->setParameter('category', $category)
            ->orderBy('p.publicationDateStart', 'DESC');

        // Pagination
        $posts = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            12
        );

        // Toutes les catégories pour le menu
        $categories = $this->categoryRepository->findAll();

        return $this->render('news/category.html.twig', [
            'category' => $category,
            'posts' => $posts,
            'categories' => $categories,
            'seo' => [
                'title' => $category->getSeoTitle() ?: 'Actualités ' . $category->getName() . ' - ADN Family Office',
                'description' => $category->getSeoDescription() ?: 'Découvrez nos actualités sur ' . $category->getName() . '. Analyses d\'experts et conseils en investissement.',
                'canonical' => $this->generateUrl('news_category', ['slug' => $category->getSeoSlug()], true),
                'keywords' => $category->getName() . ', actualités, investissement, patrimoine, family office'
            ]
        ]);
    }
}

