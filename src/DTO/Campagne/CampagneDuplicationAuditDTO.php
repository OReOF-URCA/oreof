<?php

declare(strict_types=1);

namespace App\DTO\Campagne;

use App\Entity\CampagneCollecte;

final class CampagneDuplicationAuditDTO
{
    public function __construct(
        public CampagneCollecte $sourceCampagne,
        public int $nbFormations = 0,
        public int $nbParcours = 0,
        public int $nbDpeParcours = 0,
        public int $nbDpeOuverts = 0,
        public int $nbDpeNonOuverts = 0,
        public int $nbBlocsCompetences = 0,
        public int $nbCompetences = 0,
        public int $nbButCompetences = 0,
        public int $nbButNiveaux = 0,
        public int $nbButApprentissagesCritiques = 0,
        public int $nbFichesMatieres = 0,
        public int $nbFichesMatieresMutualisables = 0,
        public int $nbSemestres = 0,
        public int $nbSemestresParcours = 0,
        public int $nbSemestresMutualisables = 0,
        public int $nbUes = 0,
        public int $nbUesMutualisables = 0,
        public int $nbElementsConstitutifs = 0,
        public int $nbContacts = 0,
        public int $nbMccc = 0,
        public int $nbDroits = 0,
        public array $warnings = [],
    ) {
    }

    public function getTotalEntitiesEstimate(): int
    {
        return $this->nbFormations
            + $this->nbParcours
            + $this->nbDpeParcours
            + $this->nbBlocsCompetences
            + $this->nbCompetences
            + $this->nbButCompetences
            + $this->nbButNiveaux
            + $this->nbButApprentissagesCritiques
            + $this->nbFichesMatieres
            + $this->nbFichesMatieresMutualisables
            + $this->nbSemestres
            + $this->nbSemestresParcours
            + $this->nbSemestresMutualisables
            + $this->nbUes
            + $this->nbUesMutualisables
            + $this->nbElementsConstitutifs
            + $this->nbContacts
            + $this->nbMccc;
    }
}
