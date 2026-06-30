<?php

declare(strict_types=1);

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

/**
 * Seed du premier article SEO pillar : "Family office : définition complète 2026".
 *
 * Cible : positionnement sur "qu'est-ce qu'un family office", "famille office définition",
 * "combien coûte un family office", "tarifs family office".
 *
 * Usage : php bin/console app:seed-seo-pillar [--force]
 */
#[AsCommand(
    name: 'app:seed-seo-pillar',
    description: 'Insère le premier article SEO pillar "Family office : définition 2026"',
)]
final class SeedSeoArticleCommand extends Command
{
    private const POST_SLUG = 'family-office-definition-services-cout-2026';
    private const CATEGORY_NAME = 'Family Office';
    private const CATEGORY_SLUG = 'family-office';

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, null, 'Recrée l\'article même s\'il existe déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        // 1) Catégorie "Family Office" (créer si manque)
        $categoryRepo = $this->em->getRepository(Category::class);
        $category = $categoryRepo->findOneBy(['seoSlug' => self::CATEGORY_SLUG]);

        if (!$category) {
            $category = new Category();
            $category->setName(self::CATEGORY_NAME);
            $category->setDescription('Articles dédiés à la compréhension du métier de family office : définition, services, coût, choix et positionnement.');
            $category->setSeoTitle('Family Office : guides et conseils | ADN Family Office');
            $category->setSeoDescription('Articles experts sur le métier de family office : définition, services, tarifs, comment choisir. Conseils d\'ADN Family Office Paris.');
            $category->setSeoSlug(self::CATEGORY_SLUG);
            $this->em->persist($category);
            $this->em->flush();
            $io->success(sprintf('Catégorie créée : "%s"', $category->getName()));
        } else {
            $io->info(sprintf('Catégorie existante : "%s" (id=%d)', $category->getName(), $category->getId()));
        }

