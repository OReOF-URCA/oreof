<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime les colonnes legacy capacite_accueil sur les tables formation et parcours (remplacées par les capacités sur les années)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation DROP COLUMN capacite_accueil');
        $this->addSql('ALTER TABLE parcours DROP COLUMN capacite_accueil');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE formation ADD capacite_accueil INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE parcours ADD capacite_accueil INT DEFAULT 0 NOT NULL');
    }
}
