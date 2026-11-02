<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive migration: existing enum column remains intact for legacy consumers.
 * Canonical string key allows custom duration units without enum coercion.
 */
final class Version20261021153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical duration unit referential key to parcours while retaining legacy enum column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours ADD duree_parcours_unite_referentiel VARCHAR(50) DEFAULT NULL');
        $this->addSql('UPDATE parcours SET duree_parcours_unite_referentiel = CAST(duree_parcours_unite AS CHAR) WHERE duree_parcours_unite IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP duree_parcours_unite_referentiel');
    }
}
