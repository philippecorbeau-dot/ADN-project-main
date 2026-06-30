<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009142507 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_info ADD deposit_savings_checking INT DEFAULT NULL, ADD deposit_savings_livret_a INT DEFAULT NULL, ADD deposit_savings_ldd INT DEFAULT NULL, ADD deposit_savings_csl INT DEFAULT NULL, ADD deposit_savings_other INT DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE user_info DROP deposit_savings_checking, DROP deposit_savings_livret_a, DROP deposit_savings_ldd, DROP deposit_savings_csl, DROP deposit_savings_other
        SQL);
    }
}
