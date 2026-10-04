<?php

namespace App\Service\Apogee\Classes;

use App\Enums\Apogee\TypeHeureCE;
use Exception;

class TypeHeureDTO
{
    public string $codTypHeure;
    public string $nbrHeureElp;
    public string $normeGroupe;
    public string $nbrMinEtuOuvertureGroupeSupp;

    public function __construct(TypeHeureCE $codTypHeure, string $nbrHeureElp, string $normeGroupe){
        $this->codTypHeure = $codTypHeure->value;
        $this->nbrHeureElp = $nbrHeureElp;
        $this->normeGroupe = $normeGroupe;
    }
}
