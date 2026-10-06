<?php

namespace App\Command;

use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;
use App\Entity\UserProfil;
use App\Repository\AnneeUniversitaireRepository;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recopie-profils',
    description: 'Recopie les profils (centres de gestion) des utilisateurs d\'une campagne de collecte vers une autre',
)]
class RecopieCentreCommand extends Command
{
    public function __construct(
        private readonly UserProfilRepository $userProfilRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
        private readonly AnneeUniversitaireRepository $anneeUniversitaireRepository,
        private readonly FormationRepository $formationRepository,
        private readonly DpeParcoursRepository $dpeParcoursRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('annee-depart', InputArgument::OPTIONAL, 'Année de départ (ex: 2024, 2024-2025 ou ID campagne)')
            ->addArgument('annee-arrivee', InputArgument::OPTIONAL, 'Année d\'arrivée (ex: 2025, 2025-2026 ou ID campagne)')
            ->addOption('annee-depart', null, InputOption::VALUE_REQUIRED, 'Année de départ (ex: 2024, 2024-2025 ou ID campagne)')
            ->addOption('annee-arrivee', null, InputOption::VALUE_REQUIRED, 'Année d\'arrivée (ex: 2025, 2025-2026 ou ID campagne)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule la recopie sans persister en base de données')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Exécute l\'opération sans demander de confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $anneeDepartInput = $input->getArgument('annee-depart') ?? $input->getOption('annee-depart');
        $anneeArriveeInput = $input->getArgument('annee-arrivee') ?? $input->getOption('annee-arrivee');

        if (!$anneeDepartInput) {
            $anneeDepartInput = $io->ask('Année universitaire ou campagne de départ (ex: 2024, 2024-2025 ou ID)');
        }

        if (!$anneeArriveeInput) {
            $anneeArriveeInput = $io->ask('Année universitaire ou campagne d\'arrivée (ex: 2025, 2025-2026 ou ID)');
        }

        if (!$anneeDepartInput || !$anneeArriveeInput) {
            $io->error('Les années de départ et d\'arrivée doivent être spécifiées.');
            return Command::INVALID;
        }

        [$anneeUnivDepart, $campagneDepart] = $this->resolveYearAndCampaign($anneeDepartInput);
        if (!$campagneDepart instanceof CampagneCollecte) {
            $io->error(sprintf('Impossible de trouver la campagne de collecte pour l\'année de départ : "%s".', (string) $anneeDepartInput));
            return Command::FAILURE;
        }

        [$anneeUnivArrivee, $campagneArrivee] = $this->resolveYearAndCampaign($anneeArriveeInput);
        if (!$campagneArrivee instanceof CampagneCollecte) {
            $io->error(sprintf('Impossible de trouver la campagne de collecte pour l\'année d\'arrivée : "%s".', (string) $anneeArriveeInput));
            return Command::FAILURE;
        }

        if ($campagneDepart->getId() === $campagneArrivee->getId()) {
            $io->error('La campagne de départ et la campagne d\'arrivée doivent être différentes.');
            return Command::FAILURE;
        }

        $isDryRun = (bool) $input->getOption('dry-run');

        $io->title('Recopie des profils utilisateurs');
        $io->horizontalTable(
            ['Paramètre', 'Année universitaire', 'Campagne de collecte', 'ID Campagne'],
            [
                [
                    'Départ',
                    $anneeUnivDepart?->getLibelle() ?? (string) ($anneeUnivDepart?->getAnnee() ?? 'N/A'),
                    $campagneDepart->getLibelle() ?? 'Sans libellé',
                    (string) $campagneDepart->getId(),
                ],
                [
                    'Arrivée',
                    $anneeUnivArrivee?->getLibelle() ?? (string) ($anneeUnivArrivee?->getAnnee() ?? 'N/A'),
                    $campagneArrivee->getLibelle() ?? 'Sans libellé',
                    (string) $campagneArrivee->getId(),
                ],
            ]
        );

        if ($isDryRun) {
            $io->note('Mode simulation (dry-run) activé : aucune modification ne sera enregistrée.');
        }

        if (!$input->getOption('force') && $input->isInteractive()) {
            if (!$io->confirm('Confirmez-vous la recopie des profils ?', true)) {
                $io->warning('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        // 1. Indexation des formations cibles (clé = id de la formation d'origine)
        $tFormations = [];
        $formationsCibles = $this->formationRepository->findBy(['dpe' => $campagneArrivee]);
        foreach ($formationsCibles as $formation) {
            $origineId = $formation->getFormationOrigineCopie()?->getId();
            if ($origineId !== null) {
                $tFormations[$origineId] = $formation;
            }
        }

        // 2. Indexation des parcours cibles (clé = id du parcours d'origine)
        $tParcours = [];
        $dpeParcoursCibles = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagneArrivee]);
        foreach ($dpeParcoursCibles as $dpeParcours) {
            $p = $dpeParcours->getParcours();
            $origineId = $p?->getParcoursOrigineCopie()?->getId();
            if ($p !== null && $origineId !== null) {
                $tParcours[$origineId] = $p;
            }
        }
        // Compléter avec les parcours des formations cibles au besoin
        foreach ($formationsCibles as $formation) {
            foreach ($formation->getParcours() as $p) {
                $origineId = $p->getParcoursOrigineCopie()?->getId();
                if ($origineId !== null && !isset($tParcours[$origineId])) {
                    $tParcours[$origineId] = $p;
                }
            }
        }

        // 3. Indexation des profils déjà existants sur la campagne d'arrivée pour éviter les doublons
        $existingTargetProfils = $this->userProfilRepository->findBy(['campagneCollecte' => $campagneArrivee]);
        $existingMap = [];
        foreach ($existingTargetProfils as $ep) {
            $key = sprintf(
                '%d_%d_%d_%d_%d_%d',
                $ep->getUser()?->getId() ?? 0,
                $ep->getProfil()?->getId() ?? 0,
                $ep->getFormation()?->getId() ?? 0,
                $ep->getParcours()?->getId() ?? 0,
                $ep->getComposante()?->getId() ?? 0,
                $ep->getEtablissement()?->getId() ?? 0
            );
            $existingMap[$key] = true;
        }

        // 4. Récupération des profils sources
        $centresSource = $this->userProfilRepository->findBy(['campagneCollecte' => $campagneDepart]);
        $totalSource = count($centresSource);

        if ($totalSource === 0) {
            $io->warning(sprintf('Aucun profil utilisateur trouvé sur la campagne de départ (ID: %d).', (int) $campagneDepart->getId()));
            return Command::SUCCESS;
        }

        $io->section(sprintf('Traitement de %d profil(s) source...', $totalSource));
        $progressBar = $io->createProgressBar($totalSource);
        $progressBar->start();

        $stats = [
            'formation' => 0,
            'parcours' => 0,
            'composante' => 0,
            'etablissement' => 0,
            'global' => 0,
            'skipped_duplicate' => 0,
            'skipped_unmapped' => 0,
        ];

        $batchSize = 100;
        $persistedCount = 0;

        foreach ($centresSource as $centre) {
            $user = $centre->getUser();
            $profil = $centre->getProfil();
            $formation = $centre->getFormation();
            $parcours = $centre->getParcours();
            $composante = $centre->getComposante();
            $etablissement = $centre->getEtablissement();

            if ($user === null || $profil === null) {
                $stats['skipped_unmapped']++;
                $progressBar->advance();
                continue;
            }

            $targetFormation = null;
            $targetParcours = null;
            $targetComposante = null;
            $targetEtablissement = null;
            $type = 'global';

            if ($formation !== null) {
                $formationId = $formation->getId();
                if ($formationId !== null && isset($tFormations[$formationId])) {
                    $targetFormation = $tFormations[$formationId];
                    $type = 'formation';
                } else {
                    $stats['skipped_unmapped']++;
                    $progressBar->advance();
                    continue;
                }
            } elseif ($parcours !== null) {
                $parcoursId = $parcours->getId();
                if ($parcoursId !== null && isset($tParcours[$parcoursId])) {
                    $targetParcours = $tParcours[$parcoursId];
                    $type = 'parcours';
                } else {
                    $stats['skipped_unmapped']++;
                    $progressBar->advance();
                    continue;
                }
            } elseif ($composante !== null) {
                $targetComposante = $composante;
                $type = 'composante';
            } elseif ($etablissement !== null) {
                $targetEtablissement = $etablissement;
                $type = 'etablissement';
            }

            $key = sprintf(
                '%d_%d_%d_%d_%d_%d',
                $user->getId() ?? 0,
                $profil->getId() ?? 0,
                $targetFormation?->getId() ?? 0,
                $targetParcours?->getId() ?? 0,
                $targetComposante?->getId() ?? 0,
                $targetEtablissement?->getId() ?? 0
            );

            if (isset($existingMap[$key])) {
                $stats['skipped_duplicate']++;
                $progressBar->advance();
                continue;
            }

            $newCentre = new UserProfil();
            $newCentre->setUser($user);
            $newCentre->setProfil($profil);
            $newCentre->setCampagneCollecte($campagneArrivee);

            if ($targetFormation !== null) {
                $newCentre->setFormation($targetFormation);
            }
            if ($targetParcours !== null) {
                $newCentre->setParcours($targetParcours);
            }
            if ($targetComposante !== null) {
                $newCentre->setComposante($targetComposante);
            }
            if ($targetEtablissement !== null) {
                $newCentre->setEtablissement($targetEtablissement);
            }

            if (!$isDryRun) {
                $this->entityManager->persist($newCentre);
                $persistedCount++;
                if ($persistedCount % $batchSize === 0) {
                    $this->entityManager->flush();
                }
            }

            $existingMap[$key] = true;
            $stats[$type]++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $totalCopiés = $stats['formation'] + $stats['parcours'] + $stats['composante'] + $stats['etablissement'] + $stats['global'];

        $io->table(
            ['Catégorie', 'Nombre'],
            [
                ['Profils source analysés', (string) $totalSource],
                ['Profils Formations créés', (string) $stats['formation']],
                ['Profils Parcours créés', (string) $stats['parcours']],
                ['Profils Composantes créés', (string) $stats['composante']],
                ['Profils Établissements créés', (string) $stats['etablissement']],
                ['Profils Globaux créés', (string) $stats['global']],
                ['Total profils recopiés', (string) $totalCopiés],
                ['Ignorés (déjà existants / doublons)', (string) $stats['skipped_duplicate']],
                ['Ignorés (formation/parcours non trouvés dans la cible)', (string) $stats['skipped_unmapped']],
            ]
        );

        if ($isDryRun) {
            $io->success(sprintf('[DRY-RUN] Simulation terminée avec succès : %d profils auraient été recopiés.', $totalCopiés));
        } else {
            $io->success(sprintf('Recopie terminée avec succès : %d profils recopiés sur la campagne d\'arrivée.', $totalCopiés));
        }

        return Command::SUCCESS;
    }

    /**
     * Recherche l'AnneeUniversitaire et la CampagneCollecte associées à partir d'une saisie utilisateur.
     * Accepte une année numérique (ex: 2024), un libellé (ex: 2024-2025), ou un ID de campagne/année.
     *
     * @return array{0: ?AnneeUniversitaire, 1: ?CampagneCollecte}
     */
    private function resolveYearAndCampaign(string|int|null $input): array
    {
        if ($input === null) {
            return [null, null];
        }

        $val = trim((string) $input);
        if ($val === '') {
            return [null, null];
        }

        $anneeUniv = null;
        $campagne = null;

        // 1. Recherche par AnneeUniversitaire
        if (is_numeric($val)) {
            $intVal = (int) $val;
            $anneeUniv = $this->anneeUniversitaireRepository->findOneBy(['annee' => $intVal])
                ?? $this->anneeUniversitaireRepository->find($intVal);
        }

        if ($anneeUniv === null) {
            $anneeUniv = $this->anneeUniversitaireRepository->findOneBy(['libelle' => $val]);
        }

        if ($anneeUniv === null && preg_match('/^(\d{4})[-\/](\d{4})$/', $val, $matches)) {
            $anneeUniv = $this->anneeUniversitaireRepository->findOneBy(['annee' => (int) $matches[1]]);
        }

        // Si AnneeUniversitaire trouvée, chercher la CampagneCollecte associée
        if ($anneeUniv instanceof AnneeUniversitaire) {
            $campagne = $this->campagneCollecteRepository->findOneBy(['annee_universitaire' => $anneeUniv])
                ?? ($anneeUniv->getDpes()->first() ?: null)
                ?? (is_int($anneeUniv->getAnnee()) ? $this->campagneCollecteRepository->findOneBy(['annee' => $anneeUniv->getAnnee()]) : null);

            return [$anneeUniv, $campagne];
        }

        // 2. Recherche par CampagneCollecte directement
        if (is_numeric($val)) {
            $intVal = (int) $val;
            $campagne = $this->campagneCollecteRepository->findOneBy(['annee' => $intVal])
                ?? $this->campagneCollecteRepository->find($intVal);
        }

        if ($campagne === null) {
            $campagne = $this->campagneCollecteRepository->findOneBy(['libelle' => $val]);
        }

        if ($campagne === null && preg_match('/^(\d{4})[-\/](\d{4})$/', $val, $matches)) {
            $campagne = $this->campagneCollecteRepository->findOneBy(['annee' => (int) $matches[1]]);
        }

        if ($campagne instanceof CampagneCollecte) {
            $anneeUniv = $campagne->getAnneeUniversitaire()
                ?? (is_int($campagne->getAnnee()) ? $this->anneeUniversitaireRepository->findOneBy(['annee' => $campagne->getAnnee()]) : null);

            return [$anneeUniv, $campagne];
        }

        return [null, null];
    }
}

