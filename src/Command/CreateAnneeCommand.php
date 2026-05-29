<?php

namespace App\Command;

use App\Entity\Annee;
use App\Repository\SemestreParcoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-annee',
    description: 'Add a short description for your command',
)]
class CreateAnneeCommand extends Command
{
    public function __construct(
        protected SemestreparcoursRepository $semestreparcoursRepository,
        protected EntityManagerInterface     $entityManager,
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

        $semestresParcours = $this->semestreparcoursRepository->findBy([], [
            'parcours' => 'ASC',
            'ordre' => 'ASC',
        ]);
        $tabAnnee = [];
        foreach ($semestresParcours as $semestreParcours) {
            //si ordre impair on ajoute l'année, si ordre pair on récupère l'année n-1. Il faut stocker les années dans le parcours
            $ordreSemestre = (int)$semestreParcours->getOrdre();

            // calcul de l'ordre de l'année : (1|2)->1, (3|4)->2, ...
            $ordreAnnee = intdiv($ordreSemestre + 1, 2);

            if ($semestreParcours->getOrdre() % 2 !== 0) {
                $annee = new Annee();
                $annee->setParcours($semestreParcours->getParcours());
                $annee->setOrdre($ordreAnnee);
                $annee->setCodeApogeeEtapeAnnee($semestreParcours->getCodeApogeeEtapeAnnee());
                $annee->setCodeApogeeEtapeVersion($semestreParcours->getCodeApogeeEtapeVersion());
                $tabAnnee[$semestreParcours->getParcours()?->getId()][$semestreParcours->getOrdre()] = $annee;
                $this->entityManager->persist($annee);
            } else {
                $annee = $tabAnnee[$semestreParcours->getParcours()?->getId()][$semestreParcours->getOrdre() - 1];
            }

            $annee->addParcoursSemestre($semestreParcours);
        }

        $this->entityManager->flush();

        $io->success('Années créées.');

        return Command::SUCCESS;
    }
}
