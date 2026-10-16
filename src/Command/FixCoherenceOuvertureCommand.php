<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Enums\TypeModificationDpeEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:parcours:fix-coherence-ouverture',
    description: 'Corrige les incohérences d\'ouverture entre parcours et années sur une campagne (parcours fermé avec années ouvertes, ou parcours ouvert avec toutes ses années fermées).'
)]
final class FixCoherenceOuvertureCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
                'Libellé ou année de la campagne ciblée (ex: 2027-2028 ou 2027)',
                '2027-2028'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Applique réellement les modifications en base de données (sinon mode simulation).'
            )
            ->addOption(
                'action',
                null,
                InputOption::VALUE_REQUIRED,
                'Action spécifique à exécuter: "all" (défaut), "fermer-annees" (parcours fermés avec années ouvertes) ou "fermer-parcours" (parcours ouverts avec toutes années fermées)',
                'all'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $campagneArg = (string)$input->getOption('campagne');
        $apply = (bool)$input->getOption('apply');
        $action = (string)$input->getOption('action');

        $io->title('Correction des incohérences d\'ouverture parcours / années');

        if (!$apply) {
            $io->note('Mode SIMULATION (dry-run). Aucune modification ne sera enregistrée. Utilisez --apply pour appliquer.');
        }

        // 1. Recherche de la campagne
        $campagne = $this->findCampagne($campagneArg);
        if ($campagne === null) {
            $io->error(sprintf('Campagne "%s" introuvable.', $campagneArg));
            return Command::FAILURE;
        }

        $io->section(sprintf('Campagne ciblée : %s (ID %d)', $campagne->getLibelle() ?? (string)$campagne->getAnnee(), $campagne->getId()));

        $dpeParcoursRepository = $this->entityManager->getRepository(DpeParcours::class);
        /** @var list<DpeParcours> $dpeParcoursList */
        $dpeParcoursList = $dpeParcoursRepository->findBy(['campagneCollecte' => $campagne]);

        $io->text(sprintf('Total des parcours analysés sur la campagne : %d', count($dpeParcoursList)));

        $incoherencesParcoursFermes = [];
        $incoherencesAnneesFermees = [];

        foreach ($dpeParcoursList as $dpeParcours) {
            $parcours = $dpeParcours->getParcours();
            if ($parcours === null) {
                continue;
            }

            $isParcoursOuvert = $dpeParcours->isOuvert(); // true si ouvert, false si non-ouverture / fermeture
            $annees = $parcours->getAnnees();
            $totalAnnees = count($annees);

            $anneesOuvertes = [];
            foreach ($annees as $annee) {
                if ($annee->isOuvert() === true) {
                    $anneesOuvertes[] = $annee;
                }
            }

            // Incohérence 1 : Parcours fermé mais des années sont toujours ouvertes
            if (!$isParcoursOuvert && count($anneesOuvertes) > 0) {
                $incoherencesParcoursFermes[] = [
                    'parcours' => $parcours,
                    'dpeParcours' => $dpeParcours,
                    'anneesOuvertes' => $anneesOuvertes,
                ];
            }

            // Incohérence 2 : Parcours ouvert mais TOUTES ses années sont fermées (et il a au moins 1 année définie)
            if ($isParcoursOuvert && $totalAnnees > 0 && count($anneesOuvertes) === 0) {
                $incoherencesAnneesFermees[] = [
                    'parcours' => $parcours,
                    'dpeParcours' => $dpeParcours,
                    'totalAnnees' => $totalAnnees,
                ];
            }
        }

        $traiterAnnees = ($action === 'all' || $action === 'fermer-annees');
        $traiterParcours = ($action === 'all' || $action === 'fermer-parcours');

        // --- Affichage Incohérence 1 ---
        $io->section(sprintf('1. Parcours fermés mais avec des années ouvertes (%d trouvé(s))', count($incoherencesParcoursFermes)));
        if (!empty($incoherencesParcoursFermes)) {
            $rows = [];
            foreach ($incoherencesParcoursFermes as $item) {
                $p = $item['parcours'];
                $dp = $item['dpeParcours'];
                $ordresOuverts = array_map(static fn($a) => (string)$a->getOrdre(), $item['anneesOuvertes']);
                $etatReconduction = $dp->getEtatReconduction();
                $rows[] = [
                    $p->getId(),
                    $p->getLibelle(),
                    $p->getFormation()?->getDisplay() ?? '',
                    $etatReconduction !== null ? $etatReconduction->value : 'NON DÉFINI',
                    count($item['anneesOuvertes']),
                    implode(', ', $ordresOuverts),
                ];
            }
            $io->table(
                ['ID Parcours', 'Parcours', 'Formation', 'État Reconduction', 'Nb années ouv.', 'Ordres ouverts'],
                $rows
            );
        } else {
            $io->success('Aucune incohérence de type "parcours fermé avec années ouvertes".');
        }

        // --- Affichage Incohérence 2 ---
        $io->section(sprintf('2. Parcours ouverts dont TOUTES les années sont fermées (%d trouvé(s))', count($incoherencesAnneesFermees)));
        if (!empty($incoherencesAnneesFermees)) {
            $rows = [];
            foreach ($incoherencesAnneesFermees as $item) {
                $p = $item['parcours'];
                $dp = $item['dpeParcours'];
                $etatReconduction = $dp->getEtatReconduction();
                $rows[] = [
                    $p->getId(),
                    $p->getLibelle(),
                    $p->getFormation()?->getDisplay() ?? '',
                    $etatReconduction !== null ? $etatReconduction->value : 'OUVERT',
                    $item['totalAnnees'],
                ];
            }
            $io->table(
                ['ID Parcours', 'Parcours', 'Formation', 'État Reconduction', 'Nb années (toutes fermées)'],
                $rows
            );
        } else {
            $io->success('Aucune incohérence de type "parcours ouvert avec toutes années fermées".');
        }

        // --- Application des corrections si --apply ---
        $totalAnneesFermees = 0;
        $totalParcoursFermes = 0;

        if ($apply) {
            // Traitement Incohérence 1 : Fermer les années des parcours fermés
            if ($traiterAnnees && !empty($incoherencesParcoursFermes)) {
                foreach ($incoherencesParcoursFermes as $item) {
                    foreach ($item['anneesOuvertes'] as $annee) {
                        $annee->setIsOuvert(false);
                        $annee->setCapaciteAccueil(0);

                        // Désactiver les paramètres de plateformes pour cette campagne
                        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
                            if ($param->getCampagne() === $campagne) {
                                $param->setActive(false);
                                $param->setCapaciteGlobale(null);
                                $param->setCapaciteFi(null);
                                $param->setCapaciteAlternance(0);
                                $param->setCapaciteSpecifique(0);
                            }
                        }
                        $totalAnneesFermees++;
                    }
                }
            }

            // Traitement Incohérence 2 : Passer les parcours en non-ouverture si toutes leurs années sont fermées
            if ($traiterParcours && !empty($incoherencesAnneesFermees)) {
                foreach ($incoherencesAnneesFermees as $item) {
                    $dpeParcours = $item['dpeParcours'];
                    $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
                    $totalParcoursFermes++;
                }
            }

            $this->entityManager->flush();

            $io->success(sprintf(
                'Modifications appliquées avec succès : %d année(s) fermée(s) sur les parcours fermés, %d parcours passé(s) en NON_OUVERTURE.',
                $totalAnneesFermees,
                $totalParcoursFermes
            ));
        } else {
            $io->info(sprintf(
                'Bilan simulation : %d parcours fermés à synchroniser (années ouvertes à fermer), %d parcours ouverts à fermer (toutes années fermées).',
                count($incoherencesParcoursFermes),
                count($incoherencesAnneesFermees)
            ));
            $io->comment('Pour appliquer ces corrections, exécutez la commande avec l\'option --apply :');
            $io->text(sprintf('php bin/console app:parcours:fix-coherence-ouverture --campagne=%s --apply', $campagneArg));
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

        // Recherche par année numérique (ex: 2027)
        if (is_numeric($search)) {
            $campagne = $repo->findOneBy(['annee' => (int)$search]);
            if ($campagne !== null) {
                return $campagne;
            }

            // Recherche par ID
            $campagne = $repo->find((int)$search);
            if ($campagne !== null) {
                return $campagne;
            }
        }

        return null;
    }
}
