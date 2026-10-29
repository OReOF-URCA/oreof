<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create portail entity table and add portail_id relation on annee table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portail (id INT AUTO_INCREMENT NOT NULL, campagne_id INT NOT NULL, libelle VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, capacite_accueil INT DEFAULT 0 NOT NULL, created DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PORTAIL_CAMPAGNE (campagne_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE portail ADD CONSTRAINT FK_PORTAIL_CAMPAGNE FOREIGN KEY (campagne_id) REFERENCES campagne_collecte (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE annee ADD portail_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE annee ADD CONSTRAINT FK_ANNEE_PORTAIL FOREIGN KEY (portail_id) REFERENCES portail (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_ANNEE_PORTAIL ON annee (portail_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annee DROP FOREIGN KEY FK_ANNEE_PORTAIL');
        $this->addSql('DROP INDEX IDX_ANNEE_PORTAIL ON annee');
        $this->addSql('ALTER TABLE annee DROP portail_id');
        $this->addSql('DROP TABLE portail');
    }
}

