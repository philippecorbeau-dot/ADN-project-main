<?php

namespace App\Command;

use App\Entity\Blog\Category;
use App\Entity\Blog\Post;
use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-news',
    description: 'Crée des données de test pour les actualités',
)]
class CreateTestNewsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Créer les catégories si elles n'existent pas
        $categories = [
            [
                'name' => 'Investissement',
                'seoSlug' => 'investissement',
                'description' => 'Découvrez nos conseils et analyses sur les différentes stratégies d\'investissement pour optimiser votre patrimoine.',
                'seoTitle' => 'Actualités Investissement - Conseils et Stratégies | ADN Family Office',
                'seoDescription' => 'Restez informé des dernières tendances d\'investissement avec nos analyses d\'experts. Stratégies patrimoniales et conseils personnalisés.'
            ],
            [
                'name' => 'Fiscalité',
                'seoSlug' => 'fiscalite',
                'description' => 'Optimisez votre fiscalité avec nos conseils d\'experts et restez informé des dernières évolutions réglementaires.',
                'seoTitle' => 'Actualités Fiscalité - Optimisation et Conseils | ADN Family Office',
                'seoDescription' => 'Découvrez nos conseils en optimisation fiscale et les dernières évolutions réglementaires pour optimiser votre patrimoine.'
            ],
            [
                'name' => 'Immobilier',
                'seoSlug' => 'immobilier',
                'description' => 'Suivez les tendances du marché immobilier et découvrez nos conseils pour vos investissements immobiliers.',
                'seoTitle' => 'Actualités Immobilier - Marché et Investissements | ADN Family Office',
                'seoDescription' => 'Analyses du marché immobilier, tendances et conseils pour vos investissements immobiliers par nos experts.'
            ],
            [
                'name' => 'SCPI',
                'seoSlug' => 'scpi',
                'description' => 'Tout savoir sur les SCPI : performances, analyses et conseils pour bien choisir vos investissements.',
                'seoTitle' => 'Actualités SCPI - Performances et Analyses | ADN Family Office',
                'seoDescription' => 'Suivez les performances des SCPI et découvrez nos analyses pour optimiser vos investissements immobiliers.'
            ],
            [
                'name' => 'Assurance-vie',
                'seoSlug' => 'assurance-vie',
                'description' => 'Optimisez votre assurance-vie avec nos conseils d\'experts et découvrez les meilleures stratégies de placement.',
                'seoTitle' => 'Actualités Assurance-vie - Conseils et Stratégies | ADN Family Office',
                'seoDescription' => 'Conseils d\'experts en assurance-vie, stratégies de placement et optimisation fiscale pour votre épargne.'
            ]
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $category = $this->entityManager->getRepository(Category::class)
                ->findOneBy(['seoSlug' => $categoryData['seoSlug']]);
            
            if (!$category) {
                $category = new Category();
                $category->setName($categoryData['name']);
                $category->setDescription($categoryData['description']);
                $category->setSeoTitle($categoryData['seoTitle']);
                $category->setSeoDescription($categoryData['seoDescription']);
                // Le slug sera généré automatiquement par Gedmo
                
                $this->entityManager->persist($category);
                $io->success("Catégorie créée : {$categoryData['name']}");
            }
            
            $createdCategories[] = $category;
        }

        // Récupérer un utilisateur admin ou créer un utilisateur de test
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@adnfamilyoffice.fr']);
        if (!$user) {
            $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        }

        // Créer des articles de test
        $articles = [
            [
                'title' => 'Les tendances d\'investissement pour 2024 : où placer son argent ?',
                'content' => '<h2>Une année charnière pour les investisseurs</h2><p>L\'année 2024 s\'annonce particulièrement intéressante pour les investisseurs avisés. Entre les évolutions des taux d\'intérêt et les nouvelles opportunités qui émergent, il est crucial de bien positionner son portefeuille.</p><h3>Les secteurs porteurs</h3><p>Plusieurs secteurs se démarquent cette année :</p><ul><li>La technologie verte et les énergies renouvelables</li><li>L\'immobilier commercial dans les zones urbaines</li><li>Les SCPI spécialisées dans la logistique</li><li>Les fonds thématiques ESG</li></ul><blockquote>L\'investissement responsable n\'est plus une option mais une nécessité pour construire un patrimoine durable.</blockquote><h3>Nos recommandations</h3><p>Chez ADN Family Office, nous recommandons une approche diversifiée qui combine sécurité et performance. N\'hésitez pas à nous contacter pour une analyse personnalisée de votre situation.</p>',
                'category' => 0, // Investissement
                'seoTitle' => 'Tendances Investissement 2024 - Où Placer son Argent | ADN Family Office',
                'seoDescription' => 'Découvrez les meilleures opportunités d\'investissement pour 2024. Conseils d\'experts et analyses de marché par ADN Family Office.',
                'imageAlt' => 'Graphiques et tendances d\'investissement 2024'
            ],
            [
                'title' => 'Réforme fiscale 2024 : impact sur votre patrimoine',
                'content' => '<h2>Les principales mesures de la réforme</h2><p>La réforme fiscale 2024 apporte son lot de changements significatifs pour les contribuables français. Il est essentiel de comprendre ces évolutions pour adapter sa stratégie patrimoniale.</p><h3>Nouveautés sur l\'IFI</h3><p>L\'Impôt sur la Fortune Immobilière évolue avec de nouveaux seuils et abattements. Ces modifications impactent directement les stratégies de détention immobilière.</p><h3>Assurance-vie et fiscalité</h3><p>Les contrats d\'assurance-vie bénéficient de nouvelles mesures avantageuses, notamment pour les versements après 70 ans.</p><blockquote>Une optimisation fiscale réussie nécessite une approche globale et personnalisée.</blockquote><p>Nos experts vous accompagnent dans l\'adaptation de votre stratégie patrimoniale aux nouvelles règles fiscales.</p>',
                'category' => 1, // Fiscalité
                'seoTitle' => 'Réforme Fiscale 2024 - Impact Patrimoine | ADN Family Office',
                'seoDescription' => 'Analyse complète de la réforme fiscale 2024 et son impact sur votre patrimoine. Conseils d\'optimisation par nos experts.',
                'imageAlt' => 'Réforme fiscale 2024 et impact patrimonial'
            ],
            [
                'title' => 'Marché immobilier : analyse des prix au T4 2024',
                'content' => '<h2>Une stabilisation après la tempête</h2><p>Le marché immobilier français montre des signes de stabilisation au quatrième trimestre 2024, après une période de forte volatilité.</p><h3>Évolution des prix par région</h3><p>Les disparités régionales se creusent avec des dynamiques différenciées :</p><ul><li>Île-de-France : stabilisation des prix</li><li>Métropoles régionales : légère baisse</li><li>Littoral : résistance des prix</li><li>Zones rurales : dynamisme maintenu</li></ul><h3>Perspectives pour 2025</h3><p>Les experts s\'accordent sur une reprise progressive du marché, soutenue par :</p><ul><li>L\'assouplissement des conditions de crédit</li><li>Le retour de la confiance des investisseurs</li><li>Les nouveaux dispositifs d\'aide à l\'investissement</li></ul><blockquote>C\'est le moment idéal pour saisir les opportunités immobilières avec un accompagnement professionnel.</blockquote>',
                'category' => 2, // Immobilier
                'seoTitle' => 'Marché Immobilier T4 2024 - Analyse Prix | ADN Family Office',
                'seoDescription' => 'Analyse détaillée du marché immobilier au T4 2024. Évolution des prix, tendances et perspectives par nos experts.',
                'imageAlt' => 'Analyse marché immobilier et évolution des prix'
            ],
            [
                'title' => 'SCPI : performances et sélection 2024',
                'content' => '<h2>Un bilan contrasté mais prometteur</h2><p>Les SCPI affichent des performances variables en 2024, avec des disparités importantes selon les typologies d\'actifs et les stratégies de gestion.</p><h3>Top performers de l\'année</h3><p>Certaines SCPI se distinguent par leurs excellentes performances :</p><ul><li>SCPI de bureaux : rendement moyen de 4,2%</li><li>SCPI commerciales : 3,8% de rendement</li><li>SCPI logistiques : 4,5% en moyenne</li><li>SCPI santé : 4,1% de performance</li></ul><h3>Critères de sélection</h3><p>Pour bien choisir ses SCPI, plusieurs facteurs sont déterminants :</p><ul><li>La qualité de la société de gestion</li><li>La diversification géographique</li><li>La typologie des actifs</li><li>L\'historique de performance</li><li>Les frais de gestion</li></ul><blockquote>Une sélection rigoureuse des SCPI est la clé d\'un investissement immobilier réussi.</blockquote><p>Notre équipe analyse en permanence le marché des SCPI pour vous proposer les meilleures opportunités.</p>',
                'category' => 3, // SCPI
                'seoTitle' => 'SCPI Performances 2024 - Sélection et Conseils | ADN Family Office',
                'seoDescription' => 'Découvrez les meilleures SCPI 2024, leurs performances et nos conseils de sélection. Analyse experte par ADN Family Office.',
                'imageAlt' => 'Performances SCPI 2024 et critères de sélection'
            ],
            [
                'title' => 'Assurance-vie : nouvelles stratégies de gestion',
                'content' => '<h2>Adapter sa gestion aux nouveaux enjeux</h2><p>L\'assurance-vie reste l\'un des placements préférés des Français, mais les stratégies de gestion évoluent pour s\'adapter aux nouveaux enjeux économiques.</p><h3>Diversification des supports</h3><p>La tendance est à la diversification avec de nouveaux supports d\'investissement :</p><ul><li>Fonds ESG et investissement responsable</li><li>SCPI et OPCI pour l\'exposition immobilière</li><li>Fonds structurés pour la protection du capital</li><li>Investissements alternatifs</li></ul><h3>Gestion pilotée vs gestion libre</h3><p>Le choix entre gestion pilotée et gestion libre dépend de plusieurs facteurs :</p><ul><li>Votre profil de risque</li><li>Vos connaissances financières</li><li>Le temps que vous souhaitez consacrer</li><li>Vos objectifs patrimoniaux</li></ul><blockquote>Une assurance-vie bien gérée peut générer des performances attractives tout en préservant la sécurité du capital.</blockquote><h3>Optimisation fiscale</h3><p>L\'assurance-vie offre de nombreux avantages fiscaux qu\'il convient d\'optimiser selon votre situation personnelle.</p>',
                'category' => 4, // Assurance-vie
                'seoTitle' => 'Assurance-vie Stratégies 2024 - Gestion et Performance | ADN Family Office',
                'seoDescription' => 'Nouvelles stratégies de gestion en assurance-vie. Conseils d\'experts pour optimiser votre contrat et vos performances.',
                'imageAlt' => 'Stratégies de gestion assurance-vie et optimisation'
            ],
            [
                'title' => 'PEA-PME : opportunités et risques en 2024',
                'content' => '<h2>Un dispositif méconnu mais attractif</h2><p>Le PEA-PME offre des opportunités intéressantes pour les investisseurs souhaitant soutenir les petites et moyennes entreprises tout en bénéficiant d\'avantages fiscaux.</p><h3>Les avantages du PEA-PME</h3><ul><li>Exonération d\'impôt sur les plus-values après 5 ans</li><li>Plafond de versement de 225 000 €</li><li>Possibilité de cumuler avec un PEA classique</li><li>Soutien à l\'économie française</li></ul><h3>Sélection des valeurs</h3><p>La sélection des entreprises éligibles nécessite une analyse approfondie :</p><ul><li>Solidité financière</li><li>Perspectives de croissance</li><li>Secteur d\'activité porteur</li><li>Équipe dirigeante expérimentée</li></ul><blockquote>Le PEA-PME combine utilité économique et optimisation fiscale pour les investisseurs avisés.</blockquote>',
                'category' => 0, // Investissement
                'seoTitle' => 'PEA-PME 2024 - Opportunités et Stratégies | ADN Family Office',
                'seoDescription' => 'Découvrez les opportunités du PEA-PME en 2024. Conseils de sélection et stratégies d\'investissement par nos experts.',
                'imageAlt' => 'PEA-PME opportunités et stratégies d\'investissement'
            ]
        ];

        $this->entityManager->flush(); // Flush les catégories d'abord

        foreach ($articles as $index => $articleData) {
            // Vérifier si l'article existe déjà
            $existingPost = $this->entityManager->getRepository(Post::class)
                ->findOneBy(['title' => $articleData['title']]);
            
            if ($existingPost) {
                continue;
            }

            $post = new Post();
            $post->setTitle($articleData['title']);
            $post->setContent($articleData['content']);
            $post->setSeoTitle($articleData['seoTitle']);
            $post->setSeoDescription($articleData['seoDescription']);
            $post->setImageAlt($articleData['imageAlt']);
            $post->setStatus(1); // Actif
            $post->setCommentsEnabled(false);
            
            // Date de publication (articles récents)
            $publicationDate = new \DateTime();
            $publicationDate->modify('-' . ($index * 3) . ' days');
            $post->setPublicationDateStart($publicationDate);
            
            $post->setCreatedAt(new \DateTime());
            
            if ($user) {
                $post->setUser($user);
            }
            
            // Associer la catégorie
            $post->setCategory($createdCategories[$articleData['category']]);
            
            $this->entityManager->persist($post);
            $io->success("Article créé : {$articleData['title']}");
        }

        $this->entityManager->flush();

        $io->success('Données de test créées avec succès !');
        $io->note('Vous pouvez maintenant visiter /actualites pour voir les articles.');

        return Command::SUCCESS;
    }
}

