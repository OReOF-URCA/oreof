<?php

namespace App\Command;

use App\Entity\ElementConstitutif;
use App\Repository\ElementConstitutifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-ac',
    description: 'Add a short description for your command',
)]
class UpdateAcCommand extends Command
{
    public function __construct(
        protected ElementConstitutifRepository $elementConstitutifRepository,
        protected EntityManagerInterface       $entityManager
    ) {
        parent::__construct();
    }


    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ecs = $this->elementConstitutifRepository->findWithAc();

        /** @var ElementConstitutif $ec */
        foreach ($ecs as $ec) {
            $fiche = $ec->getFicheMatiere();
            if ($fiche !== null) {
                foreach ($fiche->getApprentissagesCritiques() as $ac) {
                    $fiche->addApprentissagesCritique($ac);
                }
            }
        }

        $this->entityManager->flush();

        $io->success('AC mis à jour.');

        return Command::SUCCESS;
    }
}
