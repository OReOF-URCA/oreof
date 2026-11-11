<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreofv2/src/Command/ParcoursCompletionCheckCommand.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 11/11/2026 18:27
 */

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Entity\ParcoursTabState;
use App\Repository\CampagneCollecteRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ParcoursTabStateRepository;
use App\Service\Parcours\ParcoursTabCompletionChecker;
use App\Service\Parcours\ParcoursTabRegistry;
use App\Service\Validation\SemesterValidationRefresher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:parcours:check-completion', description: 'Vérifie et met à jour l\'état de complétion des onglets de parcours (et optionnellement des semestres)')]
final class ParcoursCompletionCheckCommand extends Command
{
    public function __construct(
        private readonly ParcoursTabCompletionChecker $checker,
        private readonly ParcoursRepository           $parcoursRepository,
        private readonly CampagneCollecteRepository   $campagneCollecteRepository,
        private readonly ParcoursTabStateRepository   $parcoursTabStateRepository,
        private readonly SemesterValidationRefresher  $semesterValidationRefresher,
        private readonly EntityManagerInterface       $em,
        private readonly LoggerInterface              $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('parcours', null, InputOption::VALUE_REQUIRED, 'ID du parcours à traiter')
            ->addOption('formation', null, InputOption::VALUE_REQUIRED, 'ID de la formation dont on traitera les parcours')
            ->addOption('campagne', null, InputOption::VALUE_REQUIRED, 'ID de la campagne de collecte à traiter')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Traiter tous les parcours')
            ->addOption('with-semestres', null, InputOption::VALUE_NONE, 'Recalculer également la validation des semestres')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne pas persister les changements')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Persister les changements en base')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie (summary|json)', 'summary')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Fichier de sortie pour le rapport JSON', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $parcoursId = $input->getOption('parcours');
        $formationId = $input->getOption('formation');
        $campagneId = $input->getOption('campagne');
        $all = (bool) $input->getOption('all');
        $withSemestres = (bool) $input->getOption('with-semestres');
        $dryRun = (bool) $input->getOption('dry-run');
        $save = (bool) $input->getOption('save');
        $format = (string) $input->getOption('format');
        $outputPath = $input->getOption('output');

        if ($dryRun && $save) {
            $io->error('Les options --dry-run et --save sont incompatibles. Choisissez l\'une ou l\'autre.');
            return Command::FAILURE;
        }

        $parcoursIds = [];

        if ($parcoursId !== null) {
            $parcoursIds[] = (int) $parcoursId;
        }

        if ($formationId !== null) {
            $rows = $this->parcoursRepository->createQueryBuilder('p')
                ->select('p.id')
                ->where('IDENTITY(p.formation) = :formationId')
                ->setParameter('formationId', (int) $formationId)
                ->getQuery()
                ->getScalarResult();
            foreach ($rows as $row) {
                $parcoursIds[] = (int) $row['id'];
            }
        }

        if ($campagneId !== null) {
            $campagne = $this->campagneCollecteRepository->find((int) $campagneId);
            if (!$campagne instanceof CampagneCollecte) {
                $io->error(sprintf('Campagne %s introuvable.', (string) $campagneId));
                return Command::FAILURE;
            }
            $rows = $this->parcoursRepository->createQueryBuilder('p')
                ->select('DISTINCT p.id')
                ->innerJoin('p.dpeParcours', 'dpe')
                ->where('dpe.campagneCollecte = :campagne')
                ->setParameter('campagne', $campagne)
                ->getQuery()
                ->getScalarResult();
            foreach ($rows as $row) {
                $parcoursIds[] = (int) $row['id'];
            }
        }

        if ($all) {
            $batchSize = 100;
            $page = 0;
            do {
                $rows = $this->parcoursRepository->createQueryBuilder('p')
                    ->select('p.id')
                    ->setFirstResult($page * $batchSize)
                    ->setMaxResults($batchSize)
                    ->getQuery()
                    ->getScalarResult();
                $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
                foreach ($ids as $id) {
                    $parcoursIds[] = $id;
                }
                ++$page;
            } while (count($ids) === $batchSize);
        }

        $parcoursIds = array_values(array_unique($parcoursIds));

        if (count($parcoursIds) === 0) {
            $io->error('Aucune cible spécifiée. Utilisez --parcours, --formation, --campagne ou --all.');
            return Command::FAILURE;
        }

