<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreofv2/src/Command/FicheMatiereCompletionCheckCommand.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 12/11/2026 18:55
 */

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereTabState;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FicheMatiereTabStateRepository;
use App\Service\FicheMatiere\FicheMatiereTabCompletionChecker;
use App\Service\FicheMatiere\FicheMatiereTabRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:fiche-matiere:check-completion', description: 'Vérifie et met à jour l\'état de complétion des onglets des fiches matières pour une campagne donnée')]
final class FicheMatiereCompletionCheckCommand extends Command
{
    public function __construct(
        private readonly FicheMatiereTabCompletionChecker $checker,
        private readonly FicheMatiereRepository           $ficheMatiereRepository,
        private readonly CampagneCollecteRepository       $campagneCollecteRepository,
        private readonly FicheMatiereTabStateRepository   $ficheMatiereTabStateRepository,
        private readonly EntityManagerInterface           $em,
        private readonly LoggerInterface                  $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fiche-matiere', null, InputOption::VALUE_REQUIRED, 'ID ou slug de la fiche matière à traiter')
            ->addOption('campagne', null, InputOption::VALUE_REQUIRED, 'ID de la campagne de collecte à traiter')
            ->addOption('parcours', null, InputOption::VALUE_REQUIRED, 'ID du parcours dont on traitera les fiches matières')
            ->addOption('formation', null, InputOption::VALUE_REQUIRED, 'ID de la formation dont on traitera les fiches matières')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Traiter toutes les fiches matières')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Ne pas persister les changements')
            ->addOption('save', null, InputOption::VALUE_NONE, 'Persister les changements en base')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Format de sortie (summary|json)', 'summary')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Fichier de sortie pour le rapport JSON', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ficheIdOrSlug = $input->getOption('fiche-matiere');
        $campagneId = $input->getOption('campagne');
        $parcoursId = $input->getOption('parcours');
        $formationId = $input->getOption('formation');
        $all = (bool) $input->getOption('all');
        $dryRun = (bool) $input->getOption('dry-run');
        $save = (bool) $input->getOption('save');
        $format = (string) $input->getOption('format');
        $outputPath = $input->getOption('output');

        if ($dryRun && $save) {
            $io->error('Les options --dry-run et --save sont incompatibles. Choisissez l\'une ou l\'autre.');
            return Command::FAILURE;
        }

        $ficheMatiereIds = [];

        if ($ficheIdOrSlug !== null) {
            $fiche = is_numeric($ficheIdOrSlug)
                ? $this->ficheMatiereRepository->find((int) $ficheIdOrSlug)
                : $this->ficheMatiereRepository->findOneBy(['slug' => (string) $ficheIdOrSlug]);

            if ($fiche instanceof FicheMatiere) {
                $ficheMatiereIds[] = $fiche->getId();
            } else {
                $io->error(sprintf('Fiche matière "%s" introuvable.', (string) $ficheIdOrSlug));
                return Command::FAILURE;
            }
        }

