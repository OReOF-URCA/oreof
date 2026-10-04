<?php

namespace App\Command;

use App\Entity\UserProfil;
use App\Repository\CampagneCollecteRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recopie-profils',
    description: 'Recopie les centres des utilisateurs lors de la recopie d\'une campagne sur une autre',
)]
class RecopieCentreCommand extends Command
{
    public function __construct(
        private UserProfilRepository       $userCentreRepository,
        private EntityManagerInterface     $entityManager,
        private CampagneCollecteRepository $campagneCollecteRepository,
        private FormationRepository        $formationRepository,
        private ParcoursRepository         $parcoursRepository
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $campagneCollecte = $this->campagneCollecteRepository->find(3);

        $tFormations = [];
        $formations = $this->formationRepository->findAll();
        foreach ($formations as $formation) {
            // clé d'origine, nouvelle formation
            $tFormations[$formation->getFormationOrigineCopie()?->getId()] = $formation;
        }

        $tParcours = [];
        $parcours = $this->parcoursRepository->findAll();
        foreach ($parcours as $p) {
            $tParcours[$p->getParcoursOrigineCopie()?->getId()] = $p;
        }

        $centres = $this->userCentreRepository->findBy(['campagneCollecte' => 2]);
        $io->progressStart(count($centres));
        foreach ($centres as $centre) {
            $formation = $centre->getFormation();
            $parcours = $centre->getParcours();
            if ($formation !== null) {
                $formationId = $formation->getId();
                if ($formationId !== null && isset($tFormations[$formationId])) {
                    $newCentre = new UserProfil();
                    $newCentre->setUser($centre->getUser());
                    $newCentre->setFormation($tFormations[$formationId]);
                    $newCentre->setProfil($centre->getProfil());
                    $newCentre->setCampagneCollecte($campagneCollecte);
                    $this->entityManager->persist($newCentre);
                    unset($newCentre);
                }
            } elseif ($parcours !== null) {
                $parcoursId = $parcours->getId();
                if ($parcoursId !== null && isset($tParcours[$parcoursId])) {
                    $newCentre = new UserProfil();
                    $newCentre->setUser($centre->getUser());
                    $newCentre->setParcours($tParcours[$parcoursId]);
                    $newCentre->setProfil($centre->getProfil());
                    $newCentre->setCampagneCollecte($campagneCollecte);
                    $this->entityManager->persist($newCentre);
                    unset($newCentre);
                }
            } elseif ($centre->getComposante() !== null) {
                $newCentre = new UserProfil();
                $newCentre->setUser($centre->getUser());
                $newCentre->setComposante($centre->getComposante());
                $newCentre->setProfil($centre->getProfil());
                $newCentre->setCampagneCollecte($campagneCollecte);
                $this->entityManager->persist($newCentre);
                unset($newCentre);
            } elseif ($centre->getEtablissement() !== null) {
                $newCentre = new UserProfil();
                $newCentre->setUser($centre->getUser());
                $newCentre->setEtablissement($centre->getEtablissement());
                $newCentre->setProfil($centre->getProfil());
                $newCentre->setCampagneCollecte($campagneCollecte);
                $this->entityManager->persist($newCentre);
                unset($newCentre);
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();

        $io->success('Profils recopiés sur la nouvelle campagne de collecte.');

        return Command::SUCCESS;
    }
}
