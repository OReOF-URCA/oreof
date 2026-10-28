<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027224000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate Langue entity and join tables to JSON columns in fiche_matiere';
    }

    public function up(Schema $schema): void
    {
        // 1. Ajouter les colonnes JSON dans fiche_matiere avec ['fr'] par défaut
        $this->addSql("ALTER TABLE fiche_matiere ADD langue_dispense JSON DEFAULT '[\"fr\"]' NOT NULL, ADD langue_support JSON DEFAULT '[\"fr\"]' NOT NULL");

        // 2. Transférer les données de dispensation existantes (ou ['fr'] par défaut si vide)
        $this->addSql("
            UPDATE fiche_matiere fm
            LEFT JOIN (
                SELECT fmd.fiche_matiere_id, JSON_ARRAYAGG(
                    CASE 
                        WHEN LOWER(l.code_iso) = 'ch' THEN 'zh'
                        WHEN LOWER(l.code_iso) = 'fs' THEN 'fr'
                        ELSE LOWER(l.code_iso)
                    END
                ) as codes
                FROM fiche_matiere_langue_dispense fmd
                JOIN langue l ON l.id = fmd.langue_id
                GROUP BY fmd.fiche_matiere_id
            ) d ON d.fiche_matiere_id = fm.id
            SET fm.langue_dispense = COALESCE(NULLIF(d.codes, JSON_ARRAY()), JSON_ARRAY('fr'))
        ");

        // 3. Transférer les données de support existantes (ou ['fr'] par défaut si vide)
        $this->addSql("
            UPDATE fiche_matiere fm
            LEFT JOIN (
                SELECT fms.fiche_matiere_id, JSON_ARRAYAGG(
                    CASE 
                        WHEN LOWER(l.code_iso) = 'ch' THEN 'zh'
                        WHEN LOWER(l.code_iso) = 'fs' THEN 'fr'
                        ELSE LOWER(l.code_iso)
                    END
                ) as codes
                FROM fiche_matiere_langue_support fms
                JOIN langue l ON l.id = fms.langue_id
                GROUP BY fms.fiche_matiere_id
            ) s ON s.fiche_matiere_id = fm.id
            SET fm.langue_support = COALESCE(NULLIF(s.codes, JSON_ARRAY()), JSON_ARRAY('fr'))
        ");

        // 4. Supprimer les tables de jointure et la table langue
        $this->addSql('DROP TABLE fiche_matiere_langue_dispense');
        $this->addSql('DROP TABLE fiche_matiere_langue_support');
        $this->addSql('DROP TABLE langue');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE langue (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(50) NOT NULL, code_iso VARCHAR(2) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fiche_matiere_langue_dispense (fiche_matiere_id INT NOT NULL, langue_id INT NOT NULL, INDEX IDX_FMD_FM (fiche_matiere_id), INDEX IDX_FMD_L (langue_id), PRIMARY KEY(fiche_matiere_id, langue_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fiche_matiere_langue_support (fiche_matiere_id INT NOT NULL, langue_id INT NOT NULL, INDEX IDX_FMS_FM (fiche_matiere_id), INDEX IDX_FMS_L (langue_id), PRIMARY KEY(fiche_matiere_id, langue_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fiche_matiere DROP langue_dispense, DROP langue_support');
    }
}
