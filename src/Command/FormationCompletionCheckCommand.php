<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Command/FormationCompletionCheckCommand.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 15/02/2026 10:00
 */

namespace App\Command;

use App\Entity\Formation;
use App\Entity\FormationTabState;
use App\Repository\FormationTabStateRepository;
use App\Service\Formation\FormationTabCompletionChecker;
use App\Service\Formation\FormationTabRegistry;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\FicheMatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:formation:check-completion', description: 'Vérifie et met à jour l\'état de complétion des formations')]
final class FormationCompletionCheckCommand extends Command
{
    public function __construct(
        private readonly FormationTabCompletionChecker $checker,
        private readonly FormationRepository           $formationRepository,
        private readonly ParcoursRepository            $parcoursRepository,
        private readonly FicheMatiereRepository        $ficheMatiereRepository,
        private readonly FormationTabStateRepository   $formationTabStateRepository,
        private readonly EntityManagerInterface        $em,
        private readonly LoggerInterface               $logger
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('formation', null, InputOption::VALUE_REQUIRED, 'ID de la formation à traiter')
            ->addOption('parcours', null, InputOption::VALUE_REQUIRED, 'ID du parcours : on traitera la formation liée')
            ->addOption('fichematiere', null, InputOption::VALUE_REQUIRED, 'ID de la fiche matière : on traitera la formation liée')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Traiter toutes les formations')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne pas persister les changements')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Persister les changements en base')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie (json)', 'json')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Fichier de sortie pour le rapport', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $formationId = $input->getOption('formation');
        $parcoursId = $input->getOption('parcours');
        $ficheId = $input->getOption('fichematiere');
        $all = (bool)$input->getOption('all');
        $dryRun = (bool)$input->getOption('dry-run');
        $save = (bool)$input->getOption('save');
        $format = $input->getOption('format');
        $outputPath = $input->getOption('output');

        if ($dryRun && $save) {
            $io->error('Options --dry-run et --save sont incompatibles. Choisissez l\'un ou l\'autre.');
            return Command::FAILURE;
        }

        // Collecte des formations cibles (on stocke des ids pour chunking)
        $formationIds = [];

        if ($formationId !== null) {
            $formationIds[] = (int)$formationId;
        }

        if ($parcoursId !== null) {
            $parcours = $this->parcoursRepository->find((int)$parcoursId);
            if ($parcours === null) {
                $io->error(sprintf('Parcours %s introuvable', $parcoursId));
                return Command::FAILURE;
            }
            $f = $parcours->getFormation();
            if ($f instanceof Formation) {
                $formationIds[] = $f->getId();
            }
        }

        if ($ficheId !== null) {
            $fiche = $this->ficheMatiereRepository->find((int)$ficheId);
            if ($fiche === null) {
                $io->error(sprintf('FicheMatiere %s introuvable', $ficheId));
                return Command::FAILURE;
            }
            $parcours = $fiche->getParcours();
            if ($parcours !== null && $parcours->getFormation() instanceof Formation) {
                $formationIds[] = $parcours->getFormation()->getId();
            }
        }

        if ($all) {
            // chunking pour limiter la mémoire
            $batchSize = 50;
            $page = 0;
            do {
                $qb = $this->formationRepository->createQueryBuilder('f')
                    ->select('f.id')
                    ->setFirstResult($page * $batchSize)
                    ->setMaxResults($batchSize);

                $rows = $qb->getQuery()->getScalarResult();
                $ids = array_map(fn($r) => (int)$r['id'], $rows);

                foreach ($ids as $id) {
                    $formationIds[] = $id;
                }

                $page++;
            } while (count($ids) === $batchSize);
        }

        // dédupliquer
        $formationIds = array_values(array_unique($formationIds));

        if (count($formationIds) === 0) {
            $io->error('Aucune cible spécifiée. Utilisez --formation, --parcours, --fichematiere ou --all.');
            return Command::FAILURE;
        }

        // START: new progress / metrics
        $total = count($formationIds);
        $io->section(sprintf('Démarrage du traitement de %d formation(s)', $total));
        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        $io->progressStart($total);
        $report = [];
        $changedCount = 0;
        $processed = 0;
        $errorsCount = 0;
        // END: new progress / metrics

        foreach ($formationIds as $fid) {
            $processed++;
            $f = $this->formationRepository->find($fid);
            if (!$f instanceof Formation) {
                $io->warning(sprintf('Formation %d introuvable (skip)', $fid));
                $errorsCount++;
                $io->progressAdvance();
                continue;
            }

            try {
                $row = $this->processFormation($f, $dryRun, $save);
                if (!empty($row['changed'])) {
                    $changedCount++;
                }
                $report[] = $row;
            } catch (\Throwable $e) {
                $this->logger->error('Erreur lors du traitement formation ' . $f->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
                $io->warning(sprintf('Erreur sur formation %d : %s', $f->getId(), $e->getMessage()));
                $report[] = [
                    'id' => $f->getId(),
                    'title' => (string)$f->getSlug(),
                    'error' => $e->getMessage(),
                    'changed' => false,
                ];
                $errorsCount++;
            }

            // libérer mémoire par batch si nécessaire
            if ($processed % 50 === 0) {
                $this->em->clear();
            }

            // Mise à jour de la progress bar
            $io->progressAdvance();
        }

        // finish progress
        $io->progressFinish();

        $duration = microtime(true) - $startTime;
        $memoryEnd = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);

        $io->newLine(1);
        $io->section('Résumé d\'exécution');
        $io->text([
            sprintf('Formations demandées : %d', $total),
            sprintf('Traitées : %d', $processed),
            sprintf('Modifiées : %d', $changedCount),
            sprintf('Erreurs : %d', $errorsCount),
            sprintf('Durée : %s', $this->formatDuration($duration)),
            sprintf('Mémoire au démarrage : %s', $this->formatBytes($memoryStart)),
            sprintf('Mémoire finale : %s', $this->formatBytes($memoryEnd)),
            sprintf('Mémoire pic : %s', $this->formatBytes($memoryPeak)),
        ]);

        if ($io->isVerbose()) {
            // afficher un petit tableau des résultats (limité à 200 lignes pour éviter surcharge)
            $rows = [];
            $count = 0;
            foreach ($report as $r) {
                $rows[] = [
                    $r['id'],
                    $r['title'] ?? '',
                    $r['changed'] ? 'yes' : 'no',
                    count($r['issues'] ?? [])
                ];
                $count++;
                if ($count >= 200) {
                    break;
                }
            }

            if (count($rows) > 0) {
                $io->newLine(1);
                $io->table(['ID', 'Titre', 'Changed', 'NbTabsIssues'], $rows);
            }
        }

        $io->success(sprintf('Traitement terminé : %d / %d formations traitées, %d modifiées', $processed, $total, $changedCount));

        if ($format === 'json') {
            $content = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($outputPath) {
                file_put_contents($outputPath, $content);
                $io->text('Rapport sauvegardé dans ' . $outputPath);
            } else {
                $io->writeln($content);
            }
        }

        return Command::SUCCESS;
    }

    private function processFormation(Formation $formation, bool $dryRun, bool $save): array
    {
        // calculer pour chaque onglet s'il y a des issues
        $tabs = FormationTabRegistry::TABS;
        $statuses = [];
        $issuesSummary = [];
        $changed = false;
        $modifiedStates = [];

        // charger d'abord tous les states existants pour ces tabs
        $existingStates = $this->formationTabStateRepository->findByFormationAndTabs($formation, $tabs);

        foreach ($tabs as $tab) {
            $issues = $this->checker->getTabIssues($formation, $tab);
            $isDone = count($issues) === 0;
            $statuses[$tab] = $isDone;
            $issuesSummary[$tab] = array_map(fn($i) => [
                'field' => $i->field,
                'label' => $i->label,
                'message' => $i->message,
            ], $issues);

            // récupérer l'état existant depuis la map chargée, sinon créer et persister si demandé
            $state = $existingStates[$tab] ?? null;
            if (!$state instanceof FormationTabState) {
                $state = new FormationTabState($formation, $tab);
                if ($save && !$dryRun) {
                    $this->em->persist($state);
                }
                $changed = true;
                $modifiedStates[] = $state;
            }

            // calculer le status textuel (ex: 'green'/'red') et done bool
            $statusText = $isDone ? 'green' : 'red';
            if ($state->isDone() !== $isDone || $state->getStatus() !== $statusText) {
                $state->setDone($isDone);
                $state->setStatus($statusText);
                $state->touch();
                $changed = true;
                $modifiedStates[] = $state;
            }
        }

        if ($changed && !$dryRun && $save) {
            // flush une fois par formation (on a déjà persisté les créations)
            $this->em->flush();
            $this->logger->info('Formation ' . $formation->getId() . ' : états des onglets mis à jour', ['etat' => $statuses]);
        } elseif ($changed) {
            // si dry-run on veut quand même signaler ce qui aurait changé
            $this->logger->info('Formation ' . $formation->getId() . ' : changements détectés (dry-run)', ['etat' => $statuses]);
        }

        return [
            'id' => $formation->getId(),
            'title' => (string)$formation->getSlug(),
            'changed' => $changed,
            'etat' => $statuses,
            'issues' => $issuesSummary,
        ];
    }

    private function formatDuration(float $seconds): string
    {
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return sprintf('%dh %02dm %02ds', $h, $m, (int)$s);
        }

        if ($m > 0) {
            return sprintf('%dm %02ds', $m, (int)$s);
        }

        return sprintf('%02ds', (int)$s);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int)floor(log($bytes, 1024));
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
