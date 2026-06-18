<?php

namespace App\Service;

use App\DTO\AnneeData;
use App\DTO\ParcoursAnneeComparaison;
use App\Entity\Parcours;

class ParcoursComparaisonService
{
    public function construireTableauComparaison(Parcours $parcoursCourant): array
    {
        $parcoursOrigine = $parcoursCourant->getParcoursOrigine();
        $nbAnnees = $parcoursCourant->getFormation()?->getTypeDiplome()?->getNbAnnee() ?? 0;

        $tableau = [];

        for ($i = 1; $i <= $nbAnnees; $i++) {
            $anneeCourante = $this->getAnneeByOrdre($parcoursCourant, $i);
            $anneePrecedente = $parcoursOrigine ? $this->getAnneeByOrdre($parcoursOrigine, $i) : null;

            $tableau[$i] = new ParcoursAnneeComparaison(
                numeroAnnee: $i,
                anneeCourante: AnneeData::fromAnnee($anneeCourante),
                anneePrecedente: AnneeData::fromAnnee($anneePrecedente)
            );
        }

        return $tableau;
    }

    private function getAnneeByOrdre(Parcours $parcours, int $ordre): ?\App\Entity\Annee
    {
        foreach ($parcours->getAnnees() as $annee) {
            if ($annee->getOrdre() === $ordre) {
                return $annee;
            }
        }

        return null;
    }
}
