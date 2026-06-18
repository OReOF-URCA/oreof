<?php

namespace App\DTO;

class ParcoursAnneeComparaison
{
    public function __construct(
        public int $numeroAnnee,
        public ?AnneeData $anneeCourante = null,
        public ?AnneeData $anneePrecedente = null
    ) {
    }
}
