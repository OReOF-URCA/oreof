<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Formation;
use App\Service\Formation\FormationDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:formation:delete',
    description: 'Supprime définitivement une formation avec analyse préalable de ses dépendances et parcours',
)]
class DeleteFormationCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormationDeleteService $formationDeleteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'ID de la formation à supprimer')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forcer la suppression même si des dépendances partagées existent')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Exécuter l\'analyse sans procéder à la suppression');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = (int) $input->getArgument('id');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');

        $formation = $this->entityManager->getRepository(Formation::class)->find($id);
        if ($formation === null) {
            $io->error(sprintf('Formation avec l\'ID %d introuvable.', $id));

            return Command::FAILURE;
        }

        $io->title(sprintf('Analyse de suppression de la formation #%d : %s', $formation->getId(), $formation->getDisplay()));

        $analysis = $this->formationDeleteService->analyze($formation);

        if (!$analysis->canDelete()) {
            $io->error($analysis->blockingReasons);

            return Command::FAILURE;
        }

        if ($analysis->parcoursDefaut !== null) {
            $io->warning(sprintf('Attention : cette formation possède un parcours par défaut (« %s ») qui sera supprimé avec elle !', $analysis->parcoursDefaut->getLibelle()));
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
                $io->error('Cette formation ou son parcours par défaut a des dépendances partagées. Utilisez --force pour forcer la suppression.');

                return Command::FAILURE;
            }

            $io->caution('Option --force spécifiée : la suppression va détacher ou retirer ces éléments partagés.');
        } else {
            $io->success('Aucune dépendance externe critique détectée. Cette formation peut être supprimée sans impact sur d\'autres formations.');
        }

        if ($dryRun) {
            $io->note('Mode --dry-run actif : aucune modification n\'a été appliquée en base de données.');

            return Command::SUCCESS;
        }

        if ($input->isInteractive()) {
            $question = sprintf('Confirmez-vous la SUPPRESSION DÉFINITIVE et IRRÉVERSIBLE de la formation "%s" ?', $formation->getDisplay());
            if (!$io->confirm($question, false)) {
                $io->warning('Suppression annulée par l\'utilisateur.');

                return Command::SUCCESS;
            }
        }

        $this->formationDeleteService->delete($formation, $force);
        $io->success(sprintf('La formation #%d a été définitivement supprimée de la base de données.', $id));

        return Command::SUCCESS;
    }
}
