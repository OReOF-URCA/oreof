<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout du champ ordre sur TimelineDate
 */
final class Version20260930154515 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ ordre sur TimelineDate';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timeline_date ADD ordre INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE timeline_date DROP ordre');
    }
}
