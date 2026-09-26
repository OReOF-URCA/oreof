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

    private bool $fullDryRun = false;

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
        $this->fullDryRun = !$apply && [] === $selected;
        $force = (bool) $input->getOption('force');
        $errors = 0;

        if ($apply && $selected !== []) {
            $dependencyErrors = $this->validateSelectedStepDependencies($selected);
            if ($dependencyErrors !== []) {
                foreach ($dependencyErrors as $message) {
                    $io->error($message);
                }
                return Command::FAILURE;
            }
        }

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

        if ($selected !== []) {
            $io->success('Étape(s) sélectionnée(s) appliquée(s). Exécuter app:migrate:v2 --check après la migration complète.');
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
            '040_new_v2_tables' => ['label' => 'Nouvelles structures fonctionnelles V2', 'plan' => fn () => $this->newV2Tables()],
            '050_history_documents' => ['label' => 'Liaisons historiques vers les documents de conseil', 'plan' => fn () => $this->historyDocuments()],
            '090_reconcile_schema' => ['label' => 'Réconciliation des contraintes V2', 'plan' => fn () => $this->reconcileSchema()],
            '100_safe_defaults' => ['label' => 'Valeurs V2 déterministes', 'plan' => fn () => $this->safeDefaults()],
            '110_finalize_constraints' => ['label' => 'Contraintes NOT NULL après reprise', 'plan' => fn () => $this->finalizeConstraints()],
        ];
    }

    private function validateSelectedStepDependencies(array $selected): array
    {
        $dependencies = [
            '050_history_documents' => ['040_new_v2_tables'],
            '090_reconcile_schema' => ['010_documented_schema', '020_validation_schema'],
            '100_safe_defaults' => ['010_documented_schema', '020_validation_schema'],
            '110_finalize_constraints' => ['100_safe_defaults'],
        ];

        $errors = [];
        $stepOrder = array_keys($this->steps());
        foreach ($selected as $step) {
            if (!in_array($step, $stepOrder, true)) {
                $errors[] = "Étape inconnue : {$step}.";
                continue;
            }

            foreach ($dependencies[$step] ?? [] as $dependency) {
                $selectedBefore = in_array($dependency, $selected, true)
                    && array_search($dependency, $stepOrder, true) < array_search($step, $stepOrder, true);

                if (!$this->isApplied($dependency) && !$selectedBefore) {
                    $errors[] = "L'étape {$step} nécessite {$dependency}, qui n'est ni déjà appliquée ni sélectionnée avant elle.";
                }
            }
        }

        return $errors;
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
            $this->addColumn($sql, $table, 'validation_dirty', 'TINYINT(1) NOT NULL DEFAULT 1');
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
            'validation_issue' => "CREATE TABLE validation_issue (id INT AUTO_INCREMENT NOT NULL, semestre_id INT DEFAULT NULL, scope_type VARCHAR(255) NOT NULL, scope_id INT NOT NULL, rule_code VARCHAR(255) NOT NULL, severity VARCHAR(15) NOT NULL, message VARCHAR(255) DEFAULT NULL, payload JSON DEFAULT NULL, type_diplome VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_VALIDATION_SEMESTRE (semestre_id), PRIMARY KEY(id), CONSTRAINT FK_VALIDATION_SEMESTRE FOREIGN KEY (semestre_id) REFERENCES semestre (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
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
        $this->addColumn($sql, 'type_diplome_plateforme_admission', 'annees_capacite_requise', "JSON DEFAULT NULL COMMENT 'Années pour lesquelles une capacité est requise'");
        return $sql;
    }

    private function newV2Tables(): array
    {
        $sql = [];
        $tables = [
            'faq' => "CREATE TABLE faq (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(500) NOT NULL, reponse LONGTEXT NOT NULL, is_active TINYINT(1) NOT NULL, centres_show JSON NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ordre INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'help' => "CREATE TABLE help (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, content LONGTEXT DEFAULT NULL, route_slug VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, centres_show JSON NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'help_image' => "CREATE TABLE help_image (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, fichier VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
            'document_conseil' => "CREATE TABLE document_conseil (id INT AUTO_INCREMENT NOT NULL, uploaded_by_id INT DEFAULT NULL, composante_id INT DEFAULT NULL, type VARCHAR(30) NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, date_conseil DATETIME DEFAULT NULL, uploaded_at DATETIME NOT NULL, commentaire LONGTEXT DEFAULT NULL, INDEX IDX_DOCUMENT_CONSEIL_USER (uploaded_by_id), INDEX IDX_DOCUMENT_CONSEIL_COMPOSANTE (composante_id), PRIMARY KEY(id), CONSTRAINT FK_DOCUMENT_CONSEIL_USER FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id), CONSTRAINT FK_DOCUMENT_CONSEIL_COMPOSANTE FOREIGN KEY (composante_id) REFERENCES composante (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB",
        ];
        foreach ($tables as $table => $statement) {
            if (!$this->tableExists($table)) {
                $sql['Création '.$table] = $statement;
            }
        }

        if (!$this->tableExists('document_conseil_formation')) {
            $sql['Création document_conseil_formation'] = "CREATE TABLE document_conseil_formation (document_conseil_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_DCF_DOCUMENT (document_conseil_id), INDEX IDX_DCF_FORMATION (formation_id), PRIMARY KEY(document_conseil_id, formation_id), CONSTRAINT FK_DCF_DOCUMENT FOREIGN KEY (document_conseil_id) REFERENCES document_conseil (id) ON DELETE CASCADE, CONSTRAINT FK_DCF_FORMATION FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB";
        }

        return $sql;
    }

    private function historyDocuments(): array
    {
        $sql = [];
        $this->addColumn($sql, 'historique_formation', 'document_pv_id', 'INT DEFAULT NULL');
        $this->addColumn($sql, 'historique_formation', 'document_note_id', 'INT DEFAULT NULL');

        if ($this->tableExists('historique_formation')) {
            if ($this->fullDryRun || !$this->indexExists('historique_formation', 'document_pv_id')) {
                $sql['Index historique_formation.document_pv_id'] = 'CREATE INDEX IDX_HISTORIQUE_DOCUMENT_PV ON historique_formation (document_pv_id)';
            }
            if ($this->fullDryRun || !$this->foreignKeyExists('historique_formation', 'document_pv_id', 'document_conseil')) {
                $sql['FK historique_formation.document_pv_id'] = 'ALTER TABLE historique_formation ADD CONSTRAINT FK_HISTORIQUE_DOCUMENT_PV FOREIGN KEY (document_pv_id) REFERENCES document_conseil (id)';
            }
        }
        if ($this->tableExists('historique_formation')) {
            if ($this->fullDryRun || !$this->indexExists('historique_formation', 'document_note_id')) {
                $sql['Index historique_formation.document_note_id'] = 'CREATE INDEX IDX_HISTORIQUE_DOCUMENT_NOTE ON historique_formation (document_note_id)';
            }
            if ($this->fullDryRun || !$this->foreignKeyExists('historique_formation', 'document_note_id', 'document_conseil')) {
                $sql['FK historique_formation.document_note_id'] = 'ALTER TABLE historique_formation ADD CONSTRAINT FK_HISTORIQUE_DOCUMENT_NOTE FOREIGN KEY (document_note_id) REFERENCES document_conseil (id)';
            }
        }

        return $sql;
    }

    private function reconcileSchema(): array
    {
        $sql = [];

        // Update_BDD.md prévoit cette relation ; elle est nullable dans le mapping Doctrine.
        if ($this->tableExists('dpe_demande')) {
            if ($this->fullDryRun || !$this->indexExists('dpe_demande', 'auteur_id')) {
                $sql['Index dpe_demande.auteur_id'] = 'CREATE INDEX IDX_DPE_DEMANDE_AUTEUR ON dpe_demande (auteur_id)';
            }
            if ($this->fullDryRun || !$this->foreignKeyExists('dpe_demande', 'auteur_id', 'user')) {
                $sql['FK dpe_demande.auteur_id → user.id'] = 'ALTER TABLE dpe_demande ADD CONSTRAINT FK_DPE_DEMANDE_AUTEUR FOREIGN KEY (auteur_id) REFERENCES `user` (id)';
            }
        }

        // Les premiers scripts V2 créaient les tab states sans leurs contraintes d'unicité.
        foreach ([
            ['fiche_matiere_tab_state', 'fiche_matiere_id', 'tab_key', 'UNIQ_FMTAB'],
            ['formation_tab_state', 'formation_id', 'tab_key', 'formation_tab_unique'],
            ['parcours_tab_state', 'parcours_id', 'tab_key', 'UNIQ_PTAB'],
            ['volume_horaire_parcours', 'parcours_id', 'campagne_collecte_id', 'UNIQ_VOLUME_HORAIRE_PARCOURS_CAMPAGNE'],
        ] as [$table, $firstColumn, $secondColumn, $indexName]) {
            if ($this->tableExists($table) && !$this->uniqueIndexExists($table, [$firstColumn, $secondColumn])) {
                $duplicates = (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} GROUP BY {$firstColumn}, {$secondColumn} HAVING COUNT(*) > 1) duplicates"
                );
                if (0 === $duplicates) {
                    $sql["Unicité {$table}({$firstColumn},{$secondColumn})"] =
                        "CREATE UNIQUE INDEX {$indexName} ON {$table} ({$firstColumn}, {$secondColumn})";
                }
            }
        }

        // Répare les relations absentes lorsque les anciennes tables V2 existent déjà.
        foreach ([
            ['fiche_matiere_tab_state', 'fiche_matiere_id', 'fiche_matiere', 'FK_FMTAB_FM', true],
            ['formation_tab_state', 'formation_id', 'formation', 'FK_FTAB_FORMATION', true],
            ['parcours_tab_state', 'parcours_id', 'parcours', 'FK_PTAB_PARCOURS', true],
            ['validation_issue', 'semestre_id', 'semestre', 'FK_VALIDATION_SEMESTRE', false],
            ['volume_horaire_parcours', 'parcours_id', 'parcours', 'FK_VHP_PARCOURS', false],
            ['volume_horaire_parcours', 'campagne_collecte_id', 'campagne_collecte', 'FK_VHP_CAMPAGNE', false],
        ] as [$table, $column, $referencedTable, $constraint, $cascade]) {
            if ($this->columnExists($table, $column) && !$this->indexExists($table, $column)) {
                $sql["Index {$table}.{$column}"] = "CREATE INDEX IDX_V2_{$constraint} ON {$table} ({$column})";
            }
            if ($this->columnExists($table, $column) && !$this->foreignKeyExists($table, $column, $referencedTable)) {
                $onDelete = $cascade ? ' ON DELETE CASCADE' : '';
                $sql["FK {$table}.{$column} → {$referencedTable}.id"] =
                    "ALTER TABLE {$table} ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES {$referencedTable} (id){$onDelete}";
            }
        }

        // Une ancienne version du SQL V2 peut être incomplète. Les champs métier
        // inconnus sont d'abord ajoutés nullable : aucune valeur n'est inventée.
        if ($this->tableExists('validation_issue')) {
            $this->addColumn($sql, 'validation_issue', 'semestre_id', 'INT DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'scope_type', 'VARCHAR(255) DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'scope_id', 'INT DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'rule_code', 'VARCHAR(255) DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'severity', 'VARCHAR(15) DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'message', 'VARCHAR(255) DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'payload', 'JSON DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'type_diplome', 'VARCHAR(255) DEFAULT NULL');
            $this->addColumn($sql, 'validation_issue', 'created_at', 'DATETIME DEFAULT NULL');

            // Si la table legacy est vide, on peut immédiatement retrouver le mapping
            // Doctrine final sans ambiguïté de données.
            $rows = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM validation_issue');
            if (0 === $rows) {
                $sql['Finalisation validation_issue.scope_type'] = 'ALTER TABLE validation_issue MODIFY scope_type VARCHAR(255) NOT NULL';
                $sql['Finalisation validation_issue.scope_id'] = 'ALTER TABLE validation_issue MODIFY scope_id INT NOT NULL';
                $sql['Finalisation validation_issue.rule_code'] = 'ALTER TABLE validation_issue MODIFY rule_code VARCHAR(255) NOT NULL';
                $sql['Finalisation validation_issue.severity'] = 'ALTER TABLE validation_issue MODIFY severity VARCHAR(15) NOT NULL';
                $sql['Finalisation validation_issue.type_diplome'] = 'ALTER TABLE validation_issue MODIFY type_diplome VARCHAR(255) NOT NULL';
                $sql['Finalisation validation_issue.created_at'] = "ALTER TABLE validation_issue MODIFY created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'";
            }
        }

        // Une ancienne version du SQL V2 créait validation_issue avec un schéma légèrement différent.
        if ($this->columnExists('validation_issue', 'message')) {
            $sql['Réconciliation validation_issue.message'] = 'ALTER TABLE validation_issue MODIFY message VARCHAR(255) DEFAULT NULL';
        }

        return $sql;
    }

    private function safeDefaults(): array
    {
        $sql = [];
        foreach (['element_constitutif', 'semestre', 'ue'] as $table) {
            if ($this->columnExists($table, 'validation_dirty')) {
                $sql["{$table}.validation_dirty → dirty"] = "UPDATE {$table} SET validation_dirty = 1";
                $sql["{$table}.validation_dirty default"] = "ALTER TABLE {$table} MODIFY validation_dirty TINYINT(1) NOT NULL DEFAULT 1";
            }
        }

        if ($this->columnExists('plateforme_admission', 'mode_export')) {
            $sql['plateforme_admission.mode_export vide → global'] = "UPDATE plateforme_admission SET mode_export = 'global' WHERE mode_export IS NULL OR mode_export = ''";
        }
        foreach (['semestre', 'ue', 'element_constitutif'] as $table) {
            if ($this->columnExists($table, 'validation_status')) {
                $sql[$table.'.validation_status NULL → incomplete'] = "UPDATE {$table} SET validation_status = 'incomplete' WHERE validation_status IS NULL";
            }
        }
        if ($this->fullDryRun || $this->columnExists('semestre', 'last_modification')) {
            $sql['semestre.last_modification NULL → NOW()'] = 'UPDATE semestre SET last_modification = NOW() WHERE last_modification IS NULL';
        }
        if ($this->fullDryRun || $this->columnExists('dpe_demande', 'created')) {
            $sql['dpe_demande.created → date_demande'] = 'UPDATE dpe_demande SET created = COALESCE(date_demande, NOW()) WHERE created IS NULL';
        }
        if ($this->fullDryRun || $this->columnExists('dpe_demande', 'updated')) {
            $sql['dpe_demande.updated → created/date_demande'] = 'UPDATE dpe_demande SET updated = COALESCE(created, date_demande, NOW()) WHERE updated IS NULL';
        }
        return $sql;
    }

    private function finalizeConstraints(): array
    {
        $sql = [];

        if ($this->fullDryRun || $this->columnExists('dpe_demande', 'created')) {
            $sql['dpe_demande.created → NOT NULL'] = 'ALTER TABLE dpe_demande MODIFY created DATETIME NOT NULL';
        }
        if ($this->fullDryRun || $this->columnExists('dpe_demande', 'updated')) {
            $sql['dpe_demande.updated → NOT NULL'] = 'ALTER TABLE dpe_demande MODIFY updated DATETIME NOT NULL';
        }
        if ($this->fullDryRun || $this->columnExists('semestre', 'last_modification')) {
            $sql['semestre.last_modification → NOT NULL'] = 'ALTER TABLE semestre MODIFY last_modification DATETIME NOT NULL';
        }

        return $sql;
    }

    private function check(SymfonyStyle $io): int
    {
        $io->section('Contrôles post-migration');
        $errors = [];
        $warnings = [];

        foreach ([['plateforme_admission','mode_export'], ['parcours','duree_parcours'], ['type_diplome','has_ects'], ['type_diplome_plateforme_admission','annees'], ['type_diplome_plateforme_admission','annees_capacite_requise'], ['semestre','validation_status'], ['ue','validation_status'], ['element_constitutif','validation_status']] as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $errors[] = "Colonne manquante : {$table}.{$column}";
            }
        }
        foreach (['fiche_matiere_tab_state', 'formation_tab_state', 'parcours_tab_state', 'validation_issue', 'volume_horaire_parcours', 'faq', 'help', 'help_image', 'document_conseil', 'document_conseil_formation'] as $table) {
            if (!$this->tableExists($table)) {
                $errors[] = 'Table manquante : '.$table;
            }
        }

        // Repères du socle Doctrine commun à main/v2. Ils permettent de détecter une base
        // dont les migrations racine n'ont pas été exécutées jusqu'au même niveau que le code.
        foreach ([['formation', 'logo'], ['parcours', 'logo'], ['type_diplome', 'logo']] as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $warnings[] = "Socle Doctrine incomplet : {$table}.{$column} absent. Vérifier doctrine:migrations:status avant la bascule V2.";
            }
        }
        foreach ([['role', null], ['user_centre', null], ['fiche_matiere_parcours', null]] as [$legacy]) {
            if ($this->tableExists($legacy)) {
                $warnings[] = "Table legacy {$legacy} encore présente : le niveau réel des migrations Doctrine diffère du socle attendu.";
            }
        }
        if ($this->columnExists('formation', 'version_parent_id')) {
            $warnings[] = 'Colonne legacy formation.version_parent_id encore présente : vérifier le niveau des migrations Doctrine.';
        }
        if ($this->columnExists('mention', 'domaine_id')) {
            $warnings[] = 'Colonne legacy mention.domaine_id encore présente : vérifier le niveau des migrations Doctrine.';
        }

        foreach ([
            ['fiche_matiere_tab_state', 'fiche_matiere_id', 'tab_key'],
            ['formation_tab_state', 'formation_id', 'tab_key'],
            ['parcours_tab_state', 'parcours_id', 'tab_key'],
            ['volume_horaire_parcours', 'parcours_id', 'campagne_collecte_id'],
        ] as [$table, $firstColumn, $secondColumn]) {
            if ($this->tableExists($table)) {
                $duplicates = (int) $this->connection->fetchOne(
                    "SELECT COUNT(*) FROM (SELECT 1 FROM {$table} GROUP BY {$firstColumn}, {$secondColumn} HAVING COUNT(*) > 1) duplicates"
                );
                if ($duplicates > 0) {
                    $errors[] = "{$duplicates} doublon(s) de clé métier dans {$table} ({$firstColumn}, {$secondColumn}) : résolution manuelle requise avant création de l'index UNIQUE.";
                }
                if (!$this->uniqueIndexExists($table, [$firstColumn, $secondColumn])) {
                    $errors[] = "Contrainte UNIQUE manquante sur {$table} ({$firstColumn}, {$secondColumn}).";
                }
            }
        }

        foreach ([
            ['fiche_matiere_tab_state', 'fiche_matiere_id', 'fiche_matiere'],
            ['formation_tab_state', 'formation_id', 'formation'],
            ['parcours_tab_state', 'parcours_id', 'parcours'],
            ['validation_issue', 'semestre_id', 'semestre'],
            ['volume_horaire_parcours', 'parcours_id', 'parcours'],
            ['volume_horaire_parcours', 'campagne_collecte_id', 'campagne_collecte'],
            ['dpe_demande', 'auteur_id', 'user'],
        ] as [$table, $column, $referencedTable]) {
            if ($this->columnExists($table, $column) && !$this->foreignKeyExists($table, $column, $referencedTable)) {
                $errors[] = "Clé étrangère manquante : {$table}.{$column} → {$referencedTable}.id";
            }
        }

        if ($this->tableExists('validation_issue')) {
            foreach (['scope_type', 'scope_id', 'rule_code', 'severity', 'type_diplome', 'created_at'] as $column) {
                if (!$this->columnExists('validation_issue', $column)) {
                    $errors[] = "Colonne manquante : validation_issue.{$column}";
                    continue;
                }
                $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM validation_issue WHERE {$column} IS NULL");
                if ($count > 0) {
                    $errors[] = "{$count} validation_issue sans {$column} : donnée legacy à compléter avant finalisation.";
                }
            }
        }

        foreach ([
            ['dpe_demande', 'created'],
            ['dpe_demande', 'updated'],
            ['semestre', 'last_modification'],
        ] as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $errors[] = "Colonne finale manquante : {$table}.{$column}";
            } elseif ($this->columnIsNullable($table, $column)) {
                $errors[] = "Contrainte NOT NULL manquante : {$table}.{$column}";
            }
        }

        foreach (['element_constitutif', 'semestre', 'ue'] as $table) {
            if (!$this->columnExists($table, 'validation_dirty')) {
                $errors[] = "Colonne manquante : {$table}.validation_dirty";
            } elseif ($this->columnIsNullable($table, 'validation_dirty') || '1' !== $this->columnDefault($table, 'validation_dirty')) {
                $errors[] = "{$table}.validation_dirty doit être NOT NULL DEFAULT 1.";
            }
        }

        foreach ([
            ['historique_formation', 'document_pv_id', 'document_conseil'],
            ['historique_formation', 'document_note_id', 'document_conseil'],
        ] as [$table, $column, $referencedTable]) {
            if (!$this->columnExists($table, $column)) {
                $errors[] = "Colonne manquante : {$table}.{$column}";
            } elseif (!$this->foreignKeyExists($table, $column, $referencedTable)) {
                $errors[] = "Clé étrangère manquante : {$table}.{$column} → {$referencedTable}.id";
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

    private function columnIsNullable(string $table, string $column): bool
    {
        return 'YES' === $this->connection->fetchOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function columnDefault(string $table, string $column): ?string
    {
        $default = $this->connection->fetchOne(
            'SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return false === $default || null === $default ? null : (string) $default;
    }

    private function indexExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    private function uniqueIndexExists(string $table, array $columns): bool
    {
        $indexes = $this->connection->fetchAllAssociative(
            'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );

        $grouped = [];
        foreach ($indexes as $index) {
            $grouped[$index['INDEX_NAME']][] = $index['COLUMN_NAME'];
        }

        foreach ($grouped as $indexColumns) {
            if ($indexColumns === $columns) {
                return true;
            }
        }

        return false;
    }

    private function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?',
            [$table, $column, $referencedTable]
        );
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
