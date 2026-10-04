<?php

namespace App\DTO;

/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/StatsFichesMatieresParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/12/2023 11:42
 */


use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;

class StatsFichesMatieresParcours
{
    public int $nbFiches = 0;
    public int $nbFichesValidees = 0; //par central
    public int $nbFichesNonValideesSes = 0;
    public int $nbFichesCompletes = 0;
    public int $nbFichesNonValidees = 0;

    public int $nbFichesPubliees = 0;
    public int $nbEnCoursRedaction = 0;

    public function __construct(public Parcours $parcours)
    {
    }

    public function addEc(ElementConstitutif $ec, bool $raccroche) :void
    {
        if ($raccroche === false) {
            $ficheMatiere = $ec->getFicheMatiere();
            if ($ficheMatiere !== null && $ficheMatiere->getParcours()?->getId() === $this->parcours->getId() && !$ec->getNatureUeEc()->isLibre()) {
                $this->addStasEc($ficheMatiere);
            }
        }
    }

    private function addStasEc(FicheMatiere $ficheMatiere): void
    {
        $this->nbFiches++;
        if ($ficheMatiere->getRemplissage()->isFull()) {
            $this->nbFichesCompletes++;
        }
        if (array_key_exists('fiche_matiere', $ficheMatiere->getEtatFiche()) || count($ficheMatiere->getEtatFiche()) === 0) {
            $this->nbFichesNonValidees++;
        }

        if (array_key_exists('soumis_central', $ficheMatiere->getEtatFiche())) {
            $this->nbFichesNonValideesSes++;
        }

        if (array_key_exists('en_cours_redaction', $ficheMatiere->getEtatFiche())) {
            $this->nbEnCoursRedaction++;
        }
        if (array_key_exists('valide_pour_publication', $ficheMatiere->getEtatFiche())) {
            $this->nbFichesValidees++;
        }

        if (array_key_exists('publie', $ficheMatiere->getEtatFiche())) {
            $this->nbFichesPubliees++;
        }
    }
}
