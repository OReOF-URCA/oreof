<?php

namespace App\Command;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\UserProfil;
use App\Repository\CampagneCollecteRepository;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Repository\ProfilRepository;
use App\Repository\SemestreParcoursRepository;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-droits',
    description: 'Mise à jour des profils selon les responsabilités',
)]
class UpdateDroitsCommand extends Command
{

    public int $idCampagne = 3;
    public ?CampagneCollecte $campagne;

    public function __construct(
        protected FormationRepository $formationRepository,
        protected UserProfilRepository       $userProfilRepository,
        protected ProfilRepository           $profilRepository,
        protected DpeParcoursRepository      $dpeParcoursRepository,
        protected CampagneCollecteRepository $campagneCollecteRepository,
        protected EntityManagerInterface     $entityManager,
    )
    {
        parent::__construct();
        $this->campagne = $this->campagneCollecteRepository->find($this->idCampagne);
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $profilRf = $this->profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);
        $profilCoRf = $this->profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);
        $profilRp = $this->profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
        $profilCoRp = $this->profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);

        $profils = $this->userProfilRepository->findBy(['profil' => $profilRf, 'campagneCollecte' => $this->idCampagne]);
        foreach ($profils as $profil) {
            $this->entityManager->remove($profil);
        }

        $profils = $this->userProfilRepository->findBy(['profil' => $profilCoRf, 'campagneCollecte' => $this->idCampagne]);
        foreach ($profils as $profil) {
            $this->entityManager->remove($profil);
        }

        $profils = $this->userProfilRepository->findBy(['profil' => $profilRp, 'campagneCollecte' => $this->idCampagne]);
        foreach ($profils as $profil) {
            $this->entityManager->remove($profil);
        }

        $profils = $this->userProfilRepository->findBy(['profil' => $profilCoRp, 'campagneCollecte' => $this->idCampagne]);
        foreach ($profils as $profil) {
            $this->entityManager->remove($profil);
        }
        $this->entityManager->flush();

        $dpes = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $this->idCampagne]);
        foreach ($dpes as $dpe) {
            if ($dpe->getParcours()->getRespParcours() !== null) {
                // RP
                $prRp = new UserProfil();
                $prRp->setParcours($dpe->getParcours());
                $prRp->setProfil($profilRp);
                $prRp->setCampagneCollecte($this->campagne);
                $prRp->setUser($dpe->getParcours()->getRespParcours());
                $this->entityManager->persist($prRp);
            }

            if ($dpe->getParcours()->getCoResponsable() !== null) {
                // RP
                $prRp = new UserProfil();
                $prRp->setParcours($dpe->getParcours());
                $prRp->setProfil($profilCoRp);
                $prRp->setCampagneCollecte($this->campagne);
                $prRp->setUser($dpe->getParcours()->getCoResponsable());
                $this->entityManager->persist($prRp);
            }
        }

        $formations = $this->formationRepository->findBy(['dpe' => $this->idCampagne]);
        foreach ($formations as $formation) {
            if ($formation->getResponsableMention() !== null) {
                // RP
                $prRp = new UserProfil();
                $prRp->setFormation($formation);
                $prRp->setProfil($profilRf);
                $prRp->setCampagneCollecte($this->campagne);
                $prRp->setUser($formation->getResponsableMention());
                $this->entityManager->persist($prRp);
            }

            if ($formation->getCoResponsable() !== null) {
                // RP
                $prRp = new UserProfil();
                $prRp->setFormation($formation);
                $prRp->setProfil($profilCoRf);
                $prRp->setCampagneCollecte($this->campagne);
                $prRp->setUser($formation->getCoResponsable());
                $this->entityManager->persist($prRp);
            }
        }

        $this->entityManager->flush();


        // on efface tous les responsables Parcours, formation de la campagne indiquée, on parcours tous les dpe de la campagne et on remets les profils

        $io->success('Profils mis à jour.');

        return Command::SUCCESS;
    }
}
