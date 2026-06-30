<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250921222516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE fx_rates (id INT AUTO_INCREMENT NOT NULL, base VARCHAR(3) NOT NULL, counter VARCHAR(3) NOT NULL, date DATE NOT NULL, rate NUMERIC(18, 8) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE holdings (id INT AUTO_INCREMENT NOT NULL, product_account_id INT NOT NULL, instrument_id INT NOT NULL, INDEX IDX_EF81C58280C0091 (product_account_id), INDEX IDX_EF81C582CF11D9C (instrument_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE instruments (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, symbol VARCHAR(50) NOT NULL, exchange VARCHAR(10) NOT NULL, currency VARCHAR(10) NOT NULL, isin VARCHAR(12) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE price_snapshots (id INT AUTO_INCREMENT NOT NULL, instrument_id INT NOT NULL, date DATE NOT NULL, close NUMERIC(15, 6) NOT NULL, INDEX IDX_CE2075C1CF11D9C (instrument_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_accounts (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, distributor VARCHAR(50) NOT NULL, internal_name VARCHAR(255) NOT NULL, display_alias VARCHAR(255) DEFAULT NULL, fiscal_date DATE NOT NULL, initial_amount NUMERIC(15, 2) NOT NULL, INDEX IDX_178F43BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings ADD CONSTRAINT FK_EF81C58280C0091 FOREIGN KEY (product_account_id) REFERENCES product_accounts (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings ADD CONSTRAINT FK_EF81C582CF11D9C FOREIGN KEY (instrument_id) REFERENCES instruments (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE price_snapshots ADD CONSTRAINT FK_CE2075C1CF11D9C FOREIGN KEY (instrument_id) REFERENCES instruments (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_accounts ADD CONSTRAINT FK_178F43BA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings DROP FOREIGN KEY FK_EF81C58280C0091
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings DROP FOREIGN KEY FK_EF81C582CF11D9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE price_snapshots DROP FOREIGN KEY FK_CE2075C1CF11D9C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE product_accounts DROP FOREIGN KEY FK_178F43BA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE fx_rates
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE holdings
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE instruments
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE price_snapshots
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE product_accounts
        SQL);
    }
}
