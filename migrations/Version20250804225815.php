<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250804225815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_complex_products (id INT AUTO_INCREMENT NOT NULL, question_1 VARCHAR(20) DEFAULT NULL, question_2 VARCHAR(20) DEFAULT NULL, question_3 VARCHAR(20) DEFAULT NULL, question_4 VARCHAR(20) DEFAULT NULL, question_5 VARCHAR(20) DEFAULT NULL, question_6 VARCHAR(20) DEFAULT NULL, question_7 VARCHAR(20) DEFAULT NULL, question_8 VARCHAR(20) DEFAULT NULL, question_9 VARCHAR(20) DEFAULT NULL, question_10 VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_education_level (id INT AUTO_INCREMENT NOT NULL, level VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_financial_products (id INT AUTO_INCREMENT NOT NULL, question_1 VARCHAR(20) DEFAULT NULL, question_2 VARCHAR(20) DEFAULT NULL, question_3 VARCHAR(20) DEFAULT NULL, question_4 VARCHAR(20) DEFAULT NULL, question_5 VARCHAR(20) DEFAULT NULL, question_6 VARCHAR(20) DEFAULT NULL, question_7 VARCHAR(20) DEFAULT NULL, question_8 VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_investment_experience (id INT AUTO_INCREMENT NOT NULL, has_lost_significant_amounts TINYINT(1) DEFAULT NULL, portfolio_loss_percentage INT DEFAULT NULL, manages_own_portfolio TINYINT(1) DEFAULT NULL, portfolio_securities_lines INT DEFAULT NULL, concentrates_on_single_security TINYINT(1) DEFAULT NULL, appropriateness_test_performed TINYINT(1) DEFAULT NULL, orders_through_cif TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_investor (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, market_abuse_id INT DEFAULT NULL, education_level_id INT DEFAULT NULL, investment_experience_id INT DEFAULT NULL, financial_products_knowledge_id INT DEFAULT NULL, complex_products_knowledge_id INT DEFAULT NULL, market_experience_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', is_completed TINYINT(1) DEFAULT 0 NOT NULL, score INT DEFAULT NULL, profile_type VARCHAR(50) DEFAULT NULL, INDEX IDX_5A86D242A76ED395 (user_id), UNIQUE INDEX UNIQ_5A86D242F89E2A36 (market_abuse_id), UNIQUE INDEX UNIQ_5A86D242D7A5352E (education_level_id), UNIQUE INDEX UNIQ_5A86D2427A650879 (investment_experience_id), UNIQUE INDEX UNIQ_5A86D242428E1FB (financial_products_knowledge_id), UNIQUE INDEX UNIQ_5A86D242FA97CDAB (complex_products_knowledge_id), UNIQUE INDEX UNIQ_5A86D242D7071316 (market_experience_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_market_abuse (id INT AUTO_INCREMENT NOT NULL, has_other_securities_accounts TINYINT(1) DEFAULT NULL, has_financial_profession TINYINT(1) DEFAULT NULL, profession_details LONGTEXT DEFAULT NULL, is_listed_company_director TINYINT(1) DEFAULT NULL, listed_company_details LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_knowledge_market_experience (id INT AUTO_INCREMENT NOT NULL, has_stocks_experience TINYINT(1) DEFAULT NULL, stocks_operations_count VARCHAR(50) DEFAULT NULL, stocks_volume VARCHAR(50) DEFAULT NULL, has_bonds_experience TINYINT(1) DEFAULT NULL, bonds_operations_count VARCHAR(50) DEFAULT NULL, bonds_volume VARCHAR(50) DEFAULT NULL, has_ucits_experience TINYINT(1) DEFAULT NULL, ucits_operations_count VARCHAR(50) DEFAULT NULL, ucits_volume VARCHAR(50) DEFAULT NULL, has_real_estate_experience TINYINT(1) DEFAULT NULL, real_estate_operations_count VARCHAR(50) DEFAULT NULL, real_estate_volume VARCHAR(50) DEFAULT NULL, has_complex_instruments_experience TINYINT(1) DEFAULT NULL, complex_instruments_operations_count VARCHAR(50) DEFAULT NULL, complex_instruments_volume VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242F89E2A36 FOREIGN KEY (market_abuse_id) REFERENCES user_knowledge_market_abuse (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242D7A5352E FOREIGN KEY (education_level_id) REFERENCES user_knowledge_education_level (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D2427A650879 FOREIGN KEY (investment_experience_id) REFERENCES user_knowledge_investment_experience (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242428E1FB FOREIGN KEY (financial_products_knowledge_id) REFERENCES user_knowledge_financial_products (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242FA97CDAB FOREIGN KEY (complex_products_knowledge_id) REFERENCES user_knowledge_complex_products (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor ADD CONSTRAINT FK_5A86D242D7071316 FOREIGN KEY (market_experience_id) REFERENCES user_knowledge_market_experience (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242F89E2A36
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242D7A5352E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D2427A650879
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242428E1FB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242FA97CDAB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_knowledge_investor DROP FOREIGN KEY FK_5A86D242D7071316
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_complex_products
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_education_level
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_financial_products
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_investment_experience
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_investor
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_market_abuse
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_knowledge_market_experience
        SQL);
    }
}
