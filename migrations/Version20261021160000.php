<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive migration: existing enum columns remain intact for legacy consumers.
 * Canonical string keys allow custom language and formation levels without integer coercion.
 */
final class Version20261021160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical referential keys for niveauFrancais, niveauEntree and niveauSortie while retaining legacy enum columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours ADD niveau_francais_referentiel VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD niveau_entree_referentiel VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD niveau_sortie_referentiel VARCHAR(50) DEFAULT NULL');

        $this->addSql('UPDATE parcours SET niveau_francais_referentiel = CAST(niveau_francais AS CHAR) WHERE niveau_francais IS NOT NULL');
        $this->addSql('UPDATE formation SET niveau_entree_referentiel = CAST(niveau_entree AS CHAR) WHERE niveau_entree IS NOT NULL');
        $this->addSql('UPDATE formation SET niveau_sortie_referentiel = CAST(niveau_sortie AS CHAR) WHERE niveau_sortie IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP niveau_francais_referentiel');
        $this->addSql('ALTER TABLE formation DROP niveau_entree_referentiel');
        $this->addSql('ALTER TABLE formation DROP niveau_sortie_referentiel');
    }
}
