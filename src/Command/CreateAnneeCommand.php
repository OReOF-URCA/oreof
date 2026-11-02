<?php

namespace App\Command;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-annee',
    description: 'Crée les années manquantes (Annee) et les associe aux semestres des parcours (avec filtres optionnels campagne et type de diplôme).',
)]
class CreateAnneeCommand extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'campagne',
                'c',
                InputOption::VALUE_REQUIRED,
                'Filtrer par campagne de collecte (libellé ex: "2027-2028", année ex: 2027, ou ID).'
            )
            ->addOption(
                'type-diplome',
                't',
                InputOption::VALUE_REQUIRED,
                'Filtrer par type de diplôme (code court ou libellé, ex: "BUT", "LICENCE").'
            )
            ->addOption(
                'parcours',
                'p',
                InputOption::VALUE_REQUIRED,
                'Filtrer par identifiant (ID) d\'un parcours spécifique.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Mode simulation : affiche ce qui serait créé sans modifier la base de données.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Création et rattachement des années manquantes');

        $campagneOption = $input->getOption('campagne');
        $typeDiplomeOption = $input->getOption('type-diplome');
        $parcoursOption = $input->getOption('parcours');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode SIMULATION (dry-run). Aucune modification ne sera enregistrée en base de données.');
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

        if ($typeDiplomeOption !== null) {
            $io->text(sprintf('Type de diplôme ciblé : %s', (string) $typeDiplomeOption));
        }

        if ($parcoursOption !== null) {
            $io->text(sprintf('Parcours ciblé ID : %s', (string) $parcoursOption));
        }

        $parcoursRepo = $this->entityManager->getRepository(Parcours::class);
        /** @var list<Parcours> $allParcours */
        $allParcours = $parcoursRepo->findAll();

        $parcoursCount = 0;
        $createdCount = 0;
        $linkedCount = 0;

        foreach ($allParcours as $parcours) {
            // Filtrer par ID de parcours si spécifié
            if ($parcoursOption !== null && (int) $parcours->getId() !== (int) $parcoursOption) {
                continue;
            }

            // Filtrer par type de diplôme si spécifié
            if ($typeDiplomeOption !== null) {
                $td = $parcours->getFormation()?->getTypeDiplome();
                if ($td === null) {
                    continue;
                }
                $targetTd = trim((string) $typeDiplomeOption);
                $codeCourt = (string) $td->getLibelleCourt();
                $libelle = (string) $td->getLibelle();
                if (strcasecmp($codeCourt, $targetTd) !== 0 && strcasecmp($libelle, $targetTd) !== 0) {
                    continue;
                }
            }

            // Filtrer par campagne si spécifiée
            if ($campagne !== null && !$this->belongsToCampagne($parcours, $campagne)) {
                continue;
            }

            $parcoursCount++;

            $existingAnnees = [];
            foreach ($parcours->getAnnees() as $a) {
                if ($a->getOrdre() !== null) {
                    $existingAnnees[$a->getOrdre()] = $a;
                }
            }

            // 1. S'assurer que les années prévues pour le diplôme existent (ex: [1, 2, 3] en B.U.T.)
            foreach ($parcours->getOrdresAnneesPossibles() as $ordreAnnee) {
                if (!isset($existingAnnees[$ordreAnnee])) {
                    $annee = new Annee();
                    $annee->setParcours($parcours);
                    $annee->setOrdre($ordreAnnee);
                    $annee->setIsOuvert(true);
                    $annee->setCapaciteAccueil(0);
                    $annee->setHasCapacite(true);
                    $annee->setIsProposeRecrutement(true);
                    $annee->setRegimeInscription($parcours->getRegimeInscription());
                    if (!$dryRun) {
                        $this->entityManager->persist($annee);
                        $parcours->addAnnee($annee);
                    }
                    $existingAnnees[$ordreAnnee] = $annee;
                    $createdCount++;
                }
            }

            // 2. Associer chaque semestre à son année correspondante
            foreach ($parcours->getSemestreParcours() as $sp) {
                $ordreSemestre = (int) $sp->getOrdre();
                if ($ordreSemestre <= 0) {
                    continue;
                }
                $ordreAnnee = (int) ceil($ordreSemestre / 2);

                if (!isset($existingAnnees[$ordreAnnee])) {
                    $annee = new Annee();
                    $annee->setParcours($parcours);
                    $annee->setOrdre($ordreAnnee);
                    $annee->setIsOuvert(true);
                    $annee->setCapaciteAccueil(0);
                    $annee->setHasCapacite(true);
                    $annee->setIsProposeRecrutement(true);
                    $annee->setRegimeInscription($parcours->getRegimeInscription());
                    $annee->setCodeApogeeEtapeAnnee($sp->getCodeApogeeEtapeAnnee());
                    $annee->setCodeApogeeEtapeVersion($sp->getCodeApogeeEtapeVersion());
                    if (!$dryRun) {
                        $this->entityManager->persist($annee);
                        $parcours->addAnnee($annee);
                    }
                    $existingAnnees[$ordreAnnee] = $annee;
                    $createdCount++;
                }

                $annee = $existingAnnees[$ordreAnnee];
                if ($sp->getAnnee() !== $annee) {
                    if (!$dryRun) {
                        $sp->setAnnee($annee);
                        $annee->addParcoursSemestre($sp);
                    }
                    $linkedCount++;
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $io->success(sprintf(
                'Traitement terminé : %d parcours traité(s), %d année(s) créée(s), %d semestre(s) rattaché(s).',
                $parcoursCount,
                $createdCount,
                $linkedCount
            ));
        } else {
            $io->info(sprintf(
                'Simulation terminée : %d parcours analysé(s), %d année(s) à créer, %d semestre(s) à rattacher.',
                $parcoursCount,
                $createdCount,
                $linkedCount
            ));
        }

        return Command::SUCCESS;
    }

    private function findCampagne(string $search): ?CampagneCollecte
    {
        $repo = $this->entityManager->getRepository(CampagneCollecte::class);

        // Recherche par libellé exact (ex: "2027-2028")
        $campagne = $repo->findOneBy(['libelle' => $search]);
        if ($campagne !== null) {
            return $campagne;
        }

        // Si format "2027-2028" ou "2027/2028"
        if (preg_match('/^(\d{4})[-\/](\d{4})$/', $search, $matches)) {
            $campagne = $repo->findOneBy(['annee' => (int) $matches[1]]);
            if ($campagne !== null) {
                return $campagne;
            }
        }

        // Recherche par année numérique (ex: 2027)
        if (is_numeric($search)) {
            $campagne = $repo->findOneBy(['annee' => (int) $search]);
            if ($campagne !== null) {
                return $campagne;
            }

            // Recherche par ID
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

