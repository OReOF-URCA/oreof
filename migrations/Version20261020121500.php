<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive migration: existing enum columns remain intact for legacy consumers.
 * Canonical string keys allow custom modalities without integer coercion.
 */
final class Version20261020121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical modality referential keys while retaining legacy enum columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours ADD modalitesEnseignement_referentiel VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE fiche_matiere ADD modaliteEnseignement_referentiel VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE element_constitutif ADD modaliteEnseignement_referentiel VARCHAR(100) DEFAULT NULL');

        $this->addSql('UPDATE parcours SET modalitesEnseignement_referentiel = CAST(modalites_enseignement AS CHAR) WHERE modalites_enseignement IS NOT NULL');
        $this->addSql('UPDATE fiche_matiere SET modaliteEnseignement_referentiel = CAST(modalite_enseignement AS CHAR) WHERE modalite_enseignement IS NOT NULL');
        $this->addSql('UPDATE element_constitutif SET modaliteEnseignement_referentiel = CAST(modalite_enseignement AS CHAR) WHERE modalite_enseignement IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP modalitesEnseignement_referentiel');
        $this->addSql('ALTER TABLE fiche_matiere DROP modaliteEnseignement_referentiel');
        $this->addSql('ALTER TABLE element_constitutif DROP modaliteEnseignement_referentiel');
    }
}
