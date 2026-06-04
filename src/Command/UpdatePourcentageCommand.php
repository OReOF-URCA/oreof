<?php

namespace App\Command;

use App\Repository\FicheMatiereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-pourcentage',
    description: 'Update the pourcentage field for all FicheMatiere entities',
)]
class UpdatePourcentageCommand extends Command
{
    private const BATCH_SIZE = 100;


    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected FicheMatiereRepository $ficheMatiereRepository,
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

        $totalFiches = $this->ficheMatiereRepository->count();
        $io->progressStart($totalFiches);

        for ($i = 0; $i < $totalFiches; $i += self::BATCH_SIZE) {
            $fiches = $this->ficheMatiereRepository->findBy([], null, self::BATCH_SIZE, $i);

            foreach ($fiches as $fiche) {
                $remplissage = $fiche->getRemplissage();
                $fiche->setRemplissage($remplissage);
                $io->progressAdvance();
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->progressFinish();
        $io->success('All fiches have been processed successfully.');

        return Command::SUCCESS;
    }
}
