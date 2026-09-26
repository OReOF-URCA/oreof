<?php

declare(strict_types=1);

namespace App\Command\Migration;

use Doctrine\DBAL\Connection;

/**
 * Alignements non destructifs issus du delta réel V1 -> mapping V2.
 * Les suppressions de colonnes restent dans app:migrate:v2:cleanup.
 */
final class V2DoctrineAlignment
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function plan(): array
    {
        $sql = [];
        foreach ($this->changes() as $table => $definition) {
            if (!$this->tableExists($table)) {
                continue;
            }

            // Un dump Doctrine reflète une base précise. Les bases V1 réelles peuvent
            // ne pas avoir toutes les colonnes historiques : ne jamais faire échouer
            // tout un ALTER TABLE à cause d'une colonne absente.
            foreach ($this->splitChanges($definition) as $change) {
                if (!preg_match('/^CHANGE\\s+`?([a-zA-Z0-9_]+)`?\\s+/i', $change, $matches)) {
                    continue;
                }

                $column = $matches[1];
                if ($this->columnExists($table, $column)) {
                    $sql["Alignement Doctrine {$table}.{$column}"] = "ALTER TABLE `{$table}` {$change}";
                }
            }
        }

        // DpeFormation est un nouveau concept V2 : la V1 ne possède pas cette table.
        if (!$this->tableExists('dpe_formation')) {
            $sql['Création dpe_formation'] = <<<'SQL'
CREATE TABLE dpe_formation (
    id INT AUTO_INCREMENT NOT NULL,
    campagne_collecte_id INT DEFAULT NULL,
    formation_id INT DEFAULT NULL,
    etat_validation JSON NOT NULL,
    version VARCHAR(10) NOT NULL,
    created DATETIME NOT NULL,
    updated DATETIME NOT NULL,
    laissez_passer LONGTEXT DEFAULT NULL,
    INDEX IDX_DPE_FORMATION_CAMPAGNE (campagne_collecte_id),
    INDEX IDX_DPE_FORMATION_FORMATION (formation_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
SQL;
            $sql['FK dpe_formation.campagne_collecte'] = 'ALTER TABLE dpe_formation ADD CONSTRAINT FK_DPE_FORMATION_CAMPAGNE FOREIGN KEY (campagne_collecte_id) REFERENCES campagne_collecte (id)';
            $sql['FK dpe_formation.formation'] = 'ALTER TABLE dpe_formation ADD CONSTRAINT FK_DPE_FORMATION_FORMATION FOREIGN KEY (formation_id) REFERENCES formation (id)';
        } elseif (!$this->columnExists('dpe_formation', 'updated')) {
            // Compatibilité avec une base déjà passée par une première version
            // de la migration DpeFormation ne contenant que created.
            $sql['Ajout dpe_formation.updated'] = 'ALTER TABLE dpe_formation ADD updated DATETIME DEFAULT NULL';
            $sql['Initialisation dpe_formation.updated'] = 'UPDATE dpe_formation SET updated = COALESCE(created, NOW()) WHERE updated IS NULL';
            $sql['Finalisation dpe_formation.updated'] = 'ALTER TABLE dpe_formation CHANGE updated updated DATETIME NOT NULL';
        }

        if ($this->tableExists('historique') && !$this->columnExists('historique', 'dpe_formation_id')) {
            $sql['Ajout historique.dpe_formation_id'] = 'ALTER TABLE historique ADD dpe_formation_id INT DEFAULT NULL';
            $sql['Index historique.dpe_formation_id'] = 'CREATE INDEX IDX_HISTORIQUE_DPE_FORMATION ON historique (dpe_formation_id)';
            $sql['FK historique.dpe_formation'] = 'ALTER TABLE historique ADD CONSTRAINT FK_HISTORIQUE_DPE_FORMATION FOREIGN KEY (dpe_formation_id) REFERENCES dpe_formation (id)';
        }

        // Colonnes V2 historiquement ajoutées hors des migrations Doctrine.
        // Elles doivent être présentes sur toute base V1 migrée.
        if ($this->tableExists('type_diplome')) {
            if (!$this->columnExists('type_diplome', 'passage_cfvu')) {
                $sql['Ajout type_diplome.passage_cfvu'] = 'ALTER TABLE type_diplome ADD passage_cfvu BOOLEAN DEFAULT TRUE NOT NULL';
            }
            if (!$this->columnExists('type_diplome', 'logo')) {
                $sql['Ajout type_diplome.logo'] = 'ALTER TABLE type_diplome ADD logo JSON DEFAULT NULL';
            }
        }

        if ($this->tableExists('parcours')) {
            if (!$this->columnExists('parcours', 'maquette_pdf')) {
                $sql['Ajout parcours.maquette_pdf'] = 'ALTER TABLE parcours ADD maquette_pdf VARCHAR(255) DEFAULT NULL';
            }
            if (!$this->columnExists('parcours', 'maquette_pdf_nom_original')) {
                $sql['Ajout parcours.maquette_pdf_nom_original'] = 'ALTER TABLE parcours ADD maquette_pdf_nom_original VARCHAR(255) DEFAULT NULL';
            }
            if (!$this->columnExists('parcours', 'logo')) {
                $sql['Ajout parcours.logo'] = 'ALTER TABLE parcours ADD logo JSON DEFAULT NULL';
            }
        }

        if ($this->tableExists('timeline_date')) {
            if ($this->columnExists('timeline_date', 'icone')) {
                $iconMapping = [
                    'fa-bullhorn' => 'mdi:bullhorn-outline',
                    'fa-lock-open' => 'mdi:lock-open-outline',
                    'fa-shield-check' => 'mdi:shield-check-outline',
                    'fa-paper-plane' => 'mdi:paper-airplane-outline',
                    'fa-pencil' => 'mdi:pencil-outline',
                    'fa-hand' => 'mdi:hand-tap',
                ];

                foreach ($iconMapping as $legacyIcon => $uxIcon) {
                    $sql["Migration icône timeline {$legacyIcon}"] = sprintf(
                        "UPDATE timeline_date SET icone = '%s' WHERE icone = '%s'",
                        $uxIcon,
                        $legacyIcon
                    );
                }
            }

            if (!$this->columnExists('timeline_date', 'flag')) {
                $sql['Ajout timeline_date.flag'] = "ALTER TABLE timeline_date ADD flag VARCHAR(30) DEFAULT NULL";
            }
            if ($this->columnExists('timeline_date', 'is_cfvu')) {
                $sql['Reprise timeline_date.is_cfvu vers flag'] = "UPDATE timeline_date SET flag = CASE WHEN is_cfvu = 1 THEN 'cfvu' ELSE 'none' END WHERE flag IS NULL OR flag = ''";
            } else {
                $sql['Valeur timeline_date.flag par défaut'] = "UPDATE timeline_date SET flag = 'none' WHERE flag IS NULL OR flag = ''";
            }
            $sql['Finalisation timeline_date.flag'] = "ALTER TABLE timeline_date CHANGE flag flag VARCHAR(30) NOT NULL, CHANGE heure heure TIME DEFAULT NULL, CHANGE date_debut date_debut DATE DEFAULT NULL";
        }

        return $sql;
    }

    private function changes(): array
    {
        return [
            'annee' => "CHANGE code_apogee_etape_annee code_apogee_etape_annee VARCHAR(10) DEFAULT NULL, CHANGE code_apogee_etape_version code_apogee_etape_version VARCHAR(3) DEFAULT NULL",
            'historique' => "CHANGE date date DATETIME DEFAULT NULL, CHANGE complements complements JSON DEFAULT NULL",
            'type_ue' => "CHANGE type type VARCHAR(30) DEFAULT NULL",
            'document_conseil' => "CHANGE date_conseil date_conseil DATETIME DEFAULT NULL",
            'parcours_versioning' => "CHANGE version_timestamp version_timestamp DATETIME NOT NULL, CHANGE dto_file_name dto_file_name VARCHAR(255) DEFAULT NULL",
            'ville' => "CHANGE code_apogee code_apogee VARCHAR(1) DEFAULT NULL",
            'etablissement' => "CHANGE options options JSON NOT NULL, CHANGE numero_siret numero_siret VARCHAR(14) DEFAULT NULL, CHANGE numero_activite numero_activite VARCHAR(255) DEFAULT NULL, CHANGE email_central email_central VARCHAR(255) DEFAULT NULL, CHANGE email_oreof email_oreof VARCHAR(255) DEFAULT NULL",
            'adresse' => "CHANGE adresse1 adresse1 VARCHAR(255) DEFAULT NULL, CHANGE adresse2 adresse2 VARCHAR(255) DEFAULT NULL, CHANGE code_postal code_postal VARCHAR(30) DEFAULT NULL, CHANGE ville ville VARCHAR(100) DEFAULT NULL",
            'element_constitutif' => "CHANGE ects ects DOUBLE PRECISION DEFAULT NULL, CHANGE volume_cm_presentiel volume_cm_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_td_presentiel volume_td_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_tp_presentiel volume_tp_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_cm_distanciel volume_cm_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_td_distanciel volume_td_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_tp_distanciel volume_tp_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE texte_ec_libre texte_ec_libre VARCHAR(255) DEFAULT NULL, CHANGE libelle libelle VARCHAR(255) DEFAULT NULL, CHANGE type_mccc type_mccc VARCHAR(20) DEFAULT NULL, CHANGE etat_mccc etat_mccc VARCHAR(255) DEFAULT NULL, CHANGE volume_te volume_te DOUBLE PRECISION DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(10) DEFAULT NULL, CHANGE quitus_text quitus_text VARCHAR(1000) DEFAULT NULL, CHANGE validation_status validation_status VARCHAR(16) DEFAULT NULL, CHANGE validation_dirty validation_dirty TINYINT NOT NULL DEFAULT 1, CHANGE validation_updated_at validation_updated_at DATETIME DEFAULT NULL",
            'dpe_parcours' => "CHANGE etat_validation etat_validation JSON NOT NULL, CHANGE etat_reconduction etat_reconduction VARCHAR(255) DEFAULT NULL",
            'validation_issue' => "CHANGE message message VARCHAR(255) DEFAULT NULL, CHANGE payload payload JSON DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL",
            'ue' => "CHANGE libelle libelle VARCHAR(255) DEFAULT NULL, CHANGE ects ects DOUBLE PRECISION DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(10) DEFAULT NULL, CHANGE validation_status validation_status VARCHAR(16) DEFAULT NULL, CHANGE validation_dirty validation_dirty TINYINT NOT NULL DEFAULT 1, CHANGE validation_updated_at validation_updated_at DATETIME DEFAULT NULL",
            'plateforme_admission_parametre' => "CHANGE donnees_specifiques donnees_specifiques JSON DEFAULT NULL",
            'parcours_tab_state' => "CHANGE status status VARCHAR(10) DEFAULT 'red' NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE issues issues JSON DEFAULT NULL",
            'parcours' => "CHANGE nb_heures_stages nb_heures_stages DOUBLE PRECISION DEFAULT NULL, CHANGE nb_heures_projet nb_heures_projet DOUBLE PRECISION DEFAULT NULL, CHANGE codes_rome codes_rome JSON DEFAULT NULL, CHANGE regime_inscription regime_inscription JSON DEFAULT NULL, CHANGE nb_heures_situation_pro nb_heures_situation_pro DOUBLE PRECISION DEFAULT NULL, CHANGE sigle sigle VARCHAR(15) DEFAULT NULL, CHANGE maquette_pdf maquette_pdf VARCHAR(255) DEFAULT NULL, CHANGE maquette_pdf_nom_original maquette_pdf_nom_original VARCHAR(255) DEFAULT NULL, CHANGE etat_steps etat_steps JSON NOT NULL, CHANGE etat_parcours etat_parcours JSON DEFAULT NULL, CHANGE remplissage remplissage JSON DEFAULT NULL, CHANGE etats_fiches_matieres etats_fiches_matieres JSON DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(1) DEFAULT NULL, CHANGE code_rncp code_rncp VARCHAR(10) DEFAULT NULL, CHANGE type_parcours type_parcours VARCHAR(20) DEFAULT NULL, CHANGE code_apogee_numero_version code_apogee_numero_version VARCHAR(1) DEFAULT NULL, CHANGE code_mention_apogee code_mention_apogee VARCHAR(1) DEFAULT NULL, CHANGE duree_parcours duree_parcours DOUBLE PRECISION DEFAULT NULL, CHANGE duree_parcours_unite duree_parcours_unite VARCHAR(20) DEFAULT NULL, CHANGE logo logo JSON DEFAULT NULL",
            'nature_ue_ec' => "CHANGE description_courte description_courte VARCHAR(255) DEFAULT NULL, CHANGE icone icone VARCHAR(50) DEFAULT NULL",
            'formation_tab_state' => "CHANGE status status VARCHAR(10) DEFAULT 'red' NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE issues issues JSON DEFAULT NULL",
            'formation_versioning' => "CHANGE version_timestamp version_timestamp DATETIME NOT NULL",
            'profil' => "CHANGE code code VARCHAR(100) DEFAULT NULL, CHANGE centre centre VARCHAR(255) DEFAULT NULL",
            'user' => "CHANGE roles roles JSON NOT NULL, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE date_valide_dpe date_valide_dpe DATETIME DEFAULT NULL, CHANGE date_valide_administration date_valide_administration DATETIME DEFAULT NULL, CHANGE date_demande date_demande DATETIME DEFAULT NULL, CHANGE civilite civilite VARCHAR(50) DEFAULT NULL, CHANGE tel_fixe tel_fixe VARCHAR(10) DEFAULT NULL, CHANGE tel_portable tel_portable VARCHAR(10) DEFAULT NULL, CHANGE service_demande service_demande VARCHAR(255) DEFAULT NULL",
            'type_diplome_plateforme_admission' => "CHANGE annees annees JSON DEFAULT NULL, CHANGE annees_capacite_requise annees_capacite_requise JSON DEFAULT NULL",
            'dpe_demande' => "CHANGE date_cloture date_cloture DATETIME DEFAULT NULL",
            'volume_horaire_parcours' => "CHANGE volumes_annee volumes_annee JSON DEFAULT NULL, CHANGE volumes_semestre volumes_semestre JSON DEFAULT NULL",
            'contact' => "CHANGE nom nom VARCHAR(50) DEFAULT NULL, CHANGE prenom prenom VARCHAR(50) DEFAULT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL",
            'type_ec' => "CHANGE type type VARCHAR(30) DEFAULT NULL",
            'plateforme_admission' => "CHANGE configuration configuration JSON NOT NULL, CHANGE definition_champs definition_champs JSON NOT NULL, CHANGE color color VARCHAR(15) DEFAULT NULL, CHANGE mode_export mode_export VARCHAR(30) DEFAULT 'global' NOT NULL",
            'formation_demande' => "CHANGE date_validation_dpe date_validation_dpe DATETIME DEFAULT NULL, CHANGE mention_texte mention_texte VARCHAR(255) DEFAULT NULL",
            'mccc' => "CHANGE type_epreuve type_epreuve JSON DEFAULT NULL, CHANGE duree duree TIME DEFAULT NULL, CHANGE options options JSON DEFAULT NULL",
            'mention' => "CHANGE sigle sigle VARCHAR(20) DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(1) DEFAULT NULL",
            'help' => "CHANGE title title VARCHAR(255) DEFAULT NULL, CHANGE centres_show centres_show JSON NOT NULL",
            'but_niveau' => "CHANGE annee annee VARCHAR(10) DEFAULT NULL",
            'fiche_matiere' => "CHANGE libelle_anglais libelle_anglais VARCHAR(250) DEFAULT NULL, CHANGE etat_steps etat_steps JSON NOT NULL, CHANGE sigle sigle VARCHAR(255) DEFAULT NULL, CHANGE type_matiere type_matiere VARCHAR(20) DEFAULT NULL, CHANGE volume_cm_presentiel volume_cm_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_td_presentiel volume_td_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_tp_presentiel volume_tp_presentiel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_te volume_te DOUBLE PRECISION DEFAULT NULL, CHANGE etat_mccc etat_mccc VARCHAR(255) DEFAULT NULL, CHANGE volume_cm_distanciel volume_cm_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_td_distanciel volume_td_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE volume_tp_distanciel volume_tp_distanciel DOUBLE PRECISION DEFAULT NULL, CHANGE type_mccc type_mccc VARCHAR(10) DEFAULT NULL, CHANGE ects ects DOUBLE PRECISION DEFAULT NULL, CHANGE etat_fiche etat_fiche JSON DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(10) DEFAULT NULL, CHANGE remplissage remplissage JSON DEFAULT NULL, CHANGE type_apogee type_apogee VARCHAR(4) DEFAULT NULL, CHANGE quitus_text quitus_text VARCHAR(1000) DEFAULT NULL",
            'email_template' => "CHANGE subjects subjects JSON DEFAULT '[]' NOT NULL, CHANGE available_variables available_variables JSON NOT NULL",
            'generation_job' => "CHANGE parameters parameters JSON DEFAULT NULL, CHANGE started_at started_at DATETIME DEFAULT NULL, CHANGE finished_at finished_at DATETIME DEFAULT NULL, CHANGE result_path result_path VARCHAR(255) DEFAULT NULL, CHANGE result_format result_format VARCHAR(50) DEFAULT NULL",
            'but_competence' => "CHANGE nom_court nom_court VARCHAR(50) DEFAULT NULL, CHANGE situations situations JSON DEFAULT NULL, CHANGE composantes composantes JSON DEFAULT NULL",
            'help_image' => "CHANGE date_creation date_creation DATETIME NOT NULL",
            'faq' => "CHANGE centres_show centres_show JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL",
            'change_parcours' => "CHANGE payload payload JSON NOT NULL, CHANGE date_approuved date_approuved DATETIME DEFAULT NULL, CHANGE action_type action_type LONGTEXT NOT NULL, CHANGE action_status action_status LONGTEXT NOT NULL",
            'semestre' => "CHANGE code_apogee code_apogee VARCHAR(10) DEFAULT NULL, CHANGE validation_status validation_status VARCHAR(16) DEFAULT NULL, CHANGE validation_dirty validation_dirty TINYINT NOT NULL DEFAULT 1, CHANGE validation_updated_at validation_updated_at DATETIME DEFAULT NULL",
            'type_diplome' => "CHANGE libelle_court libelle_court VARCHAR(50) DEFAULT NULL, CHANGE logo logo JSON DEFAULT NULL",
            'formation' => "CHANGE mention_texte mention_texte VARCHAR(255) DEFAULT NULL, CHANGE code_rncp code_rncp VARCHAR(10) DEFAULT NULL, CHANGE regime_inscription regime_inscription JSON DEFAULT NULL, CHANGE structure_semestres structure_semestres JSON DEFAULT NULL, CHANGE etat_dpe etat_dpe JSON DEFAULT NULL, CHANGE etat_steps etat_steps JSON NOT NULL, CHANGE sigle sigle VARCHAR(255) DEFAULT NULL, CHANGE remplissage remplissage JSON DEFAULT NULL, CHANGE code_mention_apogee code_mention_apogee VARCHAR(1) DEFAULT NULL, CHANGE etat_reconduction etat_reconduction VARCHAR(255) DEFAULT NULL",
            'composante' => "CHANGE tel_standard tel_standard VARCHAR(10) DEFAULT NULL, CHANGE tel_complementaire tel_complementaire VARCHAR(10) DEFAULT NULL, CHANGE mail_contact mail_contact VARCHAR(255) DEFAULT NULL, CHANGE url_site url_site VARCHAR(255) DEFAULT NULL, CHANGE etat_composante etat_composante JSON DEFAULT NULL, CHANGE sigle sigle VARCHAR(20) DEFAULT NULL, CHANGE code_composante code_composante VARCHAR(3) DEFAULT NULL, CHANGE code_apogee code_apogee VARCHAR(2) DEFAULT NULL, CHANGE plaquette_rubriques plaquette_rubriques JSON DEFAULT NULL, CHANGE header_plaquette header_plaquette VARCHAR(255) DEFAULT NULL, CHANGE footer_plaquette footer_plaquette VARCHAR(255) DEFAULT NULL",
            'workflow_role_notification_config' => "CHANGE destinataires destinataires JSON NOT NULL, CHANGE copies copies JSON NOT NULL",
            'fiche_matiere_versioning' => "CHANGE version_timestamp version_timestamp DATETIME DEFAULT NULL",
            'user_workflow_notification_setting' => "CHANGE step step VARCHAR(150) DEFAULT NULL, CHANGE transition_name transition_name VARCHAR(150) DEFAULT NULL",
            'type_epreuve' => "CHANGE sigle sigle VARCHAR(20) DEFAULT NULL",
            'notification' => "CHANGE payload payload JSON DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL",
            'fiche_matiere_tab_state' => "CHANGE status status VARCHAR(10) DEFAULT 'red' NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE issues issues JSON DEFAULT NULL",
            'change_rf' => "CHANGE etat_demande etat_demande JSON DEFAULT NULL, CHANGE date_validation_cfvu date_validation_cfvu DATETIME DEFAULT NULL, CHANGE fichier_pv fichier_pv VARCHAR(50) DEFAULT NULL, CHANGE date_prise_fonction date_prise_fonction DATETIME DEFAULT NULL",
            'messenger_messages' => "CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL",
        ];
    }

    private function splitChanges(string $definition): array
    {
        // Les définitions actuelles ne contiennent pas de virgules dans les types.
        // Garder ce parsing local au format contrôlé de changes().
        $parts = explode(', CHANGE ', $definition);
        foreach ($parts as $index => $part) {
            $parts[$index] = 0 === $index ? trim($part) : 'CHANGE '.trim($part);
        }

        return array_values(array_filter($parts, static fn (string $change): bool => $change !== ''));
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}
