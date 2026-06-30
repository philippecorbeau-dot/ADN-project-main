<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251120160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes buy_price, buy_date, buy_cost sur holdings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings
            ADD buy_price NUMERIC(18, 6) DEFAULT NULL,
            ADD buy_date DATE DEFAULT NULL,
            ADD buy_cost NUMERIC(18, 2) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE holdings
            DROP buy_price,
            DROP buy_date,
            DROP buy_cost
        SQL);
    }
}


