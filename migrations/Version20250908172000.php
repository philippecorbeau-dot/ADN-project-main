<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250908172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expiration_date to user_kyc_document';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_kyc_document ADD expiration_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_kyc_document DROP expiration_date');
    }
}


