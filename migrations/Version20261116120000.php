<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ lien_court sur la table parcours';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours ADD lien_court VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP lien_court');
    }
}
