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
        protected SemestreParcoursRepository $semestreparcoursRepository,
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

        $parcoursRepo = $this->entityManager->getRepository(\App\Entity\Parcours::class);
        $allParcours = $parcoursRepo->findAll();
        $createdCount = 0;
        $linkedCount = 0;

        foreach ($allParcours as $parcours) {
            $existingAnnees = [];
            foreach ($parcours->getAnnees() as $a) {
                $existingAnnees[$a->getOrdre()] = $a;
            }

            foreach ($parcours->getSemestreParcours() as $sp) {
                $ordreSemestre = (int) $sp->getOrdre();
                if ($ordreSemestre <= 0) {
                    continue;
                }
                $ordreAnnee = intdiv($ordreSemestre + 1, 2);

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
                    $this->entityManager->persist($annee);
                    $parcours->addAnnee($annee);
                    $existingAnnees[$ordreAnnee] = $annee;
                    $createdCount++;
                }

                $annee = $existingAnnees[$ordreAnnee];
                if ($sp->getAnnee() !== $annee) {
                    $sp->setAnnee($annee);
                    $annee->addParcoursSemestre($sp);
                    $linkedCount++;
                }
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Années créées : %d, semestres liés : %d.', $createdCount, $linkedCount));

        return Command::SUCCESS;
    }
}
