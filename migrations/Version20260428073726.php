<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428073726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE but_competence CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE but_niveau DROP INDEX UNIQ_8B366E5D6A62A41B, ADD INDEX IDX_8B366E5D6A62A41B (but_niveau_origine_copie_id)');
        $this->addSql('ALTER TABLE competence RENAME INDEX fk_94d4687f5775e391 TO IDX_94D4687F5775E391');
        $this->addSql('ALTER TABLE element_constitutif RENAME INDEX fk_bafce2c442b21be6 TO IDX_BAFCE2C442B21BE6');
        $this->addSql('ALTER TABLE fiche_matiere DROP INDEX UNIQ_88150C90C42E70CD, ADD INDEX IDX_88150C90C42E70CD (fiche_matiere_origine_copie_id)');
        $this->addSql('ALTER TABLE formation DROP INDEX UNIQ_404021BF7EF7145A, ADD INDEX IDX_404021BF7EF7145A (formation_origine_copie_id)');
        $this->addSql('ALTER TABLE formation CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION NOT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE parcours DROP INDEX UNIQ_99B1DEE357879395, ADD INDEX IDX_99B1DEE357879395 (parcours_origine_copie_id)');
        $this->addSql('ALTER TABLE parcours CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE semestre RENAME INDEX fk_71688fbc37b87827 TO IDX_71688FBC37B87827');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ue RENAME INDEX fk_2e490a9b5f8c9c69 TO IDX_2E490A9B5F8C9C69');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE but_niveau DROP INDEX IDX_8B366E5D6A62A41B, ADD UNIQUE INDEX UNIQ_8B366E5D6A62A41B (but_niveau_origine_copie_id)');
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION DEFAULT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE parcours DROP INDEX IDX_99B1DEE357879395, ADD UNIQUE INDEX UNIQ_99B1DEE357879395 (parcours_origine_copie_id)');
        $this->addSql('ALTER TABLE parcours CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE semestre RENAME INDEX idx_71688fbc37b87827 TO FK_71688FBC37B87827');
        $this->addSql('ALTER TABLE element_constitutif RENAME INDEX idx_bafce2c442b21be6 TO FK_BAFCE2C442B21BE6');
        $this->addSql('ALTER TABLE competence RENAME INDEX idx_94d4687f5775e391 TO FK_94D4687F5775E391');
        $this->addSql('ALTER TABLE ue RENAME INDEX idx_2e490a9b5f8c9c69 TO FK_2E490A9B5F8C9C69');
        $this->addSql('ALTER TABLE but_competence CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE formation DROP INDEX IDX_404021BF7EF7145A, ADD UNIQUE INDEX UNIQ_404021BF7EF7145A (formation_origine_copie_id)');
        $this->addSql('ALTER TABLE formation CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE fiche_matiere DROP INDEX IDX_88150C90C42E70CD, ADD UNIQUE INDEX UNIQ_88150C90C42E70CD (fiche_matiere_origine_copie_id)');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) DEFAULT NULL');
    }
}