        // 2) Auteur : on cherche d'abord un super-admin, puis un admin, puis n'importe quel user
        $userRepo = $this->em->getRepository(User::class);
        $author = null;
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'] as $targetRole) {
            $author = $userRepo->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%'.$targetRole.'%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if ($author) {
                $io->info(sprintf('Auteur trouvé : %s (rôle %s)', $author->getUserIdentifier(), $targetRole));
                break;
            }
        }

        if (!$author) {
            // Fallback : premier user trouvé (pour ne pas bloquer le seed)
            $author = $userRepo->createQueryBuilder('u')->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($author) {
                $io->warning(sprintf('Aucun admin, fallback sur user : %s', $author->getUserIdentifier()));
            } else {
                $io->error('Aucun utilisateur en base.');
                return Command::FAILURE;
            }
        }

        // 3) Vérifier si l'article existe déjà
        $postRepo = $this->em->getRepository(Post::class);
        $existing = $postRepo->findOneBy(['seoSlug' => self::POST_SLUG]);

        if ($existing && !$force) {
            $io->warning(sprintf('Article déjà présent (id=%d). Utilise --force pour recréer.', $existing->getId()));
            return Command::SUCCESS;
        }

        if ($existing && $force) {
            $this->em->remove($existing);
            $this->em->flush();
            $io->info('Article existant supprimé (mode --force).');
        }

        // 4) Création de l'article pillar
        $post = new Post();
        $post->setTitle('Family office : définition, services et coût en 2026');
        $post->setSeoTitle('Family office : définition, services et coût en 2026');
        $post->setSeoDescription('Family office : définition complète, services proposés, tarifs et critères de choix en 2026. Guide expert ADN Family Office Paris.');
        $post->setContent($this->getArticleContent());
        $post->setCategory($category);
        $post->setUser($author);
        $post->setStatus('1'); // 1 = Active (setter typé string mais stocké en int)
        $post->setCommentsEnabled(false);
        $post->setPublicationDateStart(new \DateTime());
        $post->setCreatedAt(new \DateTime());
        $post->setIsFeatured(true);
        // Pas d'imageFile en seed - imageName est obligatoire en base donc on met un placeholder
        $post->setImageName('family-office-definition-2026.jpg');
        $post->setImageAlt('Family office : définition, services et coût en 2026 — ADN Family Office');

        $this->em->persist($post);
        $this->em->flush();

        $io->success(sprintf(
            'Article créé : "%s" (id=%d) — URL: /actualites/article/%s',
            $post->getTitle(),
            $post->getId(),
            $post->getSeoSlug()
        ));

        return Command::SUCCESS;
    }

    private function getArticleContent(): string
    {
        // Article pillar SEO - cible "qu'est-ce qu'un family office" (~1000 vol/mois)
        // Structure : 8 H2, ~2500 mots, maillage interne, CTA, JSON-LD friendly.
        return <<<HTML
<p class="lead">Le terme <strong>family office</strong> revient de plus en plus dans les conversations patrimoniales. Pourtant, peu de familles savent réellement ce qu'il recouvre, à qui il s'adresse et combien il coûte. Ce guide complet vous explique tout ce qu'il faut savoir sur les family offices en 2026 — et pourquoi ils ne sont plus réservés aux ultra-riches.</p>

<h2 id="definition">Qu'est-ce qu'un family office ? Définition simple</h2>

<p>Un <strong>family office</strong> est une structure dédiée à la gestion globale du patrimoine d'une famille fortunée. Contrairement à un conseiller en gestion de patrimoine classique ou à une banque privée, le family office centralise <em>l'ensemble</em> des problématiques patrimoniales d'une famille : investissements, fiscalité, transmission, immobilier, gouvernance familiale, philanthropie, et parfois même les sujets juridiques ou administratifs du quotidien.</p>

<p>L'objectif d'un family office est triple :</p>

<ul>
    <li><strong>Préserver</strong> le capital sur le long terme (plusieurs générations)</li>
    <li><strong>Faire fructifier</strong> ce capital avec un horizon d'investissement long</li>
    <li><strong>Transmettre</strong> le patrimoine dans les meilleures conditions fiscales et familiales</li>
</ul>

<p>Le family office se distingue par son <strong>indépendance</strong> : il ne perçoit pas de rétro-commissions sur les produits qu'il recommande, contrairement à de nombreux acteurs bancaires. Sa rémunération est fixe, transparente, et alignée sur les intérêts de la famille.</p>

<h2 id="types">Les 3 types de family office : single, multi et virtuel</h2>

<h3>Le single family office (SFO)</h3>

<p>Un <strong>single family office</strong> est une structure dédiée à une seule famille. Il s'agit typiquement d'une équipe de 5 à 30 personnes (financiers, juristes, fiscalistes, comptables) employée par la famille elle-même. C'est le modèle historique, né au XIXe siècle avec la famille Rockefeller.</p>

<p>Le SFO est <strong>réservé aux très grandes fortunes</strong> : on considère qu'il faut au minimum <strong>200 à 500 millions d'euros</strong> de patrimoine pour que l'économie soit viable, le coût de fonctionnement annuel d'un SFO étant de l'ordre de 2 à 5 millions d'euros.</p>

<h3>Le multi-family office (MFO)</h3>

<p>Le <strong>multi-family office</strong> mutualise l'expertise au service de plusieurs familles. Il offre la même profondeur de services qu'un SFO, mais à un coût accessible pour des familles à partir d'environ <strong>5 millions d'euros</strong> de patrimoine.</p>

<p>C'est aujourd'hui le modèle dominant en France et dans le monde, avec des acteurs comme <strong>Bordier &amp; Cie</strong>, <strong>Mirabaud</strong>, ou plus récemment des structures indépendantes comme <strong>ADN Family Office</strong>.</p>

<h3>Le family office virtuel ou hybride</h3>

<p>Apparu dans les années 2010, le <strong>family office virtuel</strong> coordonne un réseau d'experts externes (avocats, fiscalistes, gestionnaires) plutôt que de tout internaliser. Cette approche, plus agile, permet de descendre encore le seuil d'accès — typiquement à partir de <strong>1 à 3 millions d'euros</strong> de patrimoine.</p>

<p>C'est la philosophie d'<strong>ADN Family Office</strong>, qui a fait de l'<em>accessibilité</em> son ADN : <strong>A</strong>ccessible <strong>D</strong>igital <strong>N</strong>ovateur.</p>

<h2 id="services">Quels services propose un family office ?</h2>

<p>Un family office complet couvre tous les domaines patrimoniaux. Voici les principaux services proposés :</p>

<h3>1. Gestion de patrimoine et investissements</h3>

<ul>
    <li>Allocation d'actifs stratégique sur 10-30 ans</li>
    <li>Sélection de produits financiers (actions, obligations, OPCVM)</li>
    <li>Accès à du <a href="/services/private-equity">private equity</a> et fonds réservés aux investisseurs qualifiés</li>
    <li>Suivi de performance consolidé sur l'ensemble du patrimoine</li>
</ul>

<h3>2. Investissement immobilier</h3>

<ul>
    <li>Conseil en <a href="/services/gestion-immobiliere">acquisitions immobilières</a> (résidentiel, commercial, SCPI, OPCI)</li>
    <li>Optimisation de la détention (SCI, démembrement, holding)</li>
    <li>Défiscalisation immobilière (Pinel, Malraux, Monument Historique)</li>
</ul>

<h3>3. Transmission et succession</h3>

<ul>
    <li><a href="/services/transmission-succession">Planification successorale</a> sur mesure</li>
    <li>Donations-partages, démembrement temporaire</li>
    <li>Pactes Dutreil pour la transmission d'entreprise</li>
    <li>Création et gestion de holding familiale</li>
</ul>

<h3>4. Fiscalité et optimisation</h3>

<ul>
    <li>Optimisation de l'impôt sur le revenu et de l'IFI</li>
    <li>Gestion de la résidence fiscale (expatriation, retour en France)</li>
    <li>Veille réglementaire et adaptation aux évolutions législatives</li>
</ul>

<h3>5. Conseil au dirigeant d'entreprise</h3>

<ul>
    <li><a href="/services/conseil-strategique-du-dirigeant">Optimisation de la rémunération du dirigeant</a></li>
    <li>Préparation de la cession d'entreprise</li>
    <li>Pacte Dutreil et apport-cession</li>
</ul>

<h3>6. Services additionnels (selon les structures)</h3>

<ul>
    <li>Philanthropie et création de fondations</li>
    <li>Gouvernance familiale (charte familiale, conseil de famille)</li>
    <li>Concierge patrimonial (administration des comptes courants, paiement des factures, gestion des contrats)</li>
</ul>

<h2 id="cout">Combien coûte un family office en France en 2026 ?</h2>

<p>Le coût d'un family office dépend de trois facteurs : la <strong>structure</strong> (SFO, MFO, virtuel), le <strong>volume du patrimoine sous gestion</strong>, et l'<strong>étendue des services</strong> demandés.</p>

<h3>Grille tarifaire indicative (2026)</h3>

<table>
    <thead>
        <tr><th>Type</th><th>Patrimoine min.</th><th>Coût annuel typique</th><th>% du patrimoine</th></tr>
    </thead>
    <tbody>
        <tr><td>Single Family Office</td><td>200 M€ +</td><td>2 - 5 M€</td><td>0,5 - 1,5%</td></tr>
        <tr><td>Multi-family office premium</td><td>10 M€ +</td><td>50 - 200 k€</td><td>0,5 - 1%</td></tr>
        <tr><td>Multi-family office standard</td><td>5 M€ +</td><td>20 - 60 k€</td><td>0,4 - 0,8%</td></tr>
        <tr><td>Family office virtuel / hybride</td><td>1 M€ +</td><td>5 - 25 k€</td><td>0,3 - 0,6%</td></tr>
    </tbody>
</table>

<h3>Modes de rémunération</h3>

<ul>
    <li><strong>Forfait annuel fixe</strong> : la formule la plus transparente, indépendante des marchés.</li>
    <li><strong>Pourcentage sous gestion (AUM)</strong> : courant chez les MFO, souvent dégressif par tranche.</li>
    <li><strong>Honoraires à la prestation</strong> : pour des missions ponctuelles (transmission, cession).</li>
    <li><strong>Performance fee</strong> : commission sur la surperformance d'un benchmark (rare en France).</li>
</ul>

<p>⚠️ <strong>Méfiez-vous des family offices "gratuits"</strong> : s'ils ne facturent pas d'honoraires, c'est qu'ils se rémunèrent sur les rétro-commissions des produits placés, ce qui crée un conflit d'intérêts manifeste.</p>

<h2 id="quand">Quand faire appel à un family office ?</h2>

<p>Voici les <strong>10 signaux</strong> qui indiquent qu'il est temps d'envisager un family office :</p>

<ol>
    <li>Votre patrimoine dépasse <strong>1 million d'euros</strong> hors résidence principale</li>
    <li>Vous êtes <strong>dirigeant d'entreprise</strong> et envisagez une cession dans les 5 ans</li>
    <li>Vous percevez ou allez percevoir un <strong>héritage important</strong></li>
    <li>Vous payez de l'<strong>IFI</strong> et cherchez à l'optimiser</li>
    <li>Votre <strong>famille s'agrandit</strong> et vous voulez anticiper la transmission</li>
    <li>Vous êtes <strong>expatrié</strong> ou vous envisagez l'expatriation</li>
    <li>Vous avez besoin d'<strong>investir hors des marchés cotés</strong> (immobilier commercial, private equity)</li>
    <li>Vous gérez actuellement <strong>seul</strong> votre patrimoine et y consacrez trop de temps</li>
    <li>Vous avez plusieurs <strong>conseillers indépendants</strong> sans vision globale</li>
    <li>Vous avez identifié une <strong>opportunité d'investissement complexe</strong> qui dépasse votre expertise</li>
</ol>

<p>Si vous cochez 3 critères ou plus, prenez rendez-vous pour une <a href="/contact">consultation gratuite avec un family officer</a>.</p>

<h2 id="comparaison">Family office vs gestionnaire de patrimoine vs banque privée</h2>

<p>Le marché du conseil patrimonial est confus en France. Voici les différences clés entre les 3 grands acteurs :</p>

<table>
    <thead>
        <tr><th>Critère</th><th>Banque privée</th><th>CGP indépendant</th><th>Family office</th></tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Accessibilité</strong></td>
            <td>250 k€ - 5 M€</td>
            <td>100 k€ +</td>
            <td>1 M€ + (virtuel) / 5 M€ + (MFO)</td>
        </tr>
        <tr>
            <td><strong>Indépendance</strong></td>
            <td>Limitée (architecte ouverte sous contrainte)</td>
            <td>Variable (statut MIP/CIF)</td>
            <td>Totale (pas de rétro-commissions)</td>
        </tr>
        <tr>
            <td><strong>Étendue des services</strong></td>
            <td>Gestion financière surtout</td>
            <td>Patrimoine global mais souvent superficiel</td>
            <td>Patrimoine global + transmission + fiscalité + entreprise</td>
        </tr>
        <tr>
            <td><strong>Rémunération</strong></td>
            <td>Rétro-commissions + frais de gestion</td>
            <td>Mixte (honoraires + rétro)</td>
            <td>Honoraires uniquement (transparent)</td>
        </tr>
        <tr>
            <td><strong>Horizon</strong></td>
            <td>Court / moyen terme</td>
            <td>Moyen terme</td>
            <td>Long terme (multi-générations)</td>
        </tr>
    </tbody>
</table>

<p>En résumé : <strong>la banque privée vous vend des produits</strong>, le <strong>CGP indépendant</strong> vous conseille sur des sujets ponctuels, et le <strong>family office</strong> orchestre l'ensemble de votre vie patrimoniale dans la durée.</p>

<h2 id="choisir">Comment choisir son family office ? 7 critères essentiels</h2>

<p>Tous les family offices ne se valent pas. Voici les 7 critères à passer en revue avant de vous engager :</p>

<h3>1. L'indépendance réelle</h3>
<p>Vérifiez la structure capitalistique. Un family office détenu par une banque ne sera jamais totalement indépendant. Privilégiez les structures dont le capital est détenu par les associés-fondateurs ou les clients.</p>

<h3>2. La transparence des honoraires</h3>
<p>Demandez une grille tarifaire écrite, et exigez l'engagement écrit de <strong>ne percevoir aucune rétrocession</strong> sur les produits recommandés. Si on hésite à vous le donner, fuyez.</p>

<h3>3. La profondeur de l'expertise</h3>
<p>Un bon family office doit avoir en interne (ou en réseau partenaire) : un fiscaliste, un avocat patrimonial, un notaire, un gestionnaire financier, un spécialiste immobilier. Demandez les CV de l'équipe.</p>

<h3>4. L'agréments et certifications</h3>
<p>Le family office doit être inscrit à l'<strong>ORIAS</strong> (statuts MIP / CIF / CPI), et idéalement agréé <strong>AMF</strong> pour les activités financières. Une certification <strong>ISO 27001</strong> (sécurité des données) est un plus pour la confidentialité.</p>

<h3>5. La qualité du reporting</h3>
<p>Demandez à voir un exemple de reporting trimestriel anonymisé. Il doit être <strong>consolidé sur tout votre patrimoine</strong> (pas juste les actifs gérés), avec une mesure de performance nette de frais.</p>

<h3>6. La capacité d'écoute</h3>
<p>Un family office, c'est une relation de long terme. La compatibilité humaine est aussi importante que la compétence technique. Le family officer doit comprendre votre histoire familiale, vos valeurs, vos projets.</p>

<h3>7. Les références clients</h3>
<p>N'hésitez pas à demander des références de clients aux profils similaires au vôtre. Un family office sérieux acceptera de vous mettre en contact (avec l'accord de ses clients référents).</p>

