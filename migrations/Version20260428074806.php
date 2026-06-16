<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428074806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE semestre RENAME INDEX fk_71688fbc37b87827 TO IDX_71688FBC37B87827');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE ue RENAME INDEX fk_2e490a9b5f8c9c69 TO IDX_2E490A9B5F8C9C69');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE semestre RENAME INDEX idx_71688fbc37b87827 TO FK_71688FBC37B87827');
        $this->addSql('ALTER TABLE ue RENAME INDEX idx_2e490a9b5f8c9c69 TO FK_2E490A9B5F8C9C69');
        $this->addSql('ALTER TABLE type_diplome CHANGE logo logo VARCHAR(127) DEFAULT NULL');
    }
}
