<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table de jointure mention_type_diplome pour associer plusieurs types de diplômes possibles à une mention';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS mention_type_diplome (
            mention_id INT NOT NULL,
            type_diplome_id INT NOT NULL,
            INDEX IDX_MENTION_TD_M (mention_id),
            INDEX IDX_MENTION_TD_TD (type_diplome_id),
            PRIMARY KEY(mention_id, type_diplome_id),
            CONSTRAINT FK_MENTION_TD_M FOREIGN KEY (mention_id) REFERENCES mention (id) ON DELETE CASCADE,
            CONSTRAINT FK_MENTION_TD_TD FOREIGN KEY (type_diplome_id) REFERENCES type_diplome (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Pré-remplissage des types de diplômes possibles par mention à partir des formations existantes
        $this->addSql('INSERT IGNORE INTO mention_type_diplome (mention_id, type_diplome_id)
            SELECT DISTINCT f.mention_id, f.type_diplome_id
            FROM formation f
            WHERE f.mention_id IS NOT NULL AND f.type_diplome_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS mention_type_diplome');
    }
}

