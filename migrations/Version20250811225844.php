<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811225844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_messages (id INT AUTO_INCREMENT NOT NULL, sender_id INT NOT NULL, subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, is_read TINYINT(1) NOT NULL, admin_response LONGTEXT DEFAULT NULL, admin_response_at DATETIME DEFAULT NULL, priority VARCHAR(20) NOT NULL, category VARCHAR(50) NOT NULL, INDEX IDX_3B8FFA96F624B39D (sender_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_messages ADD CONSTRAINT FK_3B8FFA96F624B39D FOREIGN KEY (sender_id) REFERENCES users_adn (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_messages DROP FOREIGN KEY FK_3B8FFA96F624B39D
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_messages
        SQL);
    }
}
