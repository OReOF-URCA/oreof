<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/EcOrdre.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:10
 */

namespace App\Classes;

use App\Entity\Semestre;
use App\Entity\Ue;
use App\Repository\UeRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class UeOrdre
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UeRepository           $ueRepository
    ) {
    }

    public function deplacerUe(Ue $ue, string $sens): bool
    {
        //modifie l'ordre de la ressource
        $ordreInitial = $ue->getOrdre();

        if ($ordreInitial === 1 && $sens === 'up') {
            return false;
        }

        if ($sens === 'up') {
            $ordreDestination = $ordreInitial - 1;
        } else {
            $ordreDestination = $ordreInitial + 1;
        }

        if ($ue->getUeParent() === null) {
            return $this->inverseUe($ordreInitial, $ordreDestination, $ue, $ue->getSemestre());
        }

        return $this->inverseUeEnfant($ordreInitial, $ordreDestination, $ue);
    }

    public function deplacerSubUe(Ue $ue, string $sens): bool
    {
        $ordreInitial = $ue->getOrdre();

        if ($ordreInitial === 1 && $sens === 'up') {
            return false;
        }

        if ($sens === 'up') {
            $ordreDestination = $ordreInitial - 1;
        } else {
            $ordreDestination = $ordreInitial + 1;
        }

        //récupère toutes les ressources à déplacer
        return $this->inverseSubOrdreUe($ordreInitial, $ordreDestination, $ue);
    }

    private function inverseUe(
        ?int     $ordreInitial,
        ?int     $ordreDestination,
        Ue       $ue,
        Semestre $semestre
    ): bool {
        $ues = $this->ueRepository->findByUeOrdre($ordreDestination, $semestre);

        $ue->setOrdre($ordreDestination);

        foreach ($ues as $u) {
            $u->setOrdre($ordreInitial);
        }

        $this->entityManager->flush();

        return true;
    }

    private function inverseUeEnfant(
        ?int $ordreInitial,
        ?int $ordreDestination,
        Ue   $ue,
    ): bool {
        $ues = $this->ueRepository->findByUeSubOrdre(
            $ordreDestination,
            $ue->getUeParent()
        );

        $ue->setOrdre($ordreDestination);

        foreach ($ues as $u) {
            $u->setOrdre($ordreInitial);
        }

        $this->entityManager->flush();

        return true;
    }


    private function inverseSubOrdreUe(
        ?int $ordreInitial,
        ?int $ordreDestination,
        Ue   $ue,
    ): bool {
        // on inverse les sous-ordres
        $ues = $this->ueRepository->findByUeSubOrdre($ordreDestination, $ue->getUeParent());
        $ue->setOrdre($ordreDestination);

        foreach ($ues as $subUe) {
            $subUe->setOrdre($ordreInitial);
        }

        $this->entityManager->flush();

        return true;
    }

    public function renumeroterSubUE(?int $ordre, Ue $ueParent, Semestre $semestre): void
    {
        $ues = $this->ueRepository->findBySemestreSubOrdreAfter($ordre, $semestre, $ueParent);

        $i = $ordre;
        foreach ($ues as $ue) {
            $ue->setOrdre($i);
            ++$i;
        }

        $this->entityManager->flush();
    }

    public function renumeroterUE(?int $ordre, ?Semestre $semestre): void
    {
        $ues = $this->ueRepository->findBySemestreOrdreAfter($ordre, $semestre);

        $i = $ordre;
        foreach ($ues as $ue) {
            $ue->setOrdre($i);
            ++$i;
        }

        $this->entityManager->flush();
    }

    public function decaleSousOrdre(Ue $ueParent, ?int $ordre, Ue $ue): void
    {
        foreach ($ueParent->getUeEnfants() as $enfant) {
            if ($enfant->getOrdre() >= $ordre && $enfant !== $ue) {
                $enfant->setOrdre($enfant->getOrdre() + 1);
            }
        }
        $this->entityManager->flush();
    }
}
