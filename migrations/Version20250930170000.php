<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250930170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Step2/Step3 KYC columns to user_info (objective, investmentTerm, liquidity, realestate, accountSecurities, capitalisation, scpi, income, realestateIncome).';
    }

    public function up(Schema $schema): void
    {
        // MySQL only migration
        // Add columns if they do not exist
        $this->addSql(<<<'SQL'
ALTER TABLE user_info
  ADD COLUMN objective LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
  ADD COLUMN investmentTerm LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)',
  ADD COLUMN liquidity INT DEFAULT NULL,
  ADD COLUMN realestate INT DEFAULT NULL,
  ADD COLUMN accountSecurities INT DEFAULT NULL,
  ADD COLUMN capitalisation INT DEFAULT NULL,
  ADD COLUMN scpi INT DEFAULT NULL,
  ADD COLUMN income INT DEFAULT NULL,
  ADD COLUMN realestateIncome INT DEFAULT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE user_info
  DROP COLUMN realestateIncome,
  DROP COLUMN income,
  DROP COLUMN scpi,
  DROP COLUMN capitalisation,
  DROP COLUMN accountSecurities,
  DROP COLUMN realestate,
  DROP COLUMN liquidity,
  DROP COLUMN investmentTerm,
  DROP COLUMN objective
SQL);
    }
}