        if ($campagneId !== null) {
            $campagne = $this->campagneCollecteRepository->find((int) $campagneId);
            if (!$campagne instanceof CampagneCollecte) {
                $io->error(sprintf('Campagne %s introuvable.', (string) $campagneId));
                return Command::FAILURE;
            }

            $rows = $this->ficheMatiereRepository->createQueryBuilder('fm')
                ->select('DISTINCT fm.id')
                ->leftJoin('fm.parcours', 'p')
                ->leftJoin('p.dpeParcours', 'dpe')
                ->where('fm.campagneCollecte = :campagne OR dpe.campagneCollecte = :campagne')
                ->setParameter('campagne', $campagne)
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $ficheMatiereIds[] = (int) $row['id'];
            }
        }

        if ($parcoursId !== null) {
            $rows = $this->ficheMatiereRepository->createQueryBuilder('fm')
                ->select('DISTINCT fm.id')
                ->where('IDENTITY(fm.parcours) = :parcoursId')
                ->setParameter('parcoursId', (int) $parcoursId)
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $ficheMatiereIds[] = (int) $row['id'];
            }
        }

        if ($formationId !== null) {
            $rows = $this->ficheMatiereRepository->createQueryBuilder('fm')
                ->select('DISTINCT fm.id')
                ->innerJoin('fm.parcours', 'p')
                ->where('IDENTITY(p.formation) = :formationId')
                ->setParameter('formationId', (int) $formationId)
                ->getQuery()
                ->getScalarResult();

            foreach ($rows as $row) {
                $ficheMatiereIds[] = (int) $row['id'];
            }
        }

        if ($all) {
            $batchSize = 200;
            $page = 0;
            do {
                $rows = $this->ficheMatiereRepository->createQueryBuilder('fm')
                    ->select('fm.id')
                    ->setFirstResult($page * $batchSize)
                    ->setMaxResults($batchSize)
                    ->getQuery()
                    ->getScalarResult();

                $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
                foreach ($ids as $id) {
                    $ficheMatiereIds[] = $id;
                }
                ++$page;
            } while (count($ids) === $batchSize);
        }

        $ficheMatiereIds = array_values(array_unique(array_filter($ficheMatiereIds, static fn (?int $id): bool => $id !== null)));

        if (count($ficheMatiereIds) === 0) {
            $io->error('Aucune cible spécifiée ou trouvée. Utilisez --campagne, --fiche-matiere, --parcours, --formation ou --all.');
            return Command::FAILURE;
        }

        if (!$save && !$dryRun) {
            $io->note('Mode simulation (aucune écriture). Ajoutez --save pour persister en base.');
        }

        $total = count($ficheMatiereIds);
        $io->section(sprintf('Démarrage du traitement de %d fiches matières', $total));
        $startTime = microtime(true);

        $io->progressStart($total);
        $report = [];
        $changedCount = 0;
        $processed = 0;
        $errorsCount = 0;

        foreach ($ficheMatiereIds as $fId) {
            ++$processed;
            $fiche = $this->ficheMatiereRepository->find($fId);
            if (!$fiche instanceof FicheMatiere) {
                $io->warning(sprintf('Fiche matière %d introuvable (ignorée)', $fId));
                ++$errorsCount;
                $io->progressAdvance();
                continue;
            }

            try {
                $row = $this->processFicheMatiere($fiche, $dryRun, $save);
                if (!empty($row['changed'])) {
                    ++$changedCount;
                }
                $report[] = $row;
            } catch (\Throwable $e) {
                $this->logger->error('Erreur lors du traitement de la fiche matière ' . $fiche->getId() . ': ' . $e->getMessage(), ['exception' => $e]);
                $report[] = [
                    'id' => $fiche->getId(),
                    'title' => (string) $fiche->getLibelle(),
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
            sprintf('Fiches matières demandées : %d', $total),
            sprintf('Traitées : %d', $processed),
            sprintf('Modifiées : %d', $changedCount),
            sprintf('Erreurs : %d', $errorsCount),
            sprintf('Durée : %.2f s', $duration),
        ]);

        $io->success(sprintf('Traitement terminé : %d / %d fiches matières traitées, %d modifiées', $processed, $total, $changedCount));

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
    private function processFicheMatiere(FicheMatiere $ficheMatiere, bool $dryRun, bool $save): array
    {
        $tabs = FicheMatiereTabRegistry::TABS;
        $statuses = [];
        $issuesSummary = [];
        $changed = false;

        $existingStates = $this->ficheMatiereTabStateRepository->indexByTabKey($ficheMatiere);

        foreach ($tabs as $tab) {
            $issues = $this->checker->getTabIssues($ficheMatiere, $tab);
            $isDone = count($issues) === 0;
            $statuses[$tab] = $isDone;
            $issuesSummary[$tab] = array_map(static fn ($i): array => [
                'field' => $i->field,
                'label' => $i->label,
                'message' => $i->message,
            ], $issues);

            $state = $existingStates[$tab] ?? null;
            if (!$state instanceof FicheMatiereTabState) {
                $state = new FicheMatiereTabState($ficheMatiere, $tab);
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

        if ($changed && !$dryRun && $save) {
            $this->em->flush();
        }

        return [
            'id' => $ficheMatiere->getId(),
            'title' => (string) $ficheMatiere->getLibelle(),
            'changed' => $changed,
            'statuses' => $statuses,
            'issues' => $issuesSummary,
        ];
    }
}

