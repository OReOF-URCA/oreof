<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout du champ api_keys sur Etablissement pour stocker les clés et configurations d'API tierces.
 */
final class Version20261012120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ api_keys sur Etablissement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etablissement ADD api_keys JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE etablissement DROP api_keys');
    }
}