        if (!$save && !$dryRun) {
            $io->note('Mode simulation (aucune écriture). Ajoutez --save pour persister en base.');
        }

        $total = count($parcoursIds);
        $io->section(sprintf('Démarrage du traitement de %d parcours', $total));
        $startTime = microtime(true);

        $io->progressStart($total);
        $report = [];
        $changedCount = 0;
        $processed = 0;
        $errorsCount = 0;

        foreach ($parcoursIds as $pid) {
            ++$processed;
            $parcours = $this->parcoursRepository->find($pid);
            if (!$parcours instanceof Parcours) {
                $io->warning(sprintf('Parcours %d introuvable (ignoré)', $pid));
                ++$errorsCount;
                $io->progressAdvance();
                continue;
            }

            try {
                $row = $this->processParcours($parcours, $dryRun, $save, $withSemestres);
                if (!empty($row['changed'])) {
                    ++$changedCount;
                }
                $report[] = $row;
            } catch (\Throwable $e) {
                $this->logger->error('Erreur lors du traitement parcours ' . $parcours->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
                $report[] = [
                    'id' => $parcours->getId(),
                    'title' => (string) $parcours->getLibelle(),
                    'error' => $e->getMessage(),
                    'changed' => false,
                ];
                ++$errorsCount;
            }

            if ($processed % 50 === 0) {
                $this->em->clear();
            }

            $io->progressAdvance();
        }

        $io->progressFinish();
        $duration = microtime(true) - $startTime;

        $io->newLine();
        $io->section('Résumé d\'exécution');
        $io->text([
            sprintf('Parcours demandés : %d', $total),
            sprintf('Traités : %d', $processed),
            sprintf('Modifiés : %d', $changedCount),
            sprintf('Erreurs : %d', $errorsCount),
            sprintf('Durée : %.2f s', $duration),
        ]);

        $io->success(sprintf('Traitement terminé : %d / %d parcours traités, %d modifiés', $processed, $total, $changedCount));

        if ($format === 'json' || $outputPath !== null) {
            $content = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (is_string($outputPath) && $outputPath !== '') {
                file_put_contents($outputPath, $content);
                $io->text('Rapport sauvegardé dans ' . $outputPath);
            } else {
                $io->writeln($content);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function processParcours(Parcours $parcours, bool $dryRun, bool $save, bool $withSemestres): array
    {
        $tabs = ParcoursTabRegistry::TABS;
        $statuses = [];
        $issuesSummary = [];
        $changed = false;

        $existingStates = $this->parcoursTabStateRepository->indexByTabKey($parcours);

        foreach ($tabs as $tab) {
            $issues = $this->checker->getTabIssues($parcours, $tab);
            $isDone = count($issues) === 0;
            $statuses[$tab] = $isDone;
            $issuesSummary[$tab] = array_map(static fn ($i): array => [
                'field' => $i->field,
                'label' => $i->label,
                'message' => $i->message,
            ], $issues);

            $state = $existingStates[$tab] ?? null;
            if (!$state instanceof ParcoursTabState) {
                $state = new ParcoursTabState($parcours, $tab);
                if ($save && !$dryRun) {
                    $this->em->persist($state);
                }
                $changed = true;
            }

            $statusText = $isDone ? 'green' : 'red';
            if ($state->isDone() !== $isDone || $state->getStatus() !== $statusText || $state->getIssues() !== $issuesSummary[$tab]) {
                $state->setDone($isDone);
                $state->setStatus($statusText);
                $state->setIssues($issuesSummary[$tab]);
                $state->touch();
                $changed = true;
            }
        }

        if ($withSemestres && $save && !$dryRun && $parcours->getFormation()?->getTypeDiplome() !== null) {
            foreach ($parcours->getAnnees() as $annee) {
                foreach ($annee->getParcoursSemestre() as $semestreParcours) {
                    if ($semestreParcours->getSemestre() !== null) {
                        $this->semesterValidationRefresher->forceRefresh($semestreParcours, $parcours);
                        $changed = true;
                    }
                }
            }
        }

        if ($changed && !$dryRun && $save) {
            $this->em->flush();
        }

        return [
            'id' => $parcours->getId(),
            'title' => (string) $parcours->getLibelle(),
            'changed' => $changed,
            'statuses' => $statuses,
            'issues' => $issuesSummary,
        ];
    }
}

