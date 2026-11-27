<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table coherence_analyse pour le suivi de cohérence textuelle et IA des parcours et fiches matières';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE coherence_analyse (id INT AUTO_INCREMENT NOT NULL, parcours_id INT DEFAULT NULL, fiche_matiere_id INT DEFAULT NULL, entity_type VARCHAR(32) NOT NULL, entity_id INT NOT NULL, content_hash VARCHAR(64) NOT NULL, status VARCHAR(24) NOT NULL, score INT DEFAULT NULL, deterministic_score INT DEFAULT NULL, summary LONGTEXT DEFAULT NULL, issues JSON NOT NULL, is_ai_generated TINYINT NOT NULL, provider_label VARCHAR(120) DEFAULT NULL, analyzed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_67890A124E38C15 (parcours_id), UNIQUE INDEX UNIQ_67890A12C4E86A7F (fiche_matiere_id), INDEX IDX_COHERENCE_STATUS (status), INDEX IDX_COHERENCE_ANALYZED_AT (analyzed_at), UNIQUE INDEX UNIQ_COHERENCE_ENTITY (entity_type, entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE coherence_analyse ADD CONSTRAINT FK_COHERENCE_PARCOURS FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE coherence_analyse ADD CONSTRAINT FK_COHERENCE_FICHE_MATIERE FOREIGN KEY (fiche_matiere_id) REFERENCES fiche_matiere (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE coherence_analyse DROP FOREIGN KEY FK_COHERENCE_PARCOURS');
        $this->addSql('ALTER TABLE coherence_analyse DROP FOREIGN KEY FK_COHERENCE_FICHE_MATIERE');
        $this->addSql('DROP TABLE coherence_analyse');
    }
}
