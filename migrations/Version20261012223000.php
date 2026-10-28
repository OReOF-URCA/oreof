<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout du champ logo sur Composante pour l'icône/logo de la composante (plaquette et fiche).
 */
final class Version20261012223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ logo sur Composante';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE composante ADD logo VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE composante DROP logo');
    }
}
