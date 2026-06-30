<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114123901 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_activity (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, message LONGTEXT DEFAULT NULL, level VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_4CF9ED5AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_activity ADD CONSTRAINT FK_4CF9ED5AA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_activity DROP FOREIGN KEY FK_4CF9ED5AA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_activity
        SQL);
    }
}
