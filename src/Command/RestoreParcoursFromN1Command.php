<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:restore-parcours-from-n1',
    description: 'Restaure le contenu d\'un parcours d\'une année N à partir de son parcours d\'origine N-1 (fiches matières, rattachements des ECs, mutualisations).',
)]
class RestoreParcoursFromN1Command extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected ParcoursRepository $parcoursRepository,
        protected FormationRepository $formationRepository,
        protected CampagneCollecteRepository $campagneCollecteRepository,
        protected FicheMatiereRepository $ficheMatiereRepository,
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
                'Filtrer par identifiant (ID) d\'un parcours spécifique de l\'année N.'
            )
            ->addOption(
                'formation',
                'f',
                InputOption::VALUE_REQUIRED,
                'Filtrer par identifiant (ID) ou slug d\'une formation de l\'année N.'
            )
            ->addOption(
                'campagne',
                'c',
                InputOption::VALUE_REQUIRED,
                'Filtrer par campagne de collecte de l\'année N (libellé ex: "2027-2028", année ex: 2027, ou ID).'
            )
            ->addOption(
                'type-diplome',
                't',
                InputOption::VALUE_REQUIRED,
                'Filtrer par type de diplôme (code court ou libellé, ex: "BUT", "LICENCE").'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Mode simulation : affiche les opérations sans enregistrer les modifications en base.'
            )
            ->addOption(
                'clean-orphans',
                null,
                InputOption::VALUE_NONE,
                'Supprimer les fiches matières de l\'année N qui n\'ont pas d\'origine N-1 et ne sont liées à aucun EC.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $io = new SymfonyStyle($input, $output);
        $io->title('Restauration des parcours N depuis leur origine N-1');

        $parcoursOption = $input->getOption('parcours');
        $formationOption = $input->getOption('formation');
        $campagneOption = $input->getOption('campagne');
        $typeDiplomeOption = $input->getOption('type-diplome');
        $dryRun = (bool) $input->getOption('dry-run');
        $cleanOrphans = (bool) $input->getOption('clean-orphans');

        if ($dryRun) {
            $io->note('Mode SIMULATION (dry-run). Aucune modification ne sera persistée en base de données.');
        }

        $campagne = null;
        if ($campagneOption !== null) {
            $campagne = $this->findCampagne((string) $campagneOption);
            if ($campagne === null) {
                $io->error(sprintf('Campagne "%s" introuvable.', (string) $campagneOption));
                return Command::FAILURE;
            }
            $io->text(sprintf('Campagne ciblée : %s (ID %d)', $campagne->getLibelle() ?? (string) $campagne->getAnnee(), $campagne->getId()));
        }

        $targetFormation = null;
        if ($formationOption !== null) {
            $targetFormation = is_numeric($formationOption)
                ? $this->formationRepository->find((int) $formationOption)
                : $this->formationRepository->findOneBy(['slug' => (string) $formationOption]);

            if ($targetFormation === null) {
                $io->error(sprintf('Formation "%s" introuvable.', (string) $formationOption));
                return Command::FAILURE;
            }
            $io->text(sprintf('Formation ciblée : %s (ID %d)', (string) $targetFormation->getSlug(), $targetFormation->getId()));
        }

        $candidateParcours = [];
        if ($parcoursOption !== null) {
            $singleParcours = $this->parcoursRepository->find((int) $parcoursOption);
            if ($singleParcours === null) {
                $io->error(sprintf('Parcours ID %s introuvable.', (string) $parcoursOption));
                return Command::FAILURE;
            }
            $candidateParcours = [$singleParcours];
        } elseif ($targetFormation !== null) {
            $candidateParcours = $targetFormation->getParcours()->toArray();
        } else {
            $candidateParcours = $this->parcoursRepository->findAll();
        }

        $totalParcours = 0;
        $totalFichesRestored = 0;
        $totalFichesLinked = 0;
        $totalFichesRecreated = 0;
        $totalEcsReattached = 0;
        $totalMutusRestored = 0;
        $totalMutusRemoved = 0;
        $totalOrphansRemoved = 0;

        foreach ($candidateParcours as $parcoursN) {
            if ($typeDiplomeOption !== null) {
                $td = $parcoursN->getFormation()?->getTypeDiplome();
                if ($td === null) {
                    continue;
                }
                $targetTd = trim((string) $typeDiplomeOption);
                if (strcasecmp((string) $td->getLibelleCourt(), $targetTd) !== 0 && strcasecmp((string) $td->getLibelle(), $targetTd) !== 0) {
                    continue;
                }
            }

            if ($campagne !== null && !$this->belongsToCampagne($parcoursN, $campagne)) {
                continue;
            }

            $parcoursN1 = $this->resolveParcoursN1($parcoursN);
            if ($parcoursN1 === null) {
                $io->writeln(sprintf(
                    '<comment>[Ignoré]</comment> Parcours #%d (%s) : aucun parcours d\'origine N-1 trouvé.',
                    (int) $parcoursN->getId(),
                    (string) ($parcoursN->getSigle() ?? $parcoursN->getLibelle())
                ));
                continue;
            }

            $totalParcours++;
            $io->section(sprintf(
                'Parcours N #%d (%s) -> N-1 #%d (%s) [Formation #%d: %s]',
                (int) $parcoursN->getId(),
                (string) ($parcoursN->getSigle() ?? $parcoursN->getLibelle()),
                (int) $parcoursN1->getId(),
                (string) ($parcoursN1->getSigle() ?? $parcoursN1->getLibelle()),
                (int) ($parcoursN->getFormation()?->getId() ?? 0),
                (string) ($parcoursN->getFormation()?->getSlug() ?? '')
            ));

            // 1. Remise à jour et réalignement des fiches matières du parcours
            $statsFiches = $this->restoreFichesMatieres($parcoursN, $parcoursN1, $dryRun);
            $totalFichesRestored += $statsFiches['restored'];
            $totalFichesLinked += $statsFiches['linked'];
            $totalFichesRecreated += $statsFiches['recreated'];

            // 2. Remise à jour des éléments constitutifs (ECs) et de leurs liaisons
            $statsEcs = $this->restoreEcs($parcoursN, $parcoursN1, $dryRun);
            $totalEcsReattached += $statsEcs['reattached'];

            // 3. Remise à jour des mutualisations de fiches
            $statsMutu = $this->restoreMutualisations($parcoursN, $parcoursN1, $dryRun);
            $totalMutusRestored += $statsMutu['restored'];
            $totalMutusRemoved += $statsMutu['removed'];

            // 4. Nettoyage des fiches orphelines sans origine si demandé
            if ($cleanOrphans) {
                $orphansCount = $this->cleanOrphanFiches($parcoursN, $dryRun);
                $totalOrphansRemoved += $orphansCount;
            }

            if (!$dryRun) {
                $this->entityManager->flush();
            }

            $io->text(sprintf(
                '  - Fiches restaurées: %d, Fiches re-liées à N-1: %d, Fiches recréées: %d, ECs ré-attachés: %d, Mutus créées: %d, Mutus parasites supprimées: %d',
                $statsFiches['restored'],
                $statsFiches['linked'],
                $statsFiches['recreated'],
                $statsEcs['reattached'],
                $statsMutu['restored'],
                $statsMutu['removed']
            ));
        }

        $io->success(sprintf(
            'Restauration terminée sur %d parcours : %d fiches restaurées, %d fiches re-liées à N-1, %d fiches recréées, %d ECs ré-attachés, %d mutualisations créées, %d mutualisations parasites supprimées, %d orphelines supprimées.',
            $totalParcours,
            $totalFichesRestored,
            $totalFichesLinked,
            $totalFichesRecreated,
            $totalEcsReattached,
            $totalMutusRestored,
            $totalMutusRemoved,
            $totalOrphansRemoved
        ));

        return Command::SUCCESS;
    }

    private function resolveParcoursN1(Parcours $parcoursN): ?Parcours
    {
        $n1 = $parcoursN->getParcoursOrigineCopie();
        if ($n1 !== null) {
            return $n1;
        }

        // Tenter de retrouver via la formation d'origine
        $formationN1 = $parcoursN->getFormation()?->getFormationOrigineCopie();
        if ($formationN1 !== null) {
            foreach ($formationN1->getParcours() as $cand) {
                if ($parcoursN->getSigle() !== null && strcasecmp((string) $cand->getSigle(), (string) $parcoursN->getSigle()) === 0) {
                    $parcoursN->setParcoursOrigineCopie($cand);
                    return $cand;
                }
                if ($parcoursN->getLibelle() !== null && strcasecmp((string) $cand->getLibelle(), (string) $parcoursN->getLibelle()) === 0) {
                    $parcoursN->setParcoursOrigineCopie($cand);
                    return $cand;
                }
            }
        }

        return null;
    }

    /**
     * @return array{restored: int, linked: int, recreated: int}
     */
    private function restoreFichesMatieres(Parcours $parcoursN, Parcours $parcoursN1, bool $dryRun): array
    {
        $stats = ['restored' => 0, 'linked' => 0, 'recreated' => 0];
        $fichesN1BySigle = [];
        $fichesN1ById = [];

        foreach ($parcoursN1->getFicheMatieres() as $fmN1) {
            if ($fmN1->getId() !== null) {
                $fichesN1ById[$fmN1->getId()] = $fmN1;
            }
            $sigle = strtoupper(trim((string) $fmN1->getSigle()));
            $type = (string) $fmN1->getTypeMatiere();
            if ($sigle !== '') {
                $fichesN1BySigle[$type . '_' . $sigle] = $fmN1;
            }
        }

        $fichesNOriginIds = [];

        // 1. Mettre à jour les fiches existantes de N
        foreach ($parcoursN->getFicheMatieres() as $fmN) {
            $fmN1 = $fmN->getFicheMatiereOrigineCopie();

            if ($fmN1 === null) {
                $sigle = strtoupper(trim((string) $fmN->getSigle()));
                $type = (string) $fmN->getTypeMatiere();
                $key = $type . '_' . $sigle;

                if (isset($fichesN1BySigle[$key])) {
                    $fmN1 = $fichesN1BySigle[$key];
                    if (!$dryRun) {
                        $fmN->setFicheMatiereOrigineCopie($fmN1);
                    }
                    $stats['linked']++;
                }
            }

            if ($fmN1 !== null) {
                $fichesNOriginIds[$fmN1->getId()] = true;
                if (!$dryRun) {
                    $this->copyFicheData($fmN1, $fmN);
                }
                $stats['restored']++;
            }
        }

        // 2. Vérifier si des fiches existaient en N-1 mais manquent totalement en N
        $campagneN = $parcoursN->getFormation()?->getDpe();
        foreach ($parcoursN1->getFicheMatieres() as $fmN1) {
            if ($fmN1->getId() === null || isset($fichesNOriginIds[$fmN1->getId()])) {
                continue;
            }

            // Vérifier si cette fiche n'a pas déjà été copiée sur un autre parcours de la formation
            $existingInFormation = false;
            foreach ($parcoursN->getFormation()?->getParcours() ?? [] as $otherP) {
                foreach ($otherP->getFicheMatieres() as $otherFm) {
                    if ($otherFm->getFicheMatiereOrigineCopie() === $fmN1) {
                        $existingInFormation = true;
                        break 2;
                    }
                }
            }

            if (!$existingInFormation) {
                if (!$dryRun) {
                    $newFm = clone $fmN1;
                    $newFm->prepareCloneForNewAnnee();
                    $newFm->setParcours($parcoursN);
                    $newFm->setCampagneCollecte($campagneN);
                    $newFm->setFicheMatiereOrigineCopie($fmN1);
                    $newFm->setSlug($this->generateUniqueSlug((string) $fmN1->getSlug()));
                    $this->copyFicheData($fmN1, $newFm);
                    $this->entityManager->persist($newFm);
                    $parcoursN->addFicheMatiere($newFm);
                }
                $stats['recreated']++;
            }
        }

        return $stats;
    }

    private function copyFicheData(FicheMatiere $source, FicheMatiere $target): void
    {
        $target->setLibelle($source->getLibelle());
        $target->setLibelleAnglais($source->getLibelleAnglais());
        $target->setDescription($source->getDescription());
        $target->setObjectifs($source->getObjectifs());
        $target->setSigle($source->getSigle());
        $target->setTypeMatiere($source->getTypeMatiere());

        // Volumes
        $target->setVolumeCmPresentiel($source->getVolumeCmPresentiel());
        $target->setVolumeTdPresentiel($source->getVolumeTdPresentiel());
        $target->setVolumeTpPresentiel($source->getVolumeTpPresentiel());
        $target->setVolumeTe($source->getVolumeTe());
        $target->setVolumeCmDistanciel($source->getVolumeCmDistanciel());
        $target->setVolumeTdDistanciel($source->getVolumeTdDistanciel());
        $target->setVolumeTpDistanciel($source->getVolumeTpDistanciel());

        // Langues et modalités
        $target->setLangueDispense($source->getLangueDispense());
        $target->setLangueSupport($source->getLangueSupport());
        $target->setModaliteEnseignementValue($source->getModaliteEnseignementValue());

        // Flags
        $target->setSansNote($source->isSansNote());
        $target->setSansHeures($source->isSansHeures());
        $target->setEcts($source->getEcts());
        $target->setQuitus($source->isQuitus());
        $target->setQuitusText($source->getQuitusText());
        $target->setCodeApogee($source->getCodeApogee());
        $target->setTypeApogee($source->getTypeApogee());
    }

    /**
     * @return array{reattached: int}
     */
    private function restoreEcs(Parcours $parcoursN, Parcours $parcoursN1, bool $dryRun): array
    {
        $stats = ['reattached' => 0];

        // Construire une table de correspondance fiche N-1 ID => Fiche N
        $fmMap = [];
        $fmObjMap = [];
        $candidates = $parcoursN->getFormation()?->getParcours()->toArray() ?? [$parcoursN];
        foreach ($candidates as $p) {
            foreach ($p->getFicheMatieres() as $f) {
                $orig = $f->getFicheMatiereOrigineCopie();
                if ($orig !== null) {
                    if ($orig->getId() !== null) {
                        $fmMap[$orig->getId()] = $f;
                    }
                    $fmObjMap[spl_object_id($orig)] = $f;
                }
            }
        }

        foreach ($parcoursN->getSemestreParcours() as $spN) {
            $semestreN = $spN->getSemestre();
            if ($semestreN === null) {
                continue;
            }

            foreach ($semestreN->getUes() as $ueN) {
                foreach ($ueN->getElementConstitutifs() as $ecN) {
                    if ($ecN->getParcours() !== null && $ecN->getParcours() !== $parcoursN) {
                        continue;
                    }

                    $ecN1 = $ecN->getEcOrigineCopie();
                    if ($ecN1 === null) {
                        continue;
                    }

                    $expectedFmN1 = $ecN1->getFicheMatiere();
                    if ($expectedFmN1 !== null) {
                        $targetFmN = null;
                        if ($expectedFmN1->getId() !== null) {
                            $targetFmN = $fmMap[$expectedFmN1->getId()] ?? null;
                        }
                        $targetFmN ??= $fmObjMap[spl_object_id($expectedFmN1)] ?? null;

                        if ($targetFmN !== null && $ecN->getFicheMatiere() !== $targetFmN) {
                            if (!$dryRun) {
                                $ecN->setFicheMatiere($targetFmN);
                            }
                            $stats['reattached']++;
                        }
                    }

                    if (!$dryRun) {
                        // Réaligner les données de l'EC depuis N-1
                        if ($ecN1->getLibelle() !== null) {
                            $ecN->setLibelle($ecN1->getLibelle());
                        }
                        $ecN->setEcts($ecN1->getEcts());
                        if ($ecN1->getOrdre() !== null) {
                            $ecN->setOrdre($ecN1->getOrdre());
                        }
                        if ($ecN1->getCode() !== null) {
                            $ecN->setCode($ecN1->getCode());
                        }
                        if ($ecN1->getNatureUeEc() !== null) {
                            $ecN->setNatureUeEc($ecN1->getNatureUeEc());
                        }
                        if ($ecN1->getTypeEc() !== null) {
                            $ecN->setTypeEc($ecN1->getTypeEc());
                        }
                        $ecN->setModaliteEnseignementValue($ecN1->getModaliteEnseignementValue());
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * @return array{restored: int, removed: int}
     */
    private function restoreMutualisations(Parcours $parcoursN, Parcours $parcoursN1, bool $dryRun): array
    {
        $stats = ['restored' => 0, 'removed' => 0];

        $fmMap = [];
        $fmObjMap = [];
        $candidates = $parcoursN->getFormation()?->getParcours()->toArray() ?? [$parcoursN];
        foreach ($candidates as $p) {
            foreach ($p->getFicheMatieres() as $f) {
                $orig = $f->getFicheMatiereOrigineCopie();
                if ($orig !== null) {
                    if ($orig->getId() !== null) {
                        $fmMap[$orig->getId()] = $f;
                    }
                    $fmObjMap[spl_object_id($orig)] = $f;
                }
            }
        }

        // 1. Rétablir les mutualisations qui existaient en N-1
        foreach ($parcoursN1->getFicheMatiereParcours() as $fmpN1) {
            $sourceFmN1 = $fmpN1->getFicheMatiere();
            if ($sourceFmN1 === null) {
                continue;
            }

            $targetFmN = null;
            if ($sourceFmN1->getId() !== null) {
                $targetFmN = $fmMap[$sourceFmN1->getId()] ?? null;
            }
            $targetFmN ??= $fmObjMap[spl_object_id($sourceFmN1)] ?? null;

            if ($targetFmN === null) {
                continue;
            }

            $alreadyLinked = false;
            foreach ($parcoursN->getFicheMatiereParcours() as $curFmp) {
                if ($curFmp->getFicheMatiere() === $targetFmN) {
                    $alreadyLinked = true;
                    break;
                }
            }

            if (!$alreadyLinked) {
                if (!$dryRun) {
                    $mut = new FicheMatiereMutualisable();
                    $mut->setFicheMatiere($targetFmN);
                    $mut->setParcours($parcoursN);
                    $targetFmN->setEnseignementMutualise(true);
                    $this->entityManager->persist($mut);
                    $parcoursN->addFicheMatiereParcour($mut);
                }
                $stats['restored']++;
            }
        }

        // 2. Nettoyer les mutualisations parasites pointant vers une autre formation
        foreach ($parcoursN->getFicheMatiereParcours()->toArray() as $fmpN) {
            $fm = $fmpN->getFicheMatiere();
            $sourceParcours = $fm?->getParcours();
            $isInternal = false;
            if ($sourceParcours === $parcoursN) {
                $isInternal = true;
            } elseif ($sourceParcours !== null && $parcoursN->getFormation() !== null && $sourceParcours->getFormation() === $parcoursN->getFormation()) {
                $isInternal = true;
            }

            if ($fm !== null && !$isInternal) {
                if (!$dryRun) {
                    $parcoursN->removeFicheMatiereParcour($fmpN);
                    $fm->removeFicheMatiereParcour($fmpN);
                    $this->entityManager->remove($fmpN);
                }
                $stats['removed']++;
            }
        }

        return $stats;
    }

    private function cleanOrphanFiches(Parcours $parcoursN, bool $dryRun): int
    {
        $removed = 0;
        $ecRepo = $this->entityManager->getRepository(ElementConstitutif::class);
        $mutuRepo = $this->entityManager->getRepository(FicheMatiereMutualisable::class);

        foreach ($parcoursN->getFicheMatieres()->toArray() as $fm) {
            if ($fm->getFicheMatiereOrigineCopie() !== null) {
                continue;
            }

            // Vérifier si un EC utilise cette fiche
            $ecsCount = $ecRepo->count(['ficheMatiere' => $fm]);
            if ($ecsCount > 0) {
                continue;
            }

            // Vérifier si une mutualisation utilise cette fiche
            $mutusCount = $mutuRepo->count(['ficheMatiere' => $fm]);
            if ($mutusCount > 0) {
                continue;
            }

            if (!$dryRun) {
                $parcoursN->removeFicheMatiere($fm);
                $this->entityManager->remove($fm);
            }
            $removed++;
        }

        return $removed;
    }

    private function generateUniqueSlug(string $baseSlug): string
    {
        $cleaned = preg_replace('/-copie-[a-z0-9]+$/', '', $baseSlug) ?? $baseSlug;
        $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
        $slug = sprintf('%s-copie-%s', $cleaned, $suffix);

        while ($this->ficheMatiereRepository->findOneBy(['slug' => $slug]) !== null) {
            $suffix = substr(bin2hex(random_bytes(4)), 0, 6);
            $slug = sprintf('%s-copie-%s', $cleaned, $suffix);
        }

        return $slug;
    }

    private function findCampagne(string $search): ?CampagneCollecte
    {
        $repo = $this->campagneCollecteRepository;

        $campagne = $repo->findOneBy(['libelle' => $search]);
        if ($campagne !== null) {
            return $campagne;
        }

        if (is_numeric($search)) {
            $campagne = $repo->findOneBy(['annee' => (int) $search]);
            if ($campagne !== null) {
                return $campagne;
            }

            $campagne = $repo->find((int) $search);
            if ($campagne !== null) {
                return $campagne;
            }
        }

        return null;
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

        foreach ($parcours->getDpeDemandes() as $demande) {
            if ($demande->getCampagneCollecte() === $campagne) {
                return true;
            }
        }

        return false;
    }
}
