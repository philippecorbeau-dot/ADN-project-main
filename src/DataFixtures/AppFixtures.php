<?php

namespace App\DataFixtures;

use App\Entity\Blog\Category;
use App\Entity\Blog\Post;
use App\Entity\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use App\Entity\InvestmentComparison;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Catégories minimales utilisées par le menu
        $categories = [];
        $baseCategories = [
            ['Fiscalité', 'fiscalite'],
            ['Marchés financiers', 'marches-financiers'],
            ['Évolutions réglementaires', 'evolutions-reglementaires'],
            ['Analyses de marché', 'analyses-de-marche'],
            ['Tendances sectorielles', 'tendances-sectorielles'],
            ['Conseils d\'experts', 'conseils-d-experts'],
        ];

        foreach ($baseCategories as [$name, $slug]) {
            $existing = $manager->getRepository(Category::class)->findOneBy(['seoSlug' => $slug]);
            if ($existing) {
                $categories[] = $existing;
                continue;
            }
            $category = new Category();
            if (method_exists($category, 'setName')) { $category->setName($name); }
            if (method_exists($category, 'setSeoSlug')) { $category->setSeoSlug($slug); }
            if (method_exists($category, 'setSeoTitle')) { $category->setSeoTitle($name.' - ADN Family Office'); }
            if (method_exists($category, 'setSeoDescription')) { $category->setSeoDescription($faker->sentence(12)); }
            $manager->persist($category);
            $categories[] = $category;
        }

        // Un auteur générique si nécessaire
        $author = null;
        if (class_exists(User::class)) {
            $author = new User();
            if (method_exists($author, 'setEmail')) { $author->setEmail('auteur@example.com'); }
            if (method_exists($author, 'setFirstName')) { $author->setFirstName('Auteur'); }
            if (method_exists($author, 'setLastName')) { $author->setLastName('ADN'); }
            if (method_exists($author, 'setIsVerified')) { $author->setIsVerified(true); }
            if (method_exists($author, 'setPassword')) { $author->setPassword(password_hash('TestPassword123!', PASSWORD_BCRYPT)); }
            if (method_exists($author, 'setCreatedAt')) { $author->setCreatedAt(new \DateTimeImmutable()); }
            $manager->persist($author);
        }

        // Articles factices
        for ($i = 0; $i < 24; $i++) {
            $post = new Post();
            $title = ucfirst($faker->words(mt_rand(4, 8), true));

            if (method_exists($post, 'setTitle')) { $post->setTitle($title); }
            if (method_exists($post, 'setSeoTitle')) { $post->setSeoTitle($title.' | ADN'); }
            if (method_exists($post, 'setSeoSlug')) { $post->setSeoSlug($faker->slug()); }
            if (method_exists($post, 'setSeoDescription')) { $post->setSeoDescription($faker->sentence(20)); }
            if (method_exists($post, 'setStatus')) { $post->setStatus(1); }
            if (method_exists($post, 'setPublicationDateStart')) { $post->setPublicationDateStart($faker->dateTimeBetween('-90 days', 'now')); }
            if (method_exists($post, 'setCreatedAt')) { $post->setCreatedAt(new \DateTime()); }
            if ($author && method_exists($post, 'setUser')) { $post->setUser($author); }
            if (method_exists($post, 'setCategory')) { $post->setCategory($faker->randomElement($categories)); }
            if (method_exists($post, 'setContent')) { $post->setContent($faker->paragraphs(mt_rand(3, 7), true)); }
            if (method_exists($post, 'setCommentsEnabled')) { $post->setCommentsEnabled(true); }
            if (method_exists($post, 'setImageName')) { $post->setImageName('founder1.jpg'); }
            if (method_exists($post, 'setImageAlt')) { $post->setImageAlt('Illustration'); }
            
            $manager->persist($post);
        }

        $manager->flush();

        // Seed comparison table only if empty
        if (!$this->seededComparison($manager)) {
            $rows = [
                ['SCPI','yield','6-8%',1],
                ['PEA_PME','yield','Variable',2],
                ['ASSURANCE_VIE','yield','2-6%',3],
                ['PER','yield','Variable',4],

                ['SCPI','risk','Modéré',5],
                ['PEA_PME','risk','Élevé',6],
                ['ASSURANCE_VIE','risk','Faible à Modéré',7],
                ['PER','risk','Faible à Élevé',8],

                ['SCPI','liquidity','Limitée',9],
                ['PEA_PME','liquidity','Bonne',10],
                ['ASSURANCE_VIE','liquidity','Totale',11],
                ['PER','liquidity','Bloquée',12],

                ['SCPI','taxation','Revenus fonciers',13],
                ['PEA_PME','taxation','Exonération après 5 ans',14],
                ['ASSURANCE_VIE','taxation','Abattement après 8 ans',15],
                ['PER','taxation','Déduction à l\'entrée',16],

                ['SCPI','min_investment','1 000 €',17],
                ['PEA_PME','min_investment','100 €',18],
                ['ASSURANCE_VIE','min_investment','500 €',19],
                ['PER','min_investment','300 €',20],
            ];

            foreach ($rows as [$product,$criterion,$value,$pos]) {
                $ic = new InvestmentComparison();
                $ic->setProduct($product)
                    ->setCriterion($criterion)
                    ->setValue($value)
                    ->setPosition($pos);
                $manager->persist($ic);
            }
            $manager->flush();
        }
    }

    private function seededComparison(ObjectManager $manager): bool
    {
        return (bool) $manager->getRepository(InvestmentComparison::class)->count([]);
    }
}
