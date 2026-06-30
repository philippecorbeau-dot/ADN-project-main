<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251003002105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajoute la colonne rent si elle est absente
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['user_info'])) {
            $columns = array_map(static function ($col) { return $col->getName(); }, $schemaManager->listTableColumns('user_info'));
            if (!in_array('rent', $columns, true)) {
                $this->addSql('ALTER TABLE user_info ADD rent INT DEFAULT NULL');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // Supprime la colonne rent si elle existe
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['user_info'])) {
            $columns = array_map(static function ($col) { return $col->getName(); }, $schemaManager->listTableColumns('user_info'));
            if (in_array('rent', $columns, true)) {
                $this->addSql('ALTER TABLE user_info DROP COLUMN rent');
            }
        }
    }
}
