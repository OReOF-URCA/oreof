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

#[AsCommand(name: 'app:migrate:v2:cleanup', description: 'Nettoyage destructif après validation de la migration V2.')]
final class MigrateV2CleanupCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Applique les DROP (dry-run par défaut).')
            ->addOption('confirm-backup', null, InputOption::VALUE_NONE, 'Confirme qu’une sauvegarde exploitable a été réalisée.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool) $input->getOption('apply');
        $io->title('ORéOF — nettoyage post-migration V2');

        if (!$this->migrationCompleted()) {
            $io->error('La migration V2 additive n’est pas entièrement enregistrée. Cleanup refusé.');
            return Command::FAILURE;
        }

        $drops = $this->dropPlan();

        if ($drops === []) {
            $io->success('Aucun DROP validé dans le plan de nettoyage pour le moment.');
            $io->note('Les éléments legacy seront ajoutés ici après audit des usages V2. La commande ne déduit volontairement aucun DROP depuis Doctrine.');
            return Command::SUCCESS;
        }

        $io->section('Plan destructif');
        $io->listing(array_keys($drops));

        if (!$apply) {
            $io->note('Dry-run uniquement. Aucun DROP exécuté.');
            return Command::SUCCESS;
        }

        if (!(bool) $input->getOption('confirm-backup')) {
            $io->error('Ajoutez --confirm-backup après avoir vérifié la sauvegarde.');
            return Command::FAILURE;
        }

        foreach ($drops as $description => $sql) {
            $io->writeln(' • '.$description);
            $this->connection->executeStatement($sql);
        }

        $io->success('Nettoyage V2 terminé.');
        return Command::SUCCESS;
    }

    private function migrationCompleted(): bool
    {
        if (!(bool) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_v2_migration'"
        )) {
            return false;
        }

        $required = ['010_documented_schema', '020_validation_schema', '030_admission_years', '040_new_v2_tables', '050_history_documents', '060_doctrine_alignment', '090_reconcile_schema', '100_safe_defaults', '110_finalize_constraints'];
        $done = $this->connection->fetchFirstColumn(
            "SELECT migration_key FROM app_v2_migration WHERE status = 'applied'"
        );

        return array_diff($required, $done) === [];
    }

    private function dropPlan(): array
    {
        /*
         * Intentionnellement vide tant que chaque table/colonne legacy n'a pas été
         * auditée dans le code V2. Ne jamais générer ce plan depuis schema:update.
         */
        return [];
    }
}
