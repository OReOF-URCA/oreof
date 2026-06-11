<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428072509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adresse DROP INDEX FK_C35F0816AED28ADD, ADD UNIQUE INDEX UNIQ_C35F0816AED28ADD (adresse_origine_copie_id)');
        $this->addSql('ALTER TABLE bloc_competence DROP INDEX FK_711471E969B1B32C, ADD UNIQUE INDEX UNIQ_711471E969B1B32C (bloc_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE but_competence CHANGE nom_court nom_court VARCHAR(40) DEFAULT NULL, CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE competence DROP INDEX FK_94D4687F5775E391, ADD UNIQUE INDEX UNIQ_94D4687F5775E391 (competence_origine_copie_id)');
        $this->addSql('ALTER TABLE element_constitutif DROP INDEX FK_BAFCE2C442B21BE6, ADD UNIQUE INDEX UNIQ_BAFCE2C442B21BE6 (ec_origine_copie_id)');
        $this->addSql('ALTER TABLE formation CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION NOT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE parcours CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE semestre DROP INDEX FK_71688FBC37B87827, ADD UNIQUE INDEX UNIQ_71688FBC37B87827 (semestre_origine_copie_id)');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ue DROP INDEX FK_2E490A9B5F8C9C69, ADD UNIQUE INDEX UNIQ_2E490A9B5F8C9C69 (ue_origine_copie_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mccc CHANGE pourcentage pourcentage DOUBLE PRECISION DEFAULT NULL, CHANGE type_epreuve type_epreuve JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE parcours CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE semestre DROP INDEX UNIQ_71688FBC37B87827, ADD INDEX FK_71688FBC37B87827 (semestre_origine_copie_id)');
        $this->addSql('ALTER TABLE element_constitutif DROP INDEX UNIQ_BAFCE2C442B21BE6, ADD INDEX FK_BAFCE2C442B21BE6 (ec_origine_copie_id)');
        $this->addSql('ALTER TABLE competence DROP INDEX UNIQ_94D4687F5775E391, ADD INDEX FK_94D4687F5775E391 (competence_origine_copie_id)');
        $this->addSql('ALTER TABLE adresse DROP INDEX UNIQ_C35F0816AED28ADD, ADD INDEX FK_C35F0816AED28ADD (adresse_origine_copie_id)');
        $this->addSql('ALTER TABLE ue DROP INDEX UNIQ_2E490A9B5F8C9C69, ADD INDEX FK_2E490A9B5F8C9C69 (ue_origine_copie_id)');
        $this->addSql('ALTER TABLE but_competence CHANGE nom_court nom_court VARCHAR(50) DEFAULT NULL, CHANGE situations situations JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE composantes composantes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE bloc_competence DROP INDEX UNIQ_711471E969B1B32C, ADD INDEX FK_711471E969B1B32C (bloc_competence_origine_copie_id)');
        $this->addSql('ALTER TABLE formation CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo VARCHAR(127) DEFAULT NULL');
        $this->addSql('ALTER TABLE profil CHANGE centre centre VARCHAR(255) DEFAULT NULL');
    }
}
