<?php

namespace App\Command;

use App\Entity\TimelineDate;
use App\Repository\CampagneCollecteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-timeline',
    description: 'Recopie des données des campagnes dans la table timeline',
)]
class UpdateTimelineCommand extends Command
{
    public function __construct(
        private EntityManagerInterface     $entityManager,
        private CampagneCollecteRepository $campagneCollecteRepository
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

        $campagnes = $this->campagneCollecteRepository->findAll();

        foreach ($campagnes as $campagne) {

            // créer les 5 timelineDate pour chaque campagne
            $d1 = new TimelineDate();
            $d1->setCampagneCollecte($campagne);
            $d1->setLibelle('Ouverture de la campagne de collecte');
            $d1->setIcone('fa-lock-open');
            $d1->setDate($campagne->getDateOuvertureDpe());
            $this->entityManager->persist($d1);

            $d2 = new TimelineDate();
            $d2->setCampagneCollecte($campagne);
            $d2->setLibelle('Cloture DPE');
            $d2->setIcone('fa-pencil');
            $d2->setDateDebut($campagne->getDateOuvertureDpe());
            $d2->setDate($campagne->getDateClotureDpe());
            $this->entityManager->persist($d2);

            $d3 = new TimelineDate();
            $d3->setCampagneCollecte($campagne);
            $d3->setLibelle('Date limite de transmission des projets DPE au SES');
            $d3->setIcone('fa-paper-plane');
            $d3->setDate($campagne->getDateTransmissionSes());
            $this->entityManager->persist($d3);

            $d4 = new TimelineDate();
            $d4->setCampagneCollecte($campagne);
            $d4->setLibelle('Date prévisionnelle de passage en CFVU');
            $d4->setIcone('fa-shield-check');
            $d4->setDate($campagne->getDateCfvu() ?? new \DateTime());
            $this->entityManager->persist($d4);

            $d5 = new TimelineDate();
            $d5->setCampagneCollecte($campagne);
            $d5->setLibelle('Publication');
            $d5->setDate($campagne->getDatePublication());
            $d5->setIcone('fa-bullhorn');
            $this->entityManager->persist($d5);
            $this->entityManager->flush();
        }

        $io->success('Dates recopiées dans la table timeline.');

        return Command::SUCCESS;
    }
}
