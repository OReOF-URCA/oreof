<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Command/CalculVolumeHoraireCommand.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/04/2026 12:10
 */

namespace App\Command;

use App\Entity\VolumeHoraireParcours;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\VolumeHoraireParcoursRepository;
use App\Service\VolumeHoraireParcoursCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:calcul-volume-horaire',
    description: 'Calcule et stocke les volumes horaires (CM, TD, TP, TE, dist.) de tous les parcours d\'une campagne de collecte.',
)]
class CalculVolumeHoraireCommand extends Command
{
    public function __construct(
        private readonly CampagneCollecteRepository      $campagneCollecteRepository,
        private readonly DpeParcoursRepository           $dpeParcoursRepository,
        private readonly VolumeHoraireParcoursRepository $volumeHoraireParcoursRepository,
        private readonly VolumeHoraireParcoursCalculator $volumeHoraireParcoursCalculator,
        private readonly EntityManagerInterface          $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'campagne',
                InputArgument::OPTIONAL,
                'Identifiant (id) de la campagne de collecte. Si absent, traite la campagne par défaut.'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Traite toutes les campagnes de collecte, y compris la campagne courante.'
            )
            ->addOption(
                'historique',
                'H',
                InputOption::VALUE_NONE,
                'Traite toutes les campagnes précédentes (hors campagne par défaut). Option active par défaut si aucun argument n\'est fourni.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $campagnes = [];

        if ($input->getOption('all')) {
            $campagnes = $this->campagneCollecteRepository->findAll();
            $io->note(sprintf('%d campagne(s) trouvée(s).', count($campagnes)));
        } elseif ($input->getArgument('campagne') !== null) {
            $campagne = $this->campagneCollecteRepository->find((int)$input->getArgument('campagne'));
            if ($campagne === null) {
                $io->error(sprintf('Campagne avec l\'id "%s" introuvable.', $input->getArgument('campagne')));
                return Command::FAILURE;
            }
            $campagnes = [$campagne];
        } else {
            $campagnes = array_values(array_filter(
                $this->campagneCollecteRepository->findAll(),
                static fn($campagne) => $campagne->isDefaut() !== true
            ));

            if (count($campagnes) === 0) {
                $io->warning('Aucune campagne historique trouvée. Utilisez un identifiant de campagne ou --all.');
                return Command::SUCCESS;
            }

            $io->note(sprintf('%d campagne(s) historique(s) trouvée(s).', count($campagnes)));
        }

        $totalTraite = 0;
        $totalErreur = 0;

        foreach ($campagnes as $campagne) {
            $io->section(sprintf('Campagne : %s (id=%d, année=%d)', $campagne->getLibelle(), $campagne->getId(), $campagne->getAnnee()));

            $dpeParcoursList = $this->dpeParcoursRepository->findByCampagneCollecte($campagne);
            $io->progressStart(count($dpeParcoursList));
            $traiteCampagne = 0;

            foreach ($dpeParcoursList as $dpeParcours) {
                $parcours = $dpeParcours->getParcours();
                if ($parcours === null) {
                    $io->progressAdvance();
                    continue;
                }

                $formation = $parcours->getFormation();
                if ($formation === null) {
                    $io->progressAdvance();
                    continue;
                }

                try {
                    $volumeHoraire = $this->volumeHoraireParcoursRepository->findOneByParcoursAndCampagne($parcours, $campagne);
                    if ($volumeHoraire === null) {
                        $volumeHoraire = new VolumeHoraireParcours();
                    }

                    $volumeHoraire = $this->volumeHoraireParcoursCalculator->calculate($parcours, $campagne, $volumeHoraire);

                    $this->entityManager->persist($volumeHoraire);
                    $totalTraite++;
                    $traiteCampagne++;
                } catch (\Throwable $e) {
                    $totalErreur++;
                    $io->warning(sprintf(
                        'Erreur pour le parcours "%s" (id=%d) : %s',
                        $parcours->getLibelle(),
                        $parcours->getId(),
                        $e->getMessage()
                    ));
                }

                $io->progressAdvance();

                if ($traiteCampagne > 0 && $traiteCampagne % 50 === 0) {
                    $this->entityManager->flush();
                    gc_collect_cycles();
                }
            }

            $io->progressFinish();
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d parcours traité(s) avec succès, %d erreur(s).',
            $totalTraite,
            $totalErreur
        ));

        return Command::SUCCESS;
    }
}

