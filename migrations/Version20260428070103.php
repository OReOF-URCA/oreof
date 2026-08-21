<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428070103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fiche_matiere_parcours DROP FOREIGN KEY FK_ED4EE92E5A3AD6AF');
        $this->addSql('ALTER TABLE fiche_matiere_parcours DROP FOREIGN KEY FK_ED4EE92E6E38C0DB');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F1485200282E');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148D2CFEAA');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148A76ED395');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148FF631228');
        $this->addSql('ALTER TABLE user_centre DROP FOREIGN KEY FK_A3F2F148AC12F1AD');
        $this->addSql('ALTER TABLE element_constitutif_but_apprentissage_critique DROP FOREIGN KEY FK_AD71CD1928A4D44F');
        $this->addSql('ALTER TABLE element_constitutif_but_apprentissage_critique DROP FOREIGN KEY FK_AD71CD19631CA21B');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE fiche_matiere_parcours');
        $this->addSql('DROP TABLE user_centre');
        $this->addSql('DROP TABLE element_constitutif_but_apprentissage_critique');
        $this->addSql('ALTER TABLE adresse DROP INDEX FK_C35F0816AED28ADD, ADD UNIQUE INDEX UNIQ_C35F0816AED28ADD (adresse_origine_copie_id)');
        $this->addSql('ALTER TABLE bloc_competence DROP INDEX FK_711471E969B1B32C, ADD UNIQUE INDEX UNIQ_711471E969B1B32C (bloc_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE but_apprentissage_critique DROP INDEX FK_4B89EB3D2B414913, ADD UNIQUE INDEX UNIQ_4B89EB3D2B414913 (but_apprentissage_critique_origine_copie_id)');
        $this->addSql('ALTER TABLE but_apprentissage_critique CHANGE libelle libelle LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE but_competence DROP INDEX FK_A0876EB47B3A7597, ADD UNIQUE INDEX UNIQ_A0876EB47B3A7597 (but_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE but_competence CHANGE nom_court nom_court VARCHAR(40) DEFAULT NULL, CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE but_niveau DROP INDEX FK_8B366E5D6A62A41B, ADD UNIQUE INDEX UNIQ_8B366E5D6A62A41B (but_niveau_origine_copie_id)');
        $this->addSql('ALTER TABLE campagne_collecte RENAME INDEX fk_d428a2e544bfd58 TO IDX_D428A2E544BFD58');
        $this->addSql('ALTER TABLE competence DROP INDEX FK_94D4687F5775E391, ADD UNIQUE INDEX UNIQ_94D4687F5775E391 (competence_origine_copie_id)');
        $this->addSql('ALTER TABLE composante CHANGE etat_composante etat_composante JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE dpe_demande RENAME INDEX fk_5cc9fc9460bb6fe6 TO IDX_5CC9FC9460BB6FE6');
        $this->addSql('ALTER TABLE dpe_demande RENAME INDEX fk_5cc9fc94d2cfeaa TO IDX_5CC9FC94D2CFEAA');
        $this->addSql('ALTER TABLE element_constitutif DROP INDEX FK_BAFCE2C442B21BE6, ADD UNIQUE INDEX UNIQ_BAFCE2C442B21BE6 (ec_origine_copie_id)');
        $this->addSql('ALTER TABLE fiche_matiere CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE fiche_matiere RENAME INDEX fk_88150c90d2cfeaa TO IDX_88150C90D2CFEAA');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF7CCDB81E');
        $this->addSql('DROP INDEX IDX_404021BF7CCDB81E ON formation');
        $this->addSql('ALTER TABLE formation ADD logo VARCHAR(127) DEFAULT NULL, DROP version_parent_id, CHANGE regime_inscription regime_inscription JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE structure_semestres structure_semestres JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_dpe etat_dpe JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE historique CHANGE complements complements JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION NOT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE mention DROP FOREIGN KEY FK_E20259CD4272FC9F');
        $this->addSql('DROP INDEX IDX_E20259CD4272FC9F ON mention');
        $this->addSql('ALTER TABLE mention DROP domaine_id');
        $this->addSql('ALTER TABLE parcours ADD logo VARCHAR(127) DEFAULT NULL, CHANGE codes_rome codes_rome JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE regime_inscription regime_inscription JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_parcours etat_parcours JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE semestre DROP INDEX FK_71688FBC37B87827, ADD UNIQUE INDEX UNIQ_71688FBC37B87827 (semestre_origine_copie_id)');
        $this->addSql('ALTER TABLE semestre CHANGE code_apogee code_apogee VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_diplome ADD logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE ue DROP INDEX FK_2E490A9B5F8C9C69, ADD UNIQUE INDEX UNIQ_2E490A9B5F8C9C69 (ue_origine_copie_id)');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, droits JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', code_role VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, porte VARCHAR(10) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, only_admin TINYINT(1) NOT NULL, centre VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE fiche_matiere_parcours (id INT AUTO_INCREMENT NOT NULL, fiche_matiere_id INT DEFAULT NULL, parcours_id INT DEFAULT NULL, INDEX IDX_ED4EE92E5A3AD6AF (fiche_matiere_id), INDEX IDX_ED4EE92E6E38C0DB (parcours_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE user_centre (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, composante_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, etablissement_id INT DEFAULT NULL, campagne_collecte_id INT DEFAULT NULL, droits JSON NOT NULL COMMENT \'(DC2Type:json)\', INDEX IDX_A3F2F148D2CFEAA (campagne_collecte_id), INDEX IDX_A3F2F148AC12F1AD (composante_id), INDEX IDX_A3F2F1485200282E (formation_id), INDEX IDX_A3F2F148FF631228 (etablissement_id), INDEX IDX_A3F2F148A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE element_constitutif_but_apprentissage_critique (element_constitutif_id INT NOT NULL, but_apprentissage_critique_id INT NOT NULL, INDEX IDX_AD71CD1928A4D44F (element_constitutif_id), INDEX IDX_AD71CD19631CA21B (but_apprentissage_critique_id), PRIMARY KEY(element_constitutif_id, but_apprentissage_critique_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE fiche_matiere_parcours ADD CONSTRAINT FK_ED4EE92E5A3AD6AF FOREIGN KEY (fiche_matiere_id) REFERENCES fiche_matiere (id)');
        $this->addSql('ALTER TABLE fiche_matiere_parcours ADD CONSTRAINT FK_ED4EE92E6E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id)');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F1485200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148D2CFEAA FOREIGN KEY (campagne_collecte_id) REFERENCES campagne_collecte (id)');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148FF631228 FOREIGN KEY (etablissement_id) REFERENCES etablissement (id)');
        $this->addSql('ALTER TABLE user_centre ADD CONSTRAINT FK_A3F2F148AC12F1AD FOREIGN KEY (composante_id) REFERENCES composante (id)');
        $this->addSql('ALTER TABLE element_constitutif_but_apprentissage_critique ADD CONSTRAINT FK_AD71CD1928A4D44F FOREIGN KEY (element_constitutif_id) REFERENCES element_constitutif (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE element_constitutif_but_apprentissage_critique ADD CONSTRAINT FK_AD71CD19631CA21B FOREIGN KEY (but_apprentissage_critique_id) REFERENCES but_apprentissage_critique (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE but_apprentissage_critique DROP INDEX UNIQ_4B89EB3D2B414913, ADD INDEX FK_4B89EB3D2B414913 (but_apprentissage_critique_origine_copie_id)');
        $this->addSql('ALTER TABLE but_apprentissage_critique CHANGE libelle libelle LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE but_niveau DROP INDEX UNIQ_8B366E5D6A62A41B, ADD INDEX FK_8B366E5D6A62A41B (but_niveau_origine_copie_id)');
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION DEFAULT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE campagne_collecte RENAME INDEX idx_d428a2e544bfd58 TO FK_D428A2E544BFD58');
        $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        $this->addSql('ALTER TABLE parcours DROP logo, CHANGE codes_rome codes_rome JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE regime_inscription regime_inscription JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_parcours etat_parcours JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE composante CHANGE etat_composante etat_composante JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE semestre DROP INDEX UNIQ_71688FBC37B87827, ADD INDEX FK_71688FBC37B87827 (semestre_origine_copie_id)');
        $this->addSql('ALTER TABLE semestre CHANGE code_apogee code_apogee VARCHAR(6) DEFAULT NULL');
        $this->addSql('ALTER TABLE element_constitutif DROP INDEX UNIQ_BAFCE2C442B21BE6, ADD INDEX FK_BAFCE2C442B21BE6 (ec_origine_copie_id)');
        $this->addSql('ALTER TABLE historique CHANGE complements complements JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE dpe_demande RENAME INDEX idx_5cc9fc9460bb6fe6 TO FK_5CC9FC9460BB6FE6');
        $this->addSql('ALTER TABLE dpe_demande RENAME INDEX idx_5cc9fc94d2cfeaa TO FK_5CC9FC94D2CFEAA');
        $this->addSql('ALTER TABLE competence DROP INDEX UNIQ_94D4687F5775E391, ADD INDEX FK_94D4687F5775E391 (competence_origine_copie_id)');
        $this->addSql('ALTER TABLE adresse DROP INDEX UNIQ_C35F0816AED28ADD, ADD INDEX FK_C35F0816AED28ADD (adresse_origine_copie_id)');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE ue DROP INDEX UNIQ_2E490A9B5F8C9C69, ADD INDEX FK_2E490A9B5F8C9C69 (ue_origine_copie_id)');
        $this->addSql('ALTER TABLE but_competence DROP INDEX UNIQ_A0876EB47B3A7597, ADD INDEX FK_A0876EB47B3A7597 (but_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE but_competence CHANGE nom_court nom_court VARCHAR(50) DEFAULT NULL, CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE bloc_competence DROP INDEX UNIQ_711471E969B1B32C, ADD INDEX FK_711471E969B1B32C (bloc_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE mention ADD domaine_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mention ADD CONSTRAINT FK_E20259CD4272FC9F FOREIGN KEY (domaine_id) REFERENCES domaine (id)');
        $this->addSql('CREATE INDEX IDX_E20259CD4272FC9F ON mention (domaine_id)');
        $this->addSql('ALTER TABLE formation ADD version_parent_id INT DEFAULT NULL, DROP logo, CHANGE regime_inscription regime_inscription JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE structure_semestres structure_semestres JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_dpe etat_dpe JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF7CCDB81E FOREIGN KEY (version_parent_id) REFERENCES formation (id)');
        $this->addSql('CREATE INDEX IDX_404021BF7CCDB81E ON formation (version_parent_id)');
        $this->addSql('ALTER TABLE type_diplome DROP logo');
        $this->addSql('ALTER TABLE fiche_matiere CHANGE etat_steps etat_steps JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE fiche_matiere RENAME INDEX idx_88150c90d2cfeaa TO FK_88150C90D2CFEAA');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) DEFAULT NULL');
    }
}
