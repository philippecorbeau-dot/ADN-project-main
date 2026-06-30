<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250415125721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description VARCHAR(255) DEFAULT NULL, seo_slug VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_72113DE65E237E06 (name), UNIQUE INDEX UNIQ_72113DE6644B8BB3 (seo_slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_post (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, category_id INT DEFAULT NULL, image_name VARCHAR(255) NOT NULL, image_alt VARCHAR(255) DEFAULT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, comments_enabled TINYINT(1) NOT NULL, publication_date_start DATETIME NOT NULL, status INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, seo_title VARCHAR(255) NOT NULL, seo_description VARCHAR(255) NOT NULL, seo_slug VARCHAR(255) NOT NULL, canonical_url VARCHAR(255) DEFAULT NULL, redirect_url VARCHAR(255) DEFAULT NULL, related_posts JSON DEFAULT NULL, disable_in_sitemap TINYINT(1) DEFAULT 0 NOT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_BA5AE01D2B36786B (title), UNIQUE INDEX UNIQ_BA5AE01D644B8BB3 (seo_slug), INDEX IDX_BA5AE01DA76ED395 (user_id), INDEX IDX_BA5AE01D12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cocoon_post (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, status INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, seo_title VARCHAR(255) NOT NULL, seo_description VARCHAR(255) NOT NULL, seo_slug VARCHAR(255) NOT NULL, category_id VARCHAR(155) NOT NULL, image_name VARCHAR(255) DEFAULT NULL, landing_to_override VARCHAR(255) DEFAULT NULL, image_alt VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_60F5A8F32B36786B (title), UNIQUE INDEX UNIQ_60F5A8F3644B8BB3 (seo_slug), UNIQUE INDEX UNIQ_60F5A8F3ECDDEBE4 (landing_to_override), INDEX IDX_60F5A8F3727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE comment (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, username VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, model VARCHAR(255) NOT NULL, model_id INT NOT NULL, parent_id INT NOT NULL, INDEX IDX_9474526CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, lastname VARCHAR(255) NOT NULL, firstname VARCHAR(255) NOT NULL, subject LONGTEXT NOT NULL COMMENT '(DC2Type:array)', email VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, rdv_date DATETIME DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, `function` VARCHAR(255) DEFAULT NULL, activityLocation VARCHAR(255) DEFAULT NULL, locationBase VARCHAR(255) DEFAULT NULL, job VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE mail (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, sended_to VARCHAR(255) NOT NULL, receiver_ip VARCHAR(20) NOT NULL, createdAt DATETIME NOT NULL, templateHtml LONGTEXT NOT NULL, templateTxt LONGTEXT NOT NULL, token VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_5126AC485F37A13B (token), INDEX IDX_5126AC48A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE rating (id INT AUTO_INCREMENT NOT NULL, blog_post_id INT DEFAULT NULL, cocoon_post_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', score INT NOT NULL, ip VARCHAR(255) DEFAULT NULL, INDEX IDX_D8892622A77FBEAF (blog_post_id), INDEX IDX_D88926227ACA38AB (cocoon_post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE spam (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, email VARCHAR(255) DEFAULT NULL, ip VARCHAR(255) DEFAULT NULL, blocked TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_additional_fields (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, legal_capacity VARCHAR(255) DEFAULT NULL, professional_social_category VARCHAR(32) DEFAULT NULL, matrimonial_regime VARCHAR(2) DEFAULT NULL, annual_income VARCHAR(2) DEFAULT NULL, patrimony_amount VARCHAR(2) DEFAULT NULL, professional_status VARCHAR(2) DEFAULT NULL, UNIQUE INDEX UNIQ_D758AAE2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_cgp (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, companyName VARCHAR(255) NOT NULL, siren VARCHAR(255) NOT NULL, orias VARCHAR(511) NOT NULL, `function` VARCHAR(511) NOT NULL, legal_representative VARCHAR(511) NOT NULL, socialObject TINYTEXT NOT NULL, turnover NUMERIC(10, 0) NOT NULL, oldResult NUMERIC(10, 0) NOT NULL, forecastTurnover NUMERIC(10, 0) NOT NULL, capital NUMERIC(10, 0) NOT NULL, stocks NUMERIC(10, 0) NOT NULL, address_line1 VARCHAR(255) DEFAULT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(155) DEFAULT NULL, region VARCHAR(155) DEFAULT NULL, postal_code VARCHAR(10) NOT NULL, country VARCHAR(2) NOT NULL, UNIQUE INDEX UNIQ_247F41BCA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_config (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, crowdfunding TINYINT(1) NOT NULL, vefa TINYINT(1) NOT NULL, scpi TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_B1D83441A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_control (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, create_user_id INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, type LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)', email VARCHAR(128) DEFAULT NULL, last_name VARCHAR(128) DEFAULT NULL, first_name VARCHAR(128) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, origin VARCHAR(511) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_B5D63241A76ED395 (user_id), INDEX IDX_B5D6324185564492 (create_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_document (id INT AUTO_INCREMENT NOT NULL, identity_card_filename VARCHAR(255) DEFAULT NULL, proof_address_filename VARCHAR(255) DEFAULT NULL, articles_of_association_filename VARCHAR(255) DEFAULT NULL, registration_proof_filename VARCHAR(255) DEFAULT NULL, share_holder_declaration_filename VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, confirmation_token VARCHAR(255) NOT NULL, is_pro TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_info (id INT AUTO_INCREMENT NOT NULL, owner LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)', earnings LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)', earning_amount INT DEFAULT NULL, thrift_amount INT DEFAULT NULL, patrimony_amount INT DEFAULT NULL, isf TINYINT(1) DEFAULT NULL, patrimony_percent TINYINT(1) DEFAULT NULL, already_invest TINYINT(1) DEFAULT NULL, invest_type LONGTEXT DEFAULT NULL COMMENT '(DC2Type:simple_array)', investor_qualified TINYINT(1) DEFAULT NULL, source_of_founds LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)', futur_invest TINYINT(1) DEFAULT NULL, securities TINYINT(1) DEFAULT NULL, securities_options LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)', securities_options_count VARCHAR(255) DEFAULT NULL, finance_worker TINYINT(1) DEFAULT NULL, political TINYINT(1) DEFAULT NULL, investment_availability VARCHAR(255) DEFAULT NULL, company_owner TINYINT(1) DEFAULT NULL, mif TINYINT(1) DEFAULT NULL, attest_mif TINYINT(1) DEFAULT NULL, awareness_minimum_amount TINYINT(1) DEFAULT NULL, awareness_minimum_time TINYINT(1) DEFAULT NULL, awareness_minimum_transactions TINYINT(1) DEFAULT NULL, attest_income TINYINT(1) DEFAULT NULL, attest_significant_transaction TINYINT(1) DEFAULT NULL, attest_aware TINYINT(1) DEFAULT NULL, attest_truth TINYINT(1) DEFAULT NULL, adequacy1 VARCHAR(255) DEFAULT NULL, adequacy2 VARCHAR(255) DEFAULT NULL, adequacy3 TINYINT(1) DEFAULT NULL, adequacy4 TINYINT(1) DEFAULT NULL, adequacy5 VARCHAR(255) DEFAULT NULL, accompaniment TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_kyc_document (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, type VARCHAR(155) NOT NULL, status VARCHAR(55) DEFAULT NULL, refused_reason_message VARCHAR(255) DEFAULT NULL, INDEX IDX_DC67AD6A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_kyc_document_file (id INT AUTO_INCREMENT NOT NULL, document_id INT DEFAULT NULL, createdAt DATETIME NOT NULL, updatedAt DATETIME NOT NULL, name VARCHAR(255) NOT NULL, is_image TINYINT(1) NOT NULL, INDEX IDX_F30118B5C33F7837 (document_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_mailing (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, newsletter SMALLINT DEFAULT 1 NOT NULL, events SMALLINT DEFAULT 1 NOT NULL, projects SMALLINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_66D42F54A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_marketing (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, utm_source VARCHAR(55) NOT NULL, utm_medium VARCHAR(55) NOT NULL, utm_campaign VARCHAR(55) DEFAULT NULL, utm_content VARCHAR(55) DEFAULT NULL, UNIQUE INDEX UNIQ_9461EC8CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_pro (id INT AUTO_INCREMENT NOT NULL, companyName VARCHAR(255) NOT NULL, siren VARCHAR(255) NOT NULL, head_office VARCHAR(255) DEFAULT NULL, socialObject LONGTEXT NOT NULL, legalRepresentative VARCHAR(255) DEFAULT NULL, legal_representative_firstname VARCHAR(255) NOT NULL, legal_representative_lastname VARCHAR(255) NOT NULL, shareholders VARCHAR(255) DEFAULT NULL, turnover NUMERIC(10, 0) DEFAULT NULL, oldResult NUMERIC(10, 0) DEFAULT NULL, yearResult DATE DEFAULT NULL, forecastTurnover NUMERIC(10, 0) DEFAULT NULL, year_forecast_turnover DATE DEFAULT NULL, capital NUMERIC(10, 0) DEFAULT NULL, stocks NUMERIC(10, 0) DEFAULT NULL, address_line1 VARCHAR(255) DEFAULT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(155) DEFAULT NULL, region VARCHAR(155) DEFAULT NULL, postal_code VARCHAR(10) NOT NULL, country VARCHAR(2) NOT NULL, social_form VARCHAR(255) DEFAULT NULL, awareness_balance_sheet TINYINT(1) DEFAULT NULL, awareness_turnover TINYINT(1) DEFAULT NULL, awareness_equity TINYINT(1) DEFAULT NULL, attest_balance_sheet TINYINT(1) DEFAULT NULL, attest_turnover TINYINT(1) DEFAULT NULL, attest_equity TINYINT(1) DEFAULT NULL, attest_aware TINYINT(1) DEFAULT NULL, attest_truth TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_pro_shareholders (id INT AUTO_INCREMENT NOT NULL, pro_id INT DEFAULT NULL, ubo_declaration_id INT DEFAULT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, address_line1 VARCHAR(255) DEFAULT NULL, address_line2 VARCHAR(255) DEFAULT NULL, city VARCHAR(155) DEFAULT NULL, region VARCHAR(155) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, nationality VARCHAR(255) DEFAULT NULL, birthday DATE DEFAULT NULL, birthplace VARCHAR(255) DEFAULT NULL, birth_department VARCHAR(10) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_E67D71A0C3B7E4BA (pro_id), INDEX IDX_E67D71A09133115D (ubo_declaration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_pro_ubo_declaration (id INT AUTO_INCREMENT NOT NULL, pro_id INT DEFAULT NULL, status VARCHAR(127) DEFAULT NULL, message VARCHAR(511) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_994DBDFEC3B7E4BA (pro_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_want (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, want_nl TINYINT(1) DEFAULT NULL, want_infopack TINYINT(1) DEFAULT NULL, scpi_infopack TINYINT(1) DEFAULT NULL, vefa_infopack TINYINT(1) DEFAULT NULL, contact TINYINT(1) DEFAULT NULL, want_synthetic_file LONGTEXT DEFAULT NULL, want_doc_blog TINYINT(1) DEFAULT NULL, ip VARCHAR(100) DEFAULT NULL, last_name VARCHAR(45) DEFAULT NULL, first_name VARCHAR(45) DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, phone VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_482E59EBA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users_adn (id INT AUTO_INCREMENT NOT NULL, info_id INT DEFAULT NULL, document_id INT DEFAULT NULL, pro_id INT DEFAULT NULL, marketing_id INT DEFAULT NULL, email VARCHAR(180) NOT NULL, last_name VARCHAR(45) DEFAULT NULL, birth_last_name VARCHAR(45) DEFAULT NULL, first_name VARCHAR(45) DEFAULT NULL, birth_first_name VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, birthday DATE DEFAULT NULL, birthplace VARCHAR(255) DEFAULT NULL, postal_code_birthplace VARCHAR(10) DEFAULT NULL, insee_code VARCHAR(10) DEFAULT NULL, insee_code_birthplace VARCHAR(10) DEFAULT NULL, nationality VARCHAR(255) DEFAULT NULL, phone VARCHAR(15) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, address_line1 VARCHAR(255) DEFAULT NULL, address_line2 VARCHAR(255) DEFAULT NULL, tax_address VARCHAR(255) DEFAULT NULL, city VARCHAR(155) DEFAULT NULL, region VARCHAR(155) DEFAULT NULL, postal_code VARCHAR(10) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, image_name VARCHAR(255) DEFAULT NULL, gender VARCHAR(50) DEFAULT NULL, marital_status INT DEFAULT NULL, profession INT DEFAULT NULL, facebook_id VARCHAR(255) DEFAULT NULL, facebook_access_token VARCHAR(255) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, google_access_token VARCHAR(255) DEFAULT NULL, linkedin_id VARCHAR(255) DEFAULT NULL, linkedin_access_token VARCHAR(255) DEFAULT NULL, type INT DEFAULT NULL, retargeted TINYINT(1) DEFAULT NULL, sponsorship TINYINT(1) NOT NULL, pipedrive_id INT DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, how_known VARCHAR(255) DEFAULT NULL, risk1 TINYINT(1) DEFAULT NULL, risk2 TINYINT(1) DEFAULT NULL, step_registration_token VARCHAR(255) DEFAULT NULL, birth_department VARCHAR(10) DEFAULT NULL, birth_country VARCHAR(31) DEFAULT NULL, cosigner TINYINT(1) DEFAULT NULL, interested_by JSON DEFAULT NULL, is_aware_profile TINYINT(1) DEFAULT NULL, description LONGTEXT DEFAULT NULL, step_kyc SMALLINT UNSIGNED DEFAULT NULL, tax_residence VARCHAR(2) DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_2E140DCFE7927C74 (email), UNIQUE INDEX UNIQ_2E140DCF3E04DD9E (step_registration_token), UNIQUE INDEX UNIQ_2E140DCF5D8BC1F8 (info_id), UNIQUE INDEX UNIQ_2E140DCFC33F7837 (document_id), UNIQUE INDEX UNIQ_2E140DCFC3B7E4BA (pro_id), UNIQUE INDEX UNIQ_2E140DCFC6DCB66C (marketing_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01DA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE blog_post ADD CONSTRAINT FK_BA5AE01D12469DE2 FOREIGN KEY (category_id) REFERENCES blog_category (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cocoon_post ADD CONSTRAINT FK_60F5A8F3727ACA70 FOREIGN KEY (parent_id) REFERENCES cocoon_post (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail ADD CONSTRAINT FK_5126AC48A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D8892622A77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating ADD CONSTRAINT FK_D88926227ACA38AB FOREIGN KEY (cocoon_post_id) REFERENCES cocoon_post (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_additional_fields ADD CONSTRAINT FK_D758AAE2A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_cgp ADD CONSTRAINT FK_247F41BCA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_config ADD CONSTRAINT FK_B1D83441A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_control ADD CONSTRAINT FK_B5D63241A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_control ADD CONSTRAINT FK_B5D6324185564492 FOREIGN KEY (create_user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_kyc_document ADD CONSTRAINT FK_DC67AD6A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_kyc_document_file ADD CONSTRAINT FK_F30118B5C33F7837 FOREIGN KEY (document_id) REFERENCES user_kyc_document (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailing ADD CONSTRAINT FK_66D42F54A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_marketing ADD CONSTRAINT FK_9461EC8CA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_shareholders ADD CONSTRAINT FK_E67D71A0C3B7E4BA FOREIGN KEY (pro_id) REFERENCES user_pro (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_shareholders ADD CONSTRAINT FK_E67D71A09133115D FOREIGN KEY (ubo_declaration_id) REFERENCES user_pro_ubo_declaration (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_ubo_declaration ADD CONSTRAINT FK_994DBDFEC3B7E4BA FOREIGN KEY (pro_id) REFERENCES user_pro (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_want ADD CONSTRAINT FK_482E59EBA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD CONSTRAINT FK_2E140DCF5D8BC1F8 FOREIGN KEY (info_id) REFERENCES user_info (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD CONSTRAINT FK_2E140DCFC33F7837 FOREIGN KEY (document_id) REFERENCES user_document (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD CONSTRAINT FK_2E140DCFC3B7E4BA FOREIGN KEY (pro_id) REFERENCES user_pro (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD CONSTRAINT FK_2E140DCFC6DCB66C FOREIGN KEY (marketing_id) REFERENCES user_marketing (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01DA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE blog_post DROP FOREIGN KEY FK_BA5AE01D12469DE2
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE cocoon_post DROP FOREIGN KEY FK_60F5A8F3727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE mail DROP FOREIGN KEY FK_5126AC48A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D8892622A77FBEAF
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE rating DROP FOREIGN KEY FK_D88926227ACA38AB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_additional_fields DROP FOREIGN KEY FK_D758AAE2A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_cgp DROP FOREIGN KEY FK_247F41BCA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_config DROP FOREIGN KEY FK_B1D83441A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_control DROP FOREIGN KEY FK_B5D63241A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_control DROP FOREIGN KEY FK_B5D6324185564492
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_kyc_document DROP FOREIGN KEY FK_DC67AD6A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_kyc_document_file DROP FOREIGN KEY FK_F30118B5C33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailing DROP FOREIGN KEY FK_66D42F54A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_marketing DROP FOREIGN KEY FK_9461EC8CA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_shareholders DROP FOREIGN KEY FK_E67D71A0C3B7E4BA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_shareholders DROP FOREIGN KEY FK_E67D71A09133115D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_pro_ubo_declaration DROP FOREIGN KEY FK_994DBDFEC3B7E4BA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_want DROP FOREIGN KEY FK_482E59EBA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP FOREIGN KEY FK_2E140DCF5D8BC1F8
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP FOREIGN KEY FK_2E140DCFC33F7837
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP FOREIGN KEY FK_2E140DCFC3B7E4BA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP FOREIGN KEY FK_2E140DCFC6DCB66C
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE blog_category
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE blog_post
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE cocoon_post
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE comment
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE contact
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE mail
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE rating
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE spam
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_additional_fields
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_cgp
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_config
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_control
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_info
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_kyc_document
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_kyc_document_file
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_mailing
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_marketing
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_pro
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_pro_shareholders
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_pro_ubo_declaration
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_want
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE users_adn
        SQL);
    }
}
