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

#[AsCommand(
    name: 'app:annee:init-regime-inscription',
    description: 'Initialise les régimes d\'inscription des années à partir de leur parcours parent.'
)]
final class InitAnneeRegimeInscriptionCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Applique réellement les changements (sinon mode simulation).')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Écrase même si des régimes sont déjà renseignés sur l\'année.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apply = (bool)$input->getOption('apply');
        $force = (bool)$input->getOption('force');

        $io->title('Initialisation des régimes d\'inscription des années');

        $whereClause = $force
            ? '1=1'
            : "(a.regime_inscription IS NULL OR a.regime_inscription = '[]' OR a.regime_inscription = '' OR a.regime_inscription = 'null')";

        // 1. Depuis le parcours
        $countParcoursSql = "SELECT COUNT(*) FROM annee a 
            INNER JOIN parcours p ON a.parcours_id = p.id 
            WHERE {$whereClause} 
            AND p.regime_inscription IS NOT NULL 
            AND p.regime_inscription != '[]' 
            AND p.regime_inscription != '' 
            AND p.regime_inscription != 'null'";
        $countParcours = (int)$this->connection->fetchOne($countParcoursSql);

        // 2. Depuis la formation si le parcours n'en a pas
        $countFormationSql = "SELECT COUNT(*) FROM annee a 
            INNER JOIN parcours p ON a.parcours_id = p.id 
            INNER JOIN formation f ON p.formation_id = f.id 
            WHERE {$whereClause} 
            AND (p.regime_inscription IS NULL OR p.regime_inscription = '[]' OR p.regime_inscription = '' OR p.regime_inscription = 'null')
            AND f.regime_inscription IS NOT NULL 
            AND f.regime_inscription != '[]' 
            AND f.regime_inscription != '' 
            AND f.regime_inscription != 'null'";
        $countFormation = (int)$this->connection->fetchOne($countFormationSql);

        $totalToUpdate = $countParcours + $countFormation;
        $io->text(sprintf('Années à initialiser : %d (%d depuis Parcours, %d depuis Formation)', $totalToUpdate, $countParcours, $countFormation));

        if (!$apply) {
            $io->info('Mode simulation. Relancez avec --apply pour enregistrer les modifications.');
            return Command::SUCCESS;
        }

        // Exécution des mises à jour directes
        $updatedParcours = $this->connection->executeStatement(
            "UPDATE annee a 
             INNER JOIN parcours p ON a.parcours_id = p.id 
             SET a.regime_inscription = p.regime_inscription 
             WHERE {$whereClause} 
             AND p.regime_inscription IS NOT NULL 
             AND p.regime_inscription != '[]' 
             AND p.regime_inscription != '' 
             AND p.regime_inscription != 'null'"
        );

        $updatedFormation = $this->connection->executeStatement(
            "UPDATE annee a 
             INNER JOIN parcours p ON a.parcours_id = p.id 
             INNER JOIN formation f ON p.formation_id = f.id 
             SET a.regime_inscription = f.regime_inscription 
             WHERE {$whereClause} 
             AND (a.regime_inscription IS NULL OR a.regime_inscription = '[]' OR a.regime_inscription = '' OR a.regime_inscription = 'null')
             AND f.regime_inscription IS NOT NULL 
             AND f.regime_inscription != '[]' 
             AND f.regime_inscription != '' 
             AND f.regime_inscription != 'null'"
        );

        $io->success(sprintf('Initialisation terminée : %d années mises à jour (%d depuis Parcours, %d depuis Formation).', $updatedParcours + $updatedFormation, $updatedParcours, $updatedFormation));

        return Command::SUCCESS;
    }
}
