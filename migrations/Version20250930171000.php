<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250930171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename user_info.accountSecurities to account_securities to match Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        // MySQL rename column
        // Renommer seulement si l'ancienne colonne existe
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            return; // safe no-op for other platforms
        }
        // Try rename, ignore if already correct
        $this->addSql("SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_info' AND COLUMN_NAME = 'accountSecurities')");
        $this->addSql("SET @exists_new := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_info' AND COLUMN_NAME = 'account_securities')");
        $this->addSql("SET @stmt := IF(@exists = 1 AND @exists_new = 0, 'ALTER TABLE user_info CHANGE accountSecurities account_securities INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql("PREPARE s FROM @stmt");
        $this->addSql("EXECUTE s");
        $this->addSql("DEALLOCATE PREPARE s");
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();
        if ($platform !== 'mysql') {
            return;
        }
        $this->addSql("SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_info' AND COLUMN_NAME = 'account_securities')");
        $this->addSql("SET @exists_new := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_info' AND COLUMN_NAME = 'accountSecurities')");
        $this->addSql("SET @stmt := IF(@exists = 1 AND @exists_new = 0, 'ALTER TABLE user_info CHANGE account_securities accountSecurities INT DEFAULT NULL', 'SELECT 1')");
        $this->addSql("PREPARE s FROM @stmt");
        $this->addSql("EXECUTE s");
        $this->addSql("DEALLOCATE PREPARE s");
    }
}



