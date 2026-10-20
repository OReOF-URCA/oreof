<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Socle des référentiels administrables et valeurs compatibles Enum'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE referentiel (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(100) NOT NULL, libelle VARCHAR(255) NOT NULL, administrable TINYINT(1) NOT NULL, extensible TINYINT(1) NOT NULL, UNIQUE INDEX uniq_referentiel_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql("CREATE TABLE referentiel_valeur (id INT AUTO_INCREMENT NOT NULL, referentiel_id INT NOT NULL, code VARCHAR(100) NOT NULL, libelle VARCHAR(255) NOT NULL, legacy_value VARCHAR(255) DEFAULT NULL, ordre INT NOT NULL, actif TINYINT(1) NOT NULL, systeme TINYINT(1) NOT NULL, config JSON NOT NULL, INDEX IDX_REF_VALEUR_REF (referentiel_id), UNIQUE INDEX uniq_referentiel_valeur_code (referentiel_id, code), UNIQUE INDEX uniq_referentiel_valeur_legacy (referentiel_id, legacy_value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE referentiel_valeur ADD CONSTRAINT FK_REF_VALEUR_REF FOREIGN KEY (referentiel_id) REFERENCES referentiel (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE referentiel_valeur');
        $this->addSql('DROP TABLE referentiel');
    }
}
