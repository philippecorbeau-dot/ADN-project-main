<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001105521 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP FOREIGN KEY FK_2E140DCFC6DCB66C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_marketing DROP FOREIGN KEY FK_9461EC8CA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_marketing
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_2E140DCFC6DCB66C ON users_adn
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP marketing_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_marketing (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, utm_source VARCHAR(55) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, utm_medium VARCHAR(55) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, utm_campaign VARCHAR(55) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, utm_content VARCHAR(55) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_9461EC8CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_marketing ADD CONSTRAINT FK_9461EC8CA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD marketing_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD CONSTRAINT FK_2E140DCFC6DCB66C FOREIGN KEY (marketing_id) REFERENCES user_marketing (id) ON UPDATE NO ACTION ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_2E140DCFC6DCB66C ON users_adn (marketing_id)
        SQL);
    }
}
