<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251113100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_contributions table to store additional payments for a product account.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_contributions (
  id INT AUTO_INCREMENT NOT NULL,
  product_account_id INT NOT NULL,
  amount NUMERIC(15, 2) NOT NULL,
  contribution_date DATE DEFAULT NULL,
  note VARCHAR(255) DEFAULT NULL,
  INDEX IDX_PC_PRODUCT (product_account_id),
  PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE product_contributions
  ADD CONSTRAINT FK_PC_PRODUCT FOREIGN KEY (product_account_id)
  REFERENCES product_accounts (id) ON DELETE CASCADE
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DROP TABLE IF EXISTS product_contributions
SQL);
    }
}


