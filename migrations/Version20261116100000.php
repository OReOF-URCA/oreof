<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Assistance : annonces (mails de masse envoyés par l\'équipe)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_assistance ADD annonce TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_assistance DROP annonce');
    }
}