<h2 id="adn">ADN Family Office : le family office accessible à toutes les familles</h2>

<p>Chez <strong>ADN Family Office</strong>, nous sommes convaincus que l'excellence patrimoniale ne doit pas être réservée aux ultra-riches. Notre conviction : toute famille qui a su construire un patrimoine mérite d'être accompagnée avec la même rigueur que les grandes fortunes.</p>

<h3>Notre positionnement</h3>

<ul>
    <li><strong>Family office indépendant</strong> à Paris depuis plus de 30 ans</li>
    <li><strong>205 familles accompagnées</strong>, 70 M€ d'actifs gérés</li>
    <li><strong>Accessible</strong> dès 1 M€ de patrimoine (vs. 5-10 M€ chez nos concurrents)</li>
    <li><strong>Honoraires transparents</strong> et zéro rétro-commission</li>
    <li><strong>Certifications</strong> ORIAS, agrément AMF, ISO 27001</li>
</ul>

<h3>Nos 6 pôles d'expertise</h3>

<p>Nous couvrons l'ensemble de vos problématiques patrimoniales avec une équipe pluridisciplinaire :</p>

<ul>
    <li><a href="/services/gestion-de-patrimoine">Gestion de patrimoine</a> : allocation, optimisation fiscale, stratégies d'investissement</li>
    <li><a href="/services/gestion-immobiliere">Gestion immobilière</a> : résidentiel, commercial, SCPI, défiscalisation</li>
    <li><a href="/services/transmission-succession">Transmission et succession</a> : donations, holding familiale, pactes Dutreil</li>
    <li><a href="/services/private-equity">Private equity</a> : accès aux fonds réservés, startups, capital-développement</li>
    <li><a href="/services/investissements-financiers">Investissements financiers</a> : portefeuilles sur mesure, assurance-vie</li>
    <li><a href="/services/conseil-strategique-du-dirigeant">Conseil au dirigeant</a> : rémunération, cession, optimisation</li>
