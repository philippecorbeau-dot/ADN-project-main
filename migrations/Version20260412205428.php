<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260412205428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE manual_pam_overrides (id INT AUTO_INCREMENT NOT NULL, product_account_id INT NOT NULL, asset_id VARCHAR(100) NOT NULL, asset_label VARCHAR(255) NOT NULL, pam_value NUMERIC(18, 6) NOT NULL, updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_52D6944880C0091 (product_account_id), UNIQUE INDEX uniq_pam_account_asset (product_account_id, asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE manual_pam_overrides ADD CONSTRAINT FK_52D6944880C0091 FOREIGN KEY (product_account_id) REFERENCES product_accounts (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE manual_pam_overrides DROP FOREIGN KEY FK_52D6944880C0091
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE manual_pam_overrides
        SQL);
    }
}
