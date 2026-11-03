<?php

declare(strict_types=1);

namespace App\Command;

use App\DTO\Comparison\AnomalyItem;
use App\DTO\Comparison\EcDiff;
use App\DTO\Comparison\FicheMatiereDiff;
use App\DTO\Comparison\MutualisationDiff;
use App\DTO\Comparison\ParcoursN1ComparisonResult;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Service\Parcours\ParcoursN1ComparatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:compare-parcours-n1',
    description: 'Compare la maquette et les fiches matières d\'un parcours N avec son origine N-1 pour détecter les régressions (ex. sync ORéBUT).',
)]
class CompareParcoursFromN1Command extends Command
{
    public function __construct(
        protected ParcoursRepository $parcoursRepository,
        protected FormationRepository $formationRepository,
        protected CampagneCollecteRepository $campagneCollecteRepository,
        protected ParcoursN1ComparatorService $comparatorService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'parcours',
                'p',
                InputOption::VALUE_REQUIRED,
                'Identifiant (ID) d\'un parcours spécifique de l\'année N.'
            )
            ->addOption(
                'formation',
                'f',
                InputOption::VALUE_REQUIRED,
                'Identifiant (ID) ou slug d\'une formation de l\'année N.'
            )
            ->addOption(
                'campagne',
                'c',
                InputOption::VALUE_REQUIRED,
                'Campagne de collecte de l\'année N (libellé ex: "2027-2028", année ex: 2027, ou ID).'
            )
            ->addOption(
                'type-diplome',
                't',
                InputOption::VALUE_REQUIRED,
                'Filtrer par type de diplôme (défaut: "BUT", ou "all" pour tout type).',
                'BUT'
            )
            ->addOption(
                'only-diff',
                null,
                InputOption::VALUE_NONE,
                'N\'afficher que les parcours présentant des anomalies ou des écarts.'
            )
            ->addOption(
                'detail',
                null,
                InputOption::VALUE_NONE,
                'Afficher le détail exhaustif de chaque EC, fiche matière et mutualisation.'
            )
            ->addOption(
                'scope',
                's',
                InputOption::VALUE_REQUIRED,
                'Périmètre des fiches matières à comparer : "parcours" (directes, mutualisées, maquette) ou "formation" (toutes les fiches de la mention).',
                'parcours'
            )
            ->addOption(
                'all-fiches',
                null,
                InputOption::VALUE_NONE,
                'Raccourci pour comparer toutes les fiches de la formation (--scope=formation).'
            )
            ->addOption(
                'json',
                null,
                InputOption::VALUE_NONE,
                'Exporter les résultats au format JSON.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ini_set('memory_limit', '1024M');

        $io = new SymfonyStyle($input, $output);
        $isJson = (bool) $input->getOption('json');

        if (!$isJson) {
            $io->title('Comparaison de maquette et fiches matières N-1 => N');
        }

        $parcoursOption = $input->getOption('parcours');
        $formationOption = $input->getOption('formation');
        $campagneOption = $input->getOption('campagne');
        $typeDiplomeOption = (string) $input->getOption('type-diplome');
        $onlyDiff = (bool) $input->getOption('only-diff');
        $showDetail = (bool) $input->getOption('detail');
        $scopeOption = (string) $input->getOption('scope');
        $allFiches = (bool) $input->getOption('all-fiches');
        $scope = ($allFiches || strcasecmp($scopeOption, 'formation') === 0) ? 'formation' : 'parcours';

        $campagne = null;
        if ($campagneOption !== null) {
            $campagne = $this->findCampagne((string) $campagneOption);
            if ($campagne === null) {
                if ($isJson) {
                    $output->writeln(json_encode(['error' => sprintf('Campagne "%s" introuvable.', (string) $campagneOption)], JSON_THROW_ON_ERROR));
                } else {
                    $io->error(sprintf('Campagne "%s" introuvable.', (string) $campagneOption));
                }
                return Command::FAILURE;
            }
        }

        $targetFormation = null;
        if ($formationOption !== null) {
            $targetFormation = is_numeric($formationOption)
                ? $this->formationRepository->find((int) $formationOption)
                : $this->formationRepository->findOneBy(['slug' => (string) $formationOption]);

            if ($targetFormation === null) {
                if ($isJson) {
                    $output->writeln(json_encode(['error' => sprintf('Formation "%s" introuvable.', (string) $formationOption)], JSON_THROW_ON_ERROR));
                } else {
                    $io->error(sprintf('Formation "%s" introuvable.', (string) $formationOption));
                }
                return Command::FAILURE;
            }
        }

        /** @var list<Parcours> $candidates */
        $candidates = [];
        if ($parcoursOption !== null) {
            $singleParcours = $this->parcoursRepository->find((int) $parcoursOption);
            if ($singleParcours === null) {
                if ($isJson) {
                    $output->writeln(json_encode(['error' => sprintf('Parcours ID %s introuvable.', (string) $parcoursOption)], JSON_THROW_ON_ERROR));
                } else {
                    $io->error(sprintf('Parcours ID %s introuvable.', (string) $parcoursOption));
                }
                return Command::FAILURE;
            }
            $candidates = [$singleParcours];
        } elseif ($targetFormation !== null) {
            $candidates = $targetFormation->getParcours()->toArray();
        } else {
            $candidates = $this->parcoursRepository->findAll();
        }

        $filteredParcours = [];
        foreach ($candidates as $p) {
            if (strcasecmp($typeDiplomeOption, 'all') !== 0) {
                $td = $p->getFormation()?->getTypeDiplome();
                if ($td === null) {
                    continue;
                }
                $libCourt = (string) $td->getLibelleCourt();
                $libLong = (string) $td->getLibelle();
                if (strcasecmp($libCourt, $typeDiplomeOption) !== 0 && strcasecmp($libLong, $typeDiplomeOption) !== 0) {
                    continue;
                }
            }

            if ($campagne !== null && !$this->belongsToCampagne($p, $campagne)) {
                continue;
            }

            $filteredParcours[] = $p;
        }

        if (count($filteredParcours) === 0) {
            if ($isJson) {
                $output->writeln(json_encode(['parcours' => [], 'message' => 'Aucun parcours correspondant trouvé.'], JSON_THROW_ON_ERROR));
            } else {
                $io->warning('Aucun parcours correspondant aux critères n\'a été trouvé.');
            }
            return Command::SUCCESS;
        }

        /** @var list<ParcoursN1ComparisonResult> $results */
        $results = [];
        $totalAnomaliesCount = 0;
        $totalErrorsCount = 0;
        $totalWarningsCount = 0;

        foreach ($filteredParcours as $p) {
            $res = $this->comparatorService->compare($p, scope: $scope);
            $errors = $res->countErrors();
            $warnings = $res->countWarnings();
            $totalErrorsCount += $errors;
            $totalWarningsCount += $warnings;
            $totalAnomaliesCount += ($errors + $warnings);

            if ($onlyDiff && !$res->hasDifferences()) {
                continue;
            }

            $results[] = $res;
        }

        if ($isJson) {
            $jsonOutput = [];
            foreach ($results as $res) {
                $anomaliesData = [];
                foreach ($res->anomalies as $a) {
                    $anomaliesData[] = [
                        'severity' => $a->severity,
                        'category' => $a->category,
                        'message' => $a->message,
                        'context' => $a->context,
                    ];
                }

                $jsonOutput[] = [
                    'parcours_n' => [
                        'id' => $res->parcoursN->getId(),
                        'libelle' => $res->parcoursN->getLibelle(),
                        'sigle' => $res->parcoursN->getSigle(),
                        'formation' => $res->parcoursN->getFormation()?->getDisplay(),
                    ],
                    'parcours_n1' => $res->parcoursN1 !== null ? [
                        'id' => $res->parcoursN1->getId(),
                        'libelle' => $res->parcoursN1->getLibelle(),
                        'sigle' => $res->parcoursN1->getSigle(),
                    ] : null,
                    'errors_count' => $res->countErrors(),
                    'warnings_count' => $res->countWarnings(),
                    'fiches_diff_count' => $res->countFichesDifferences(),
                    'ecs_diff_count' => $res->countEcsDifferences(),
                    'mutualisations_diff_count' => $res->countMutualisationsDifferences(),
                    'anomalies' => $anomaliesData,
                ];
            }

            $output->writeln(json_encode([
                'total_parcours' => count($results),
                'total_errors' => $totalErrorsCount,
                'total_warnings' => $totalWarningsCount,
                'data' => $jsonOutput,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        // Mode affichage console standard
        $this->renderConsoleSummary($io, $results);

        if ($showDetail) {
            foreach ($results as $res) {
                $this->renderDetailedParcours($io, $res);
            }
        } else {
            $io->note('Pour afficher le détail complet de chaque EC et fiche matière, ajoutez l\'option --detail.');
        }

        if ($totalErrorsCount > 0) {
            $io->error(sprintf(
                'Détection de %d anomalie(s) critique(s) et %d avertissement(s) sur %d parcours comparé(s).',
                $totalErrorsCount,
                $totalWarningsCount,
                count($results)
            ));
            $io->text('Astuce : Pour corriger ou restaurer les éléments écrasés depuis N-1, vous pouvez utiliser la commande :');
            $io->text('  <info>php bin/console app:restore-parcours-from-n1 --parcours=<ID> --dry-run</info>');
        } else {
            $io->success(sprintf('Analyse terminée sur %d parcours. Aucune anomalie critique détectée.', count($results)));
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<ParcoursN1ComparisonResult> $results
     */
    private function renderConsoleSummary(SymfonyStyle $io, array $results): void
    {
        $table = new Table($io);
        $table->setHeaders([
            'ID N',
            'Parcours N',
            'ID N-1',
            'Parcours N-1',
            'Erreurs',
            'Warnings',
            'Fiches Diff',
            'ECs Diff',
            'Mutu Diff',
            'Δ Heures Pres',
            'Δ ECTS',
        ]);

        foreach ($results as $res) {
            $pN = $res->parcoursN;
            $pN1 = $res->parcoursN1;

            $errors = $res->countErrors();
            $warnings = $res->countWarnings();
            $fichesDiff = $res->countFichesDifferences();
            $ecsDiff = $res->countEcsDifferences();
            $mutuDiff = $res->countMutualisationsDifferences();

            $errorStr = $errors > 0 ? sprintf('<error> %d </error>', $errors) : '<info>0</info>';
            $warnStr = $warnings > 0 ? sprintf('<comment> %d </comment>', $warnings) : '0';

            $totaux = $res->totauxGlobal;
            $diffHoursStr = $totaux !== null ? sprintf('%+.1fh', $totaux->diffTotalPres) : '-';
            $diffEctsStr = $totaux !== null ? sprintf('%+.1f', $totaux->diffEcts) : '-';

            $table->addRow([
                (string) $pN->getId(),
                (string) ($pN->getSigle() ?? $pN->getLibelle() ?? '-'),
                $pN1 !== null ? (string) $pN1->getId() : '<fg=red>Aucun</>',
                $pN1 !== null ? (string) ($pN1->getSigle() ?? $pN1->getLibelle() ?? '-') : '-',
                $errorStr,
                $warnStr,
                (string) $fichesDiff,
                (string) $ecsDiff,
                (string) $mutuDiff,
                $diffHoursStr,
                $diffEctsStr,
            ]);
        }

        $table->render();
    }

    private function renderDetailedParcours(SymfonyStyle $io, ParcoursN1ComparisonResult $res): void
    {
        $pN = $res->parcoursN;
        $pN1 = $res->parcoursN1;

        $io->section(sprintf(
            'Détail Parcours N #%d (%s) -> N-1 #%d (%s) [Formation: %s]',
            (int) $pN->getId(),
            (string) ($pN->getSigle() ?? $pN->getLibelle()),
            (int) ($pN1?->getId() ?? 0),
            (string) ($pN1?->getSigle() ?? $pN1?->getLibelle() ?? 'Aucun'),
            (string) ($pN->getFormation()?->getDisplay() ?? '-')
        ));

        // Afficher les anomalies
        if (count($res->anomalies) > 0) {
            $io->writeln('  <comment>Anomalies / Diagnostic :</comment>');
            foreach ($res->anomalies as $a) {
                $prefix = match ($a->severity) {
                    AnomalyItem::SEVERITY_ERROR => '<fg=red;options=bold>[ERREUR]</>',
                    AnomalyItem::SEVERITY_WARNING => '<fg=yellow>[ATTENTION]</>',
                    default => '<fg=cyan>[INFO]</>',
                };
                $io->writeln(sprintf('    %s %s', $prefix, $a->message));
            }
            $io->newLine();
        }

        // Afficher les fiches matières modifiées ou manquantes
        $fichesWithDiff = array_filter($res->fiches, static fn (FicheMatiereDiff $f) => $f->hasDifferences());
        if (count($fichesWithDiff) > 0) {
            $io->writeln('  <comment>Écarts sur les Fiches Matières :</comment>');
            $fTable = new Table($io);
            $fTable->setHeaders(['Statut', 'Sigle', 'Intitulé N-1', 'Intitulé N', 'Détail des écarts']);

            foreach ($fichesWithDiff as $f) {
                $statusLabel = match ($f->status) {
                    FicheMatiereDiff::STATUS_MISSING_IN_N => '<fg=red>MANQUE EN N</>',
                    FicheMatiereDiff::STATUS_ADDED_IN_N => '<fg=green>AJOUTÉE EN N</>',
                    FicheMatiereDiff::STATUS_UNLINKED => '<fg=yellow>LIEN BRISÉ</>',
                    default => '<comment>MODIFIÉE</comment>',
                };

                $diffsStr = [];
                foreach ($f->differences as $k => $d) {
                    if ($d->isDiff) {
                        $diffsStr[] = sprintf('%s: %s -> %s', $d->label, (string) $d->valN1, (string) $d->valN);
                    }
                }

                $fTable->addRow([
                    $statusLabel,
                    $f->sigle,
                    (string) ($f->libelleN1 ?? '-'),
                    (string) ($f->libelleN ?? '-'),
                    implode("\n", $diffsStr) ?: ($f->isOriginLinkBroken ? 'Lien d\'origine N-1 manquant' : '-'),
                ]);
            }
            $fTable->render();
            $io->newLine();
        }

        // Afficher les ECs présentant des anomalies
        $ecsWithIssues = [];
        foreach ($res->semestres as $sem) {
            foreach ($sem->ues as $ue) {
                foreach ($ue->ecs as $ec) {
                    if ($ec->hasDifferences()) {
                        $ecsWithIssues[] = ['sem' => $sem->ordre, 'ue' => $ue->libelle, 'ec' => $ec];
                    }
                }
            }
        }

        if (count($ecsWithIssues) > 0) {
            $io->writeln('  <comment>Écarts sur les Éléments Constitutifs (ECs) :</comment>');
            $eTable = new Table($io);
            $eTable->setHeaders(['Sem.', 'Code', 'Libellé', 'Statut', 'Fiche N-1', 'Fiche N', 'Anomalie / Écarts']);

            foreach ($ecsWithIssues as $item) {
                /** @var EcDiff $ec */
                $ec = $item['ec'];
                $statusLabel = match ($ec->status) {
                    EcDiff::STATUS_MISSING_IN_N => '<fg=red>MANQUANT EN N</>',
                    EcDiff::STATUS_ADDED_IN_N => '<fg=green>AJOUTÉ EN N</>',
                    default => '<comment>MODIFIÉ</comment>',
                };

                $fmN1Str = $ec->ficheN1 !== null ? sprintf('%s (%s)', (string) $ec->ficheN1->getSigle(), (string) $ec->ficheN1->getLibelle()) : '-';
                $fmNStr = $ec->ficheN !== null ? sprintf('%s (%s)', (string) $ec->ficheN->getSigle(), (string) $ec->ficheN->getLibelle()) : '<fg=red>AUCUNE</>';

                $issueStr = $ec->attachmentMessage ?? '';
                $diffsStr = [];
                foreach ($ec->differences as $d) {
                    if ($d->isDiff) {
                        $diffsStr[] = sprintf('%s: %s -> %s', $d->label, (string) $d->valN1, (string) $d->valN);
                    }
                }

                $msg = trim($issueStr . "\n" . implode("\n", $diffsStr));

                $eTable->addRow([
                    (string) $item['sem'],
                    (string) ($ec->code ?? '-'),
                    $ec->libelle,
                    $statusLabel,
                    $fmN1Str,
                    $fmNStr,
                    $msg ?: '-',
                ]);
            }
            $eTable->render();
            $io->newLine();
        }
    }

    private function findCampagne(string $search): ?CampagneCollecte
    {
        if (is_numeric($search)) {
            $campagne = $this->campagneCollecteRepository->find((int) $search);
            if ($campagne !== null) {
                return $campagne;
            }

            $campagne = $this->campagneCollecteRepository->findOneBy(['annee' => (int) $search]);
            if ($campagne !== null) {
                return $campagne;
            }
        }

        return $this->campagneCollecteRepository->findOneBy(['libelle' => $search]);
    }

    private function belongsToCampagne(Parcours $parcours, CampagneCollecte $campagne): bool
    {
        if ($parcours->getDpeParcoursPourCampagne($campagne) !== null) {
            return true;
        }

        $formation = $parcours->getFormation();
        if ($formation !== null) {
            if ($formation->getDpe() === $campagne) {
                return true;
            }
            foreach ($formation->getDpeParcours() as $dp) {
                if ($dp->getCampagneCollecte() === $campagne) {
                    return true;
                }
            }
            foreach ($formation->getDpeFormations() as $df) {
                if ($df->getCampagneCollecte() === $campagne) {
                    return true;
                }
            }
        }

        return false;
    }
}
