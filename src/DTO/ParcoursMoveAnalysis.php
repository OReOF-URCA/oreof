<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Formation;
use App\Entity\Parcours;

final readonly class ParcoursMoveAnalysis
{
    public function __construct(
        public Parcours $parcours,
        public ?Formation $currentFormation,
        public bool $isParcoursDefaut,
        public bool $isSeulParcoursDeLaFormation,
        public int $nbDpeParcours,
        public int $nbDpeDemandes,
        public int $nbBlocCompetences,
        public int $nbChangeParcours,
        public int $nbSemestres,
    ) {
    }
}
