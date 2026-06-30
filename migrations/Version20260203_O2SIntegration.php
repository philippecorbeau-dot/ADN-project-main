<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * O2S Integration Migration
 * 
 * Adds fields to users_adn and product_accounts tables to support
 * synchronization with Harvest O2S API.
 */
final class Version20260203_O2SIntegration extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add O2S integration fields to users and product_accounts tables';
    }

    public function up(Schema $schema): void
    {
        // Add O2S fields to users_adn table
        $this->addSql('ALTER TABLE users_adn ADD o2s_contact_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users_adn ADD o2s_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_users_o2s_contact_id ON users_adn (o2s_contact_id)');

        // Add O2S fields to product_accounts table
        $this->addSql('ALTER TABLE product_accounts ADD o2s_compte_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_accounts ADD o2s_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE product_accounts ADD o2s_valuation DECIMAL(15, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_accounts ADD o2s_valuation_date DATE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_o2s_compte_id ON product_accounts (o2s_compte_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove O2S fields from users_adn table
        $this->addSql('DROP INDEX idx_users_o2s_contact_id ON users_adn');
        $this->addSql('ALTER TABLE users_adn DROP o2s_contact_id');
        $this->addSql('ALTER TABLE users_adn DROP o2s_synced_at');

        // Remove O2S fields from product_accounts table
        $this->addSql('DROP INDEX idx_o2s_compte_id ON product_accounts');
        $this->addSql('ALTER TABLE product_accounts DROP o2s_compte_id');
        $this->addSql('ALTER TABLE product_accounts DROP o2s_synced_at');
        $this->addSql('ALTER TABLE product_accounts DROP o2s_valuation');
        $this->addSql('ALTER TABLE product_accounts DROP o2s_valuation_date');
    }
}

