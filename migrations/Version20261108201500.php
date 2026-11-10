<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refonte des mentions : création de la table accreditation, liaison des mentions à l\'accréditation, ajout de formation.classique et dissociation mention/type_diplome';
    }

    public function up(Schema $schema): void
    {
        // 1. Table accreditation
        $this->addSql('CREATE TABLE IF NOT EXISTS accreditation (
            id INT AUTO_INCREMENT NOT NULL,
            libelle VARCHAR(50) NOT NULL,
            annee_debut INT NOT NULL,
            annee_fin INT NOT NULL,
            actif TINYINT(1) DEFAULT 1 NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // 2. Colonne classique sur formation
        $this->addSql('ALTER TABLE formation ADD classique TINYINT(1) DEFAULT NULL');

        // Initialisation de formation.classique depuis type_diplome.classique
        $this->addSql('UPDATE formation f INNER JOIN type_diplome td ON f.type_diplome_id = td.id SET f.classique = td.classique WHERE f.classique IS NULL');

        // 3. Période d\'accréditation par défaut 2024-2028
        $this->addSql("INSERT INTO accreditation (libelle, annee_debut, annee_fin, actif)
            SELECT '2024-2028', 2024, 2028, 1
            WHERE NOT EXISTS (SELECT 1 FROM accreditation WHERE libelle = '2024-2028')");

        // 4. Colonne accreditation_id sur mention
        $this->addSql('ALTER TABLE mention ADD accreditation_id INT DEFAULT NULL');
        $this->addSql('UPDATE mention SET accreditation_id = (SELECT id FROM accreditation WHERE libelle = \'2024-2028\' LIMIT 1) WHERE accreditation_id IS NULL');
        $this->addSql('ALTER TABLE mention ADD CONSTRAINT FK_ACC_MENTION FOREIGN KEY (accreditation_id) REFERENCES accreditation (id)');
        $this->addSql('CREATE INDEX IDX_ACC_MENTION ON mention (accreditation_id)');

        // 5. Suppression de la contrainte et de la colonne type_diplome_id sur mention
        $this->addSql('ALTER TABLE mention DROP FOREIGN KEY FK_E20259CD3BFB8FC7');
        $this->addSql('DROP INDEX IDX_E20259CD3BFB8FC7 ON mention');
        $this->addSql('ALTER TABLE mention DROP COLUMN type_diplome_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mention ADD type_diplome_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mention ADD CONSTRAINT FK_E20259CD3BFB8FC7 FOREIGN KEY (type_diplome_id) REFERENCES type_diplome (id)');
        $this->addSql('CREATE INDEX IDX_E20259CD3BFB8FC7 ON mention (type_diplome_id)');

        $this->addSql('ALTER TABLE mention DROP FOREIGN KEY FK_ACC_MENTION');
        $this->addSql('DROP INDEX IDX_ACC_MENTION ON mention');
        $this->addSql('ALTER TABLE mention DROP COLUMN accreditation_id');

        $this->addSql('ALTER TABLE formation DROP COLUMN classique');
        $this->addSql('DROP TABLE accreditation');
    }
}

