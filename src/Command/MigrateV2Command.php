<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate:v2', description: 'Migre une base ORéOF main vers V2 sans suppression destructive.')]
final class MigrateV2Command extends Command
{
    private const TRACKING_TABLE = 'app_v2_migration';

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Applique les changements (dry-run par défaut).')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Effectue uniquement les contrôles post-migration.')
            ->addOption('step', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Étape(s) à exécuter.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Rejoue une étape déjà enregistrée.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $io->title('ORéOF — migration BDD main → V2');

        try {
            $this->connection->connect();
            $io->writeln('<info>✓</info> Connexion BDD');
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ((bool) $input->getOption('check')) {
            return $this->check($io);
        }

        if (!$apply) {
            $io->note('Dry-run actif. Ajouter --apply pour écrire en base.');
        } else {
            $this->ensureTrackingTable();
        }

        $selected = array_map('strval', (array) $input->getOption('step'));
        $force = (bool) $input->getOption('force');
        $errors = 0;

        foreach ($this->steps() as $key => $step) {
            if ($selected !== [] && !in_array($key, $selected, true)) {
                continue;
            }

            $io->section($key.' — '.$step['label']);

            if ($apply && !$force && $this->isApplied($key)) {
                $io->writeln('<comment>Déjà appliquée, ignorée.</comment>');
                continue;
            }

            try {
                $plan = ($step['plan'])();
                if ($plan === []) {
                    $io->writeln('<info>✓</info> Rien à faire.');
                }

                foreach ($plan as $description => $sql) {
                    $io->writeln(' • '.$description);
                    if ($apply) {
                        $this->connection->executeStatement($sql);
                    }
                }

                if ($apply) {
                    $this->record($key, 'applied', count($plan).' statement(s)');
                }
            } catch (\Throwable $e) {
                ++$errors;
                $io->error($e->getMessage());
                if ($apply) {
                    $this->record($key, 'failed', mb_substr($e->getMessage(), 0, 60000));
                }
            }
        }

        if ($errors > 0) {
            return Command::FAILURE;
        }

        if (!$apply) {
            $io->success('Dry-run terminé.');
            return Command::SUCCESS;
        }

        return $this->check($io);
    }

    private function steps(): array
    {
        return [
            '010_documented_schema' => ['label' => 'Update_BDD*.md', 'plan' => fn () => $this->documentedSchema()],
            '020_validation_schema' => ['label' => 'Validation et états des onglets', 'plan' => fn () => $this->validationSchema()],
            '030_admission_years' => ['label' => 'Années des plateformes d’admission', 'plan' => fn () => $this->admissionYears()],
            '100_safe_defaults' => ['label' => 'Valeurs V2 déterministes', 'plan' => fn () => $this->safeDefaults()],
        ];
    }

    private function documentedSchema(): array
    {
        $sql = [];
        $this->addColumn($sql, 'dpe_demande', 'created', 'DATETIME NULL');
        $this->addColumn($sql, 'dpe_demande', 'updated', 'DATETIME NULL');
        $this->addColumn($sql, 'dpe_demande', 'date_cloture', 'DATETIME DEFAULT NULL');
        $this->addColumn($sql, 'dpe_demande', 'auteur_id', 'INT DEFAULT NULL');
        $this->addColumn($sql, 'fiche_matiere', 'quitus', 'TINYINT(1) DEFAULT NULL');
        $this->addColumn($sql, 'plateforme_admission', 'mode_export', "VARCHAR(30) NOT NULL DEFAULT 'global'");
        $this->addColumn($sql, 'plateforme_admission_parametre', 'remarques', 'LONGTEXT DEFAULT NULL');
        $this->addColumn($sql, 'etablissement', 'email_oreof', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumn($sql, 'parcours', 'duree_parcours', 'DOUBLE PRECISION DEFAULT NULL');
        $this->addColumn($sql, 'parcours', 'duree_parcours_unite', 'VARCHAR(20) DEFAULT NULL');
        $this->addColumn($sql, 'type_diplome', 'classique', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->addColumn($sql, 'type_diplome', 'has_ects', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->addColumn($sql, 'type_diplome', 'nb_ects_par_semestre', 'INT DEFAULT 30');

        $this->modifyIfPresent($sql, 'formation', 'niveau_entree', 'INT DEFAULT NULL');
        $this->modifyIfPresent($sql, 'formation', 'niveau_sortie', 'INT DEFAULT NULL');
        $this->modifyIfPresent($sql, 'type_diplome', 'semestre_debut', 'INT DEFAULT NULL');
        $this->modifyIfPresent($sql, 'type_diplome', 'semestre_fin', 'INT DEFAULT NULL');
        $this->modifyIfPresent($sql, 'type_diplome', 'debut_semestre_flexible', 'TINYINT(1) DEFAULT NULL');
        $this->modifyIfPresent($sql, 'etablissement', 'email_central', 'VARCHAR(255) DEFAULT NULL');

        return $sql;
    }

    private function validationSchema(): array
    {
        $sql = [];
        foreach (['element_constitutif', 'semestre', 'ue'] as $table) {
            $this->addColumn($sql, $table, 'validation_status', 'VARCHAR(16) DEFAULT NULL');
            $this->addColumn($sql, $table, 'validation_dirty', 'TINYINT(1) NOT NULL DEFAULT 0');
            $this->addColumn($sql, $table, 'validation_updated_at', 'DATETIME DEFAULT NULL');
        }
        $this->addColumn($sql, 'nature_ue_ec', 'description_courte', 'VARCHAR(255) DEFAULT NULL');
        $this->addColumn($sql, 'nature_ue_ec', 'icone', 'VARCHAR(50) DEFAULT NULL');
        $this->addColumn($sql, 'parcours', 'semestre_debut', 'INT DEFAULT NULL');
        $this->addColumn($sql, 'parcours', 'semestre_fin', 'INT DEFAULT NULL');
        $this->addColumn($sql, 'semestre', 'last_modification', 'DATETIME DEFAULT NULL');
        $this->addColumn($sql, 'change_rf', 'date_prise_fonction', 'DATETIME DEFAULT NULL');

        $tables = [
            'fiche_matiere_tab_state' => "CREATE TABLE fiche_matiere_tab_state (id INT AUTO_INCREMENT NOT NULL, fiche_matiere_id INT NOT NULL, tab_key VARCHAR(30) NOT NULL, done TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(10) NOT NULL DEFAULT 'red', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', issues JSON DEFAULT NULL, INDEX IDX_FMTAB_FM (fiche_matiere_id), UNIQUE INDEX UNIQ_FMTAB (fiche_matiere_id, tab_key), PRIMARY KEY(id), CONSTRAINT FK_FMTAB_FM FOREIGN KEY (fiche_matiere_id) REFERENCES fiche_matiere (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'formation_tab_state' => "CREATE TABLE formation_tab_state (id INT AUTO_INCREMENT NOT NULL, formation_id INT NOT NULL, tab_key VARCHAR(30) NOT NULL, done TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(10) NOT NULL DEFAULT 'red', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', issues JSON DEFAULT NULL, INDEX IDX_FTAB_FORMATION (formation_id), UNIQUE INDEX UNIQ_FTAB (formation_id, tab_key), PRIMARY KEY(id), CONSTRAINT FK_FTAB_FORMATION FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'parcours_tab_state' => "CREATE TABLE parcours_tab_state (id INT AUTO_INCREMENT NOT NULL, parcours_id INT NOT NULL, tab_key VARCHAR(30) NOT NULL, done TINYINT(1) NOT NULL DEFAULT 0, status VARCHAR(10) NOT NULL DEFAULT 'red', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', issues JSON DEFAULT NULL, INDEX IDX_PTAB_PARCOURS (parcours_id), UNIQUE INDEX UNIQ_PTAB (parcours_id, tab_key), PRIMARY KEY(id), CONSTRAINT FK_PTAB_PARCOURS FOREIGN KEY (parcours_id) REFERENCES parcours (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'validation_issue' => "CREATE TABLE validation_issue (id INT AUTO_INCREMENT NOT NULL, semestre_id INT DEFAULT NULL, scope_type VARCHAR(255) NOT NULL, scope_id INT NOT NULL, rule_code VARCHAR(255) NOT NULL, severity VARCHAR(15) NOT NULL, message VARCHAR(255) DEFAULT NULL, payload JSON DEFAULT NULL, type_diplome VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_VALIDATION_SEMESTRE (semestre_id), PRIMARY KEY(id), CONSTRAINT FK_VALIDATION_SEMESTRE FOREIGN KEY (semestre_id) REFERENCES semestre (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'volume_horaire_parcours' => "CREATE TABLE volume_horaire_parcours (id INT AUTO_INCREMENT NOT NULL, parcours_id INT NOT NULL, campagne_collecte_id INT NOT NULL, heures_cm_pres DOUBLE PRECISION NOT NULL, heures_td_pres DOUBLE PRECISION NOT NULL, heures_tp_pres DOUBLE PRECISION NOT NULL, heures_te_pres DOUBLE PRECISION NOT NULL, heures_cm_dist DOUBLE PRECISION NOT NULL, heures_td_dist DOUBLE PRECISION NOT NULL, heures_tp_dist DOUBLE PRECISION NOT NULL, volumes_annee JSON DEFAULT NULL, volumes_semestre JSON DEFAULT NULL, date_calcul DATETIME NOT NULL, INDEX IDX_VHP_PARCOURS (parcours_id), INDEX IDX_VHP_CAMPAGNE (campagne_collecte_id), UNIQUE INDEX UNIQ_VOLUME_HORAIRE_PARCOURS_CAMPAGNE (parcours_id, campagne_collecte_id), PRIMARY KEY(id), CONSTRAINT FK_VHP_PARCOURS FOREIGN KEY (parcours_id) REFERENCES parcours (id), CONSTRAINT FK_VHP_CAMPAGNE FOREIGN KEY (campagne_collecte_id) REFERENCES campagne_collecte (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
        ];
        foreach ($tables as $table => $statement) {
            if (!$this->tableExists($table)) {
                $sql['Création '.$table] = $statement;
            }
        }

        return $sql;
    }

    private function admissionYears(): array
    {
        $sql = [];
        $this->addColumn($sql, 'type_diplome_plateforme_admission', 'annees', "JSON NULL COMMENT 'Années concernées par la plateforme (ex: [1, 2, 3])'");
        return $sql;
    }

    private function safeDefaults(): array
    {
        $sql = [];
        if ($this->columnExists('plateforme_admission', 'mode_export')) {
            $sql['plateforme_admission.mode_export vide → global'] = "UPDATE plateforme_admission SET mode_export = 'global' WHERE mode_export IS NULL OR mode_export = ''";
        }
        foreach (['semestre', 'ue', 'element_constitutif'] as $table) {
            if ($this->columnExists($table, 'validation_status')) {
                $sql[$table.'.validation_status NULL → incomplete'] = "UPDATE {$table} SET validation_status = 'incomplete' WHERE validation_status IS NULL";
            }
        }
        if ($this->columnExists('semestre', 'last_modification')) {
            $sql['semestre.last_modification NULL → NOW()'] = 'UPDATE semestre SET last_modification = NOW() WHERE last_modification IS NULL';
        }
        if ($this->columnExists('dpe_demande', 'created')) {
            $sql['dpe_demande.created → date_demande'] = 'UPDATE dpe_demande SET created = COALESCE(date_demande, NOW()) WHERE created IS NULL';
        }
        if ($this->columnExists('dpe_demande', 'updated')) {
            $sql['dpe_demande.updated → created/date_demande'] = 'UPDATE dpe_demande SET updated = COALESCE(created, date_demande, NOW()) WHERE updated IS NULL';
        }
        return $sql;
    }

    private function check(SymfonyStyle $io): int
    {
        $io->section('Contrôles post-migration');
        $errors = [];
        $warnings = [];

        foreach ([['plateforme_admission','mode_export'], ['parcours','duree_parcours'], ['type_diplome','has_ects'], ['type_diplome_plateforme_admission','annees'], ['semestre','validation_status'], ['ue','validation_status'], ['element_constitutif','validation_status']] as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $errors[] = "Colonne manquante : {$table}.{$column}";
            }
        }
        foreach (['fiche_matiere_tab_state', 'formation_tab_state', 'parcours_tab_state', 'validation_issue', 'volume_horaire_parcours'] as $table) {
            if (!$this->tableExists($table)) {
                $errors[] = 'Table manquante : '.$table;
            }
        }

        if ($this->columnExists('plateforme_admission', 'mode_export')) {
            $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM plateforme_admission WHERE mode_export IS NULL OR mode_export NOT IN ('global','par_diplome')");
            if ($count > 0) {
                $errors[] = "{$count} plateforme(s) avec mode_export invalide.";
            }
        }
        if ($this->columnExists('type_diplome_plateforme_admission', 'annees')) {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM type_diplome_plateforme_admission WHERE annees IS NULL');
            if ($count > 0) {
                $warnings[] = "{$count} association(s) type diplôme/plateforme sans années : règle métier à définir.";
            }
        }
        if ($this->columnExists('dpe_demande', 'auteur_id')) {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM dpe_demande WHERE auteur_id IS NULL');
            if ($count > 0) {
                $warnings[] = "{$count} demande(s) DPE sans auteur : aucune valeur inventée.";
            }
        }

        foreach ($warnings as $message) {
            $io->warning($message);
        }
        foreach ($errors as $message) {
            $io->error($message);
        }

        if ($errors !== []) {
            return Command::FAILURE;
        }

        $io->success($warnings === [] ? 'Contrôles V2 OK.' : 'Structure V2 OK ; décisions métier encore nécessaires.');
        return Command::SUCCESS;
    }

    private function addColumn(array &$plan, string $table, string $column, string $definition): void
    {
        if ($this->tableExists($table) && !$this->columnExists($table, $column)) {
            $plan["Ajout {$table}.{$column}"] = "ALTER TABLE {$table} ADD {$column} {$definition}";
        }
    }

    private function modifyIfPresent(array &$plan, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            $plan["Modification {$table}.{$column}"] = "ALTER TABLE {$table} MODIFY {$column} {$definition}";
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
    }

    private function ensureTrackingTable(): void
    {
        $this->connection->executeStatement("CREATE TABLE IF NOT EXISTS ".self::TRACKING_TABLE." (id INT AUTO_INCREMENT NOT NULL, migration_key VARCHAR(100) NOT NULL, status VARCHAR(20) NOT NULL, details LONGTEXT DEFAULT NULL, applied_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_APP_V2_MIGRATION_KEY (migration_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    private function isApplied(string $key): bool
    {
        return 'applied' === $this->connection->fetchOne('SELECT status FROM '.self::TRACKING_TABLE.' WHERE migration_key = ?', [$key]);
    }

    private function record(string $key, string $status, string $details): void
    {
        $this->connection->executeStatement(
            'INSERT INTO '.self::TRACKING_TABLE.' (migration_key,status,details,applied_at) VALUES (?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status), details=VALUES(details), applied_at=VALUES(applied_at)',
            [$key, $status, $details]
        );
    }
}
