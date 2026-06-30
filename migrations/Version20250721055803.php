<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250721055803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE stocks (id INT AUTO_INCREMENT NOT NULL, symbol VARCHAR(10) NOT NULL, name VARCHAR(255) NOT NULL, price NUMERIC(10, 2) NOT NULL, `change` NUMERIC(10, 2) NOT NULL, change_percent NUMERIC(5, 2) NOT NULL, volume INT NOT NULL, high NUMERIC(10, 2) NOT NULL, low NUMERIC(10, 2) NOT NULL, open NUMERIC(10, 2) NOT NULL, previous_close NUMERIC(10, 2) NOT NULL, is_positive TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_56F79805ECC836F9 (symbol), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            DROP TABLE stocks
        SQL);
    }
}
