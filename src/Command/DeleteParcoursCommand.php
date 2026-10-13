<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Parcours;
use App\Service\Parcours\ParcoursDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:parcours:delete',
    description: 'Supprime définitivement un parcours avec analyse préalable de ses dépendances',
)]
class DeleteParcoursCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParcoursDeleteService $parcoursDeleteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID du parcours à supprimer')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la suppression même si des dépendances partagées existent')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter l\'analyse sans procéder à la suppression');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = (int) $input->getArgument('id');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $parcours = $this->entityManager->getRepository(Parcours::class)->find($id);
        if ($parcours === null) {
            $io->error(sprintf('Parcours avec l\'ID %d introuvable.', $id));

            return Command::FAILURE;
        }

        $io->title(sprintf('Analyse de suppression du parcours #%d : %s', $parcours->getId(), $parcours->getLibelle()));

        $formation = $parcours->getFormation();
        if ($formation !== null) {
            $io->text(sprintf('Formation parente : %s', $formation->getDisplay()));
        }

        $analysis = $this->parcoursDeleteService->analyze($parcours);

        if ($analysis->isSeulParcoursDeLaFormation) {
            $io->warning('Attention : ce parcours est l\'unique parcours de cette formation !');
        }

        if ($analysis->isParcoursDefaut) {
            $io->warning('Attention : ce parcours est le parcours par défaut de cette formation !');
        }

        // Affichage des éléments internes qui seront supprimés
        $io->section('Éléments internes associés qui seront supprimés :');
        $internalRows = [];
        foreach ($analysis->internalElementsCount as $key => $count) {
            if ($count > 0) {
                $internalRows[] = [$key, (string) $count];
            }
        }
        if ($internalRows !== []) {
            $io->table(['Élément', 'Quantité'], $internalRows);
        } else {
            $io->text('Aucun élément interne complexe.');
        }

        // Affichage des dépendances partagées
        if ($analysis->hasSharedDependencies()) {
            $io->section('⚠️ Dépendances partagées détectées avec d\'autres entités :');
            $depRows = [];
            foreach ($analysis->sharedDependencies as $dep) {
                $depRows[] = [
                    $dep['severity'] === 'danger' ? '<fg=red>DANGER</>' : '<fg=yellow>WARNING</>',
                    $dep['type'],
                    $dep['titre'],
                    $dep['description'],
                ];
            }
            $io->table(['Sévérité', 'Type', 'Élément', 'Détails'], $depRows);

            if (!$force) {
                $io->error('Ce parcours a des dépendances partagées avec d\'autres parcours ou formations. Utilisez --force pour forcer la suppression.');

                return Command::FAILURE;
            }

            $io->caution('Option --force spécifiée : la suppression va détacher ou retirer ces éléments partagés.');
        } else {
            $io->success('Aucune dépendance externe critique détectée. Ce parcours peut être supprimé sans impact sur d\'autres formations.');
        }

        if ($dryRun) {
            $io->note('Mode --dry-run actif : aucune modification n\'a été appliquée en base de données.');

            return Command::SUCCESS;
        }

        if ($input->isInteractive()) {
            $question = sprintf('Confirmez-vous la SUPPRESSION DÉFINITIVE et IRRÉVERSIBLE du parcours "%s" ?', $parcours->getLibelle());
            if (!$io->confirm($question, false)) {
                $io->warning('Suppression annulée par l\'utilisateur.');

                return Command::SUCCESS;
            }
        }

        $this->parcoursDeleteService->delete($parcours, $force);
        $io->success(sprintf('Le parcours #%d a été définitivement supprimé de la base de données.', $id));

        return Command::SUCCESS;
    }
}
