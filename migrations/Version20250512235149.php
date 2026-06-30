<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250512235149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_mailling (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, newsletter SMALLINT DEFAULT 1 NOT NULL, events SMALLINT DEFAULT 1 NOT NULL, projects SMALLINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_8EE7074AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailling ADD CONSTRAINT FK_8EE7074AA76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailing DROP FOREIGN KEY FK_66D42F54A76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_mailing
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE user_mailing (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, newsletter SMALLINT DEFAULT 1 NOT NULL, events SMALLINT DEFAULT 1 NOT NULL, projects SMALLINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_66D42F54A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = '' 
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailing ADD CONSTRAINT FK_66D42F54A76ED395 FOREIGN KEY (user_id) REFERENCES users_adn (id) ON UPDATE NO ACTION ON DELETE NO ACTION
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_mailling DROP FOREIGN KEY FK_8EE7074AA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE user_mailling
        SQL);
    }
}
