<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811145653 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE investment_opportunity_clicks (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, product_type VARCHAR(50) NOT NULL, action VARCHAR(20) NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, clicked_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', referrer VARCHAR(255) DEFAULT NULL, INDEX IDX_5A7FD108A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE investment_opportunity_clicks ADD CONSTRAINT FK_5A7FD108A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE investment_opportunity_clicks DROP FOREIGN KEY FK_5A7FD108A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE investment_opportunity_clicks
        SQL);
    }
}
