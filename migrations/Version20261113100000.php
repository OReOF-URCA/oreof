<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Assistance : demandes, messages et pièces jointes reçus par mail';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demande_assistance (id INT AUTO_INCREMENT NOT NULL, sujet VARCHAR(255) NOT NULL, sujet_normalise VARCHAR(255) NOT NULL, statut VARCHAR(30) NOT NULL, demandeur_email VARCHAR(180) NOT NULL, demandeur_nom VARCHAR(255) DEFAULT NULL, created DATETIME NOT NULL, date_dernier_message DATETIME NOT NULL, demandeur_id INT DEFAULT NULL, responsable_id INT DEFAULT NULL, parcours_id INT DEFAULT NULL, formation_id INT DEFAULT NULL, INDEX idx_demande_assistance_sujet (sujet_normalise), INDEX idx_demande_assistance_dernier (date_dernier_message), INDEX IDX_ED838F5395A6EE59 (demandeur_id), INDEX IDX_ED838F5353C59D72 (responsable_id), INDEX IDX_ED838F536E38C0DB (parcours_id), INDEX IDX_ED838F535200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_assistance (id INT AUTO_INCREMENT NOT NULL, sens VARCHAR(10) NOT NULL, message_id VARCHAR(255) DEFAULT NULL, in_reply_to VARCHAR(255) DEFAULT NULL, references_mail LONGTEXT DEFAULT NULL, expediteur_email VARCHAR(180) NOT NULL, expediteur_nom VARCHAR(255) DEFAULT NULL, destinataires JSON NOT NULL, sujet VARCHAR(255) NOT NULL, corps_texte LONGTEXT NOT NULL, corps_html LONGTEXT DEFAULT NULL, date_envoi DATETIME NOT NULL, automatique TINYINT NOT NULL, demande_id INT NOT NULL, auteur_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D49FA96D537A1329 (message_id), INDEX IDX_D49FA96D80E95E18 (demande_id), INDEX IDX_D49FA96D60BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE piece_jointe_assistance (id INT AUTO_INCREMENT NOT NULL, nom_original VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, taille INT NOT NULL, chemin VARCHAR(255) NOT NULL, message_id INT NOT NULL, INDEX IDX_9DE0AA8A537A1329 (message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demande_assistance ADD CONSTRAINT FK_ED838F5395A6EE59 FOREIGN KEY (demandeur_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_assistance ADD CONSTRAINT FK_ED838F5353C59D72 FOREIGN KEY (responsable_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_assistance ADD CONSTRAINT FK_ED838F536E38C0DB FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE demande_assistance ADD CONSTRAINT FK_ED838F535200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message_assistance ADD CONSTRAINT FK_D49FA96D80E95E18 FOREIGN KEY (demande_id) REFERENCES demande_assistance (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_assistance ADD CONSTRAINT FK_D49FA96D60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE piece_jointe_assistance ADD CONSTRAINT FK_9DE0AA8A537A1329 FOREIGN KEY (message_id) REFERENCES message_assistance (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_assistance DROP FOREIGN KEY FK_ED838F5395A6EE59');
        $this->addSql('ALTER TABLE demande_assistance DROP FOREIGN KEY FK_ED838F5353C59D72');
        $this->addSql('ALTER TABLE demande_assistance DROP FOREIGN KEY FK_ED838F536E38C0DB');
        $this->addSql('ALTER TABLE demande_assistance DROP FOREIGN KEY FK_ED838F535200282E');
        $this->addSql('ALTER TABLE message_assistance DROP FOREIGN KEY FK_D49FA96D80E95E18');
        $this->addSql('ALTER TABLE message_assistance DROP FOREIGN KEY FK_D49FA96D60BB6FE6');
        $this->addSql('ALTER TABLE piece_jointe_assistance DROP FOREIGN KEY FK_9DE0AA8A537A1329');
        $this->addSql('DROP TABLE demande_assistance');
        $this->addSql('DROP TABLE message_assistance');
        $this->addSql('DROP TABLE piece_jointe_assistance');
    }
}
