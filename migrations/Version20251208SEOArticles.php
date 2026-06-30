<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration SEO : Import des articles WordPress pour préserver le référencement
 * 
 * Ces articles correspondent aux URLs existantes sur adnfamilyoffice.fr
 * Les slugs doivent correspondre exactement pour que les redirections 301 fonctionnent.
 */
final class Version20251208SEOArticles extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Import des articles WordPress existants pour préserver le SEO lors de la migration';
    }

    public function up(Schema $schema): void
    {
        // Article 1 : Allier diversification et avantage fiscal
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 13, 'default-article.jpg', 'Diversification et avantage fiscal',
                'Allier diversification et avantage fiscal',
                '<p><strong>➣ Un actif tangible au cœur du patrimoine</strong></p><p>Dans un environnement économique marqué par la volatilité des marchés et la remontée durable de l''inflation, de nombreux épargnants recherchent des placements ancrés dans le réel. L''investissement forestier, à travers le GFI France Valley Forêts, répond à cette quête de stabilité et de sens.</p><p><strong>➣ La force d''un actif réel, peu corrélé aux marchés financiers</strong></p><p>La forêt est par essence un actif tangible, dont la valorisation dépend avant tout de la qualité du sol, des essences et du marché du bois.</p><p><strong>➣ Une fiscalité particulièrement avantageuse</strong></p><ul><li>18% de réduction d''impôt sur le revenu</li><li>Exonération d''IFI, sous condition minoritaire</li><li>Abattement de 75% sur les droits de donation ou de succession</li></ul>',
                1, '2025-10-29 10:00:00', 1, NOW(), NOW(),
                'Allier diversification et avantage fiscal - ADN Family Office',
                'Découvrez comment l''investissement forestier permet d''allier diversification patrimoniale et avantages fiscaux.',
                'allier-diversification-et-avantage-fiscal', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");

        // Article 2 : L'épargne de précaution
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 13, 'default-article.jpg', 'Épargne de précaution',
                'L''épargne de précaution : le socle d''une stratégie patrimoniale équilibrée',
                '<p>L''épargne de précaution constitue le fondement de toute stratégie patrimoniale solide. Elle représente un matelas de sécurité financière indispensable pour faire face aux imprévus de la vie.</p><p><strong>Pourquoi constituer une épargne de précaution ?</strong></p><ul><li>Se protéger contre les aléas de la vie</li><li>Éviter de puiser dans ses investissements long terme</li><li>Conserver une sérénité financière au quotidien</li></ul><p>Chez ADN Family Office, nous recommandons de constituer une épargne de précaution équivalente à 3 à 6 mois de dépenses courantes avant d''envisager des placements plus ambitieux.</p>',
                1, '2025-10-22 10:00:00', 1, NOW(), NOW(),
                'L''épargne de précaution : stratégie patrimoniale - ADN Family Office',
                'Découvrez pourquoi l''épargne de précaution est le socle indispensable d''une stratégie patrimoniale équilibrée.',
                'lepargne-de-precaution-le-socle-dune-strategie-patrimoniale-equilibree', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");

        // Article 3 : Pierre-papier
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 14, 'default-article.jpg', 'Pierre-papier investissement',
                'Pourquoi le contexte actuel donne tout son intérêt à la « pierre-papier » ?',
                '<p>Dans le contexte économique actuel, la \"pierre-papier\" (SCPI, OPCI, SCI) présente des atouts particulièrement intéressants pour les investisseurs.</p><p><strong>Les avantages de la pierre-papier :</strong></p><ul><li>Accessibilité : investir dans l''immobilier dès quelques centaines d''euros</li><li>Diversification : accès à un portefeuille immobilier diversifié</li><li>Gestion déléguée : aucune contrainte de gestion locative</li><li>Rendements attractifs : des rendements souvent supérieurs aux placements traditionnels</li></ul><p>ADN Family Office vous accompagne dans la sélection des meilleures SCPI adaptées à votre profil.</p>',
                1, '2025-10-01 10:00:00', 1, NOW(), NOW(),
                'Pourquoi investir dans la pierre-papier ? - ADN Family Office',
                'Découvrez les avantages de l''investissement en pierre-papier (SCPI, OPCI) dans le contexte économique actuel.',
                'pourquoi-le-contexte-actuel-donne-tout-son-interet-a-la-pierre-papier', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");

        // Article 4 : Intelligence artificielle
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 14, 'default-article.jpg', 'Intelligence artificielle investissement',
                'Comment tirer profit de l''intelligence artificielle ?',
                '<p>L''intelligence artificielle révolutionne de nombreux secteurs et offre des opportunités d''investissement significatives.</p><p><strong>Secteurs impactés par l''IA :</strong></p><ul><li>Technologies et semiconducteurs</li><li>Santé et biotechnologies</li><li>Finance et fintech</li><li>Industrie et automatisation</li></ul><p><strong>Comment investir dans l''IA ?</strong></p><p>Plusieurs véhicules d''investissement permettent de s''exposer à cette thématique : ETF thématiques, fonds sectoriels, ou actions de sociétés leaders.</p><p>ADN Family Office analyse pour vous les meilleures opportunités dans ce secteur en pleine expansion.</p>',
                1, '2025-02-19 10:00:00', 1, NOW(), NOW(),
                'Comment investir dans l''intelligence artificielle ? - ADN Family Office',
                'Découvrez comment tirer profit de la révolution de l''intelligence artificielle dans vos investissements.',
                'comment-tirer-profit-de-lintelligence-artificielle', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");

        // Article 5 : Assurance-vie
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 13, 'default-article.jpg', 'Assurance-vie changement contrat',
                'Assurance-Vie: Quand et Pourquoi Changer de Contrat ?',
                '<p>L''assurance-vie est le placement préféré des Français. Mais tous les contrats ne se valent pas. Quand faut-il envisager d''en changer ?</p><p><strong>Signaux d''alerte pour changer de contrat :</strong></p><ul><li>Frais de gestion trop élevés (> 1%)</li><li>Rendement du fonds euros insuffisant</li><li>Choix d''unités de compte limité</li><li>Options de gestion absentes</li></ul><p><strong>Avantages d''un transfert :</strong></p><p>Depuis la loi Pacte, il est possible de transférer son contrat vers un autre assureur tout en conservant l''antériorité fiscale.</p><p>ADN Family Office vous aide à optimiser votre assurance-vie.</p>',
                1, '2025-01-30 10:00:00', 1, NOW(), NOW(),
                'Assurance-Vie : Quand changer de contrat ? - ADN Family Office',
                'Découvrez quand et pourquoi il peut être pertinent de changer de contrat d''assurance-vie pour optimiser votre épargne.',
                'assurance-vie-quand-et-pourquoi-changer-de-contrat', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");

        // Article 6 : Élections américaines
        $this->addSql("
            INSERT INTO blog_post (
                user_id, category_id, image_name, image_alt, title, content, 
                comments_enabled, publication_date_start, status, created_at, updated_at,
                seo_title, seo_description, seo_slug, disable_in_sitemap
            ) VALUES (
                6, 14, 'default-article.jpg', 'Élections américaines marchés',
                'Impact des Élections Américaines sur les Marchés Financiers',
                '<p>Les élections américaines ont toujours un impact significatif sur les marchés financiers mondiaux. Quelles sont les implications pour vos investissements ?</p><p><strong>Historiquement :</strong></p><ul><li>Les marchés sont volatils avant les élections</li><li>Une fois le résultat connu, la volatilité diminue</li><li>Les secteurs impactés varient selon le vainqueur</li></ul><p><strong>Conseils pour les investisseurs :</strong></p><ul><li>Ne pas prendre de décisions hâtives basées sur les sondages</li><li>Maintenir une allocation diversifiée</li><li>Profiter des corrections pour renforcer ses positions</li></ul><p>ADN Family Office vous accompagne pour naviguer ces périodes d''incertitude.</p>',
                1, '2024-10-28 10:00:00', 1, NOW(), NOW(),
                'Impact des élections américaines sur les marchés - ADN Family Office',
                'Analyse de l''impact des élections américaines sur les marchés financiers et conseils pour vos investissements.',
                'impact-des-elections-americaines-sur-les-marches-financiers', 0
            ) ON DUPLICATE KEY UPDATE 
                image_name = VALUES(image_name),
                image_alt = VALUES(image_alt),
                content = VALUES(content),
                seo_title = VALUES(seo_title),
                seo_description = VALUES(seo_description),
                updated_at = VALUES(updated_at),
                disable_in_sitemap = VALUES(disable_in_sitemap)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'allier-diversification-et-avantage-fiscal'");
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'lepargne-de-precaution-le-socle-dune-strategie-patrimoniale-equilibree'");
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'pourquoi-le-contexte-actuel-donne-tout-son-interet-a-la-pierre-papier'");
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'comment-tirer-profit-de-lintelligence-artificielle'");
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'assurance-vie-quand-et-pourquoi-changer-de-contrat'");
        $this->addSql("DELETE FROM blog_post WHERE seo_slug = 'impact-des-elections-americaines-sur-les-marches-financiers'");
    }
}
