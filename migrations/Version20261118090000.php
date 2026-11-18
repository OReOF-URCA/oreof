<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Module Assistance : notifications système séparées des demandes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_assistance ADD notification TINYINT DEFAULT 0 NOT NULL');
        // expéditeurs d'envoi automatique (no-reply, robot…) désormais classés automatiques
        $this->addSql("UPDATE message_assistance SET automatique = 1 WHERE sens = 'entrant' AND expediteur_email REGEXP '^(no-?reply|ne-?pas-?repondre|robot)@'");
        // demandes dont tous les mails sont automatiques (notes internes exclues) : notifications système
        $this->addSql("UPDATE demande_assistance d SET d.notification = 1, d.statut = 'resolue'
            WHERE d.annonce = 0
              AND EXISTS (SELECT 1 FROM message_assistance m WHERE m.demande_id = d.id AND m.sens <> 'note')
              AND NOT EXISTS (SELECT 1 FROM message_assistance m WHERE m.demande_id = d.id AND m.sens <> 'note' AND m.automatique = 0)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_assistance DROP notification');
    }
}
