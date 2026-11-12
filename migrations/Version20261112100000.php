<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table tutoriel (vidéos tutoriel partageables sur /tutoriels)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tutoriel (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, slug VARCHAR(120) NOT NULL, youtube_url VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, duree VARCHAR(20) DEFAULT NULL, ordre INT NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_A2073AED989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tutoriel');
    }
}
