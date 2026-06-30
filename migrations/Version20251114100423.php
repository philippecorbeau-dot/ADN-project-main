<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114100423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_contributions RENAME INDEX idx_pc_product TO IDX_ACD4020C80C0091
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn ADD reset_token VARCHAR(100) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE product_contributions RENAME INDEX idx_acd4020c80c0091 TO IDX_PC_PRODUCT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users_adn DROP reset_token, DROP reset_token_expires_at
        SQL);
    }
}