</ul>

<h3>Notre méthodologie</h3>

<p>Nous appliquons une méthodologie en 4 étapes :</p>

<ol>
    <li><strong>Audit patrimonial 360°</strong> (gratuit lors de la première consultation)</li>
    <li><strong>Diagnostic et préconisations</strong> écrites, chiffrées, datées</li>
    <li><strong>Mise en œuvre</strong> avec notre réseau de partenaires sélectionnés (notaires, avocats, gestionnaires)</li>
    <li><strong>Suivi annuel</strong> avec reporting consolidé et points d'étape</li>
</ol>

<h3>Prêt à passer à l'étape suivante ?</h3>

<p>Pour découvrir comment ADN Family Office peut vous accompagner, prenez rendez-vous pour une <strong><a href="/contact">consultation gratuite</a></strong> dans nos bureaux du 140 bis rue de Rennes à Paris 6e — ou en visioconférence si vous êtes éloigné.</p>

<p>Vous pouvez également consulter <a href="/expertise">notre expertise</a> et <a href="/services">l'ensemble de nos services</a> pour vous faire une idée plus précise de notre approche.</p>

<hr>

<h2 id="faq">Foire aux questions sur les family offices</h2>

<h3>Un family office, est-ce uniquement pour les très riches ?</h3>
<p>Plus en 2026. Les family offices virtuels et hybrides (comme ADN Family Office) rendent l'expertise accessible dès 1 million d'euros de patrimoine.</p>

<h3>Combien de temps prend la mise en place ?</h3>
<p>Comptez 2 à 3 mois entre la première consultation et la mise en œuvre des premières recommandations.</p>

<h3>Peut-on quitter un family office facilement ?</h3>
<p>Oui, contrairement aux banques privées avec leurs frais de sortie. Un family office indépendant ne vous retient pas : la portabilité est totale.</p>

<h3>Le family office gère-t-il directement mon argent ?</h3>
<p>Non, dans la plupart des cas (sauf SFO). Vos comptes restent dans vos banques. Le family office vous conseille mais vous gardez le pouvoir de signature.</p>

<h3>Les conseils d'un family office sont-ils confidentiels ?</h3>
<p>Oui, c'est même un fondement du métier. Chez ADN Family Office, notre certification ISO 27001 garantit le plus haut niveau de sécurité des données.</p>

HTML;
    }
}
