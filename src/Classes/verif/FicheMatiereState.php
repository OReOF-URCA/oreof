<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/verif/ParcoursState.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Classes\verif;

use App\Entity\FicheMatiere;
use App\Enums\EtatRemplissageEnum;

class FicheMatiereState
{
    private FicheMatiere $ficheMatiere;

    public function setFicheMatiere(FicheMatiere $ficheMatiere): void
    {
        $this->ficheMatiere = $ficheMatiere;
    }

    public function onglets(): array
    {
        $max = 4;

        for ($i = 1; $i <= $max; $i++) {
            $methodEmpty = 'isEmptyOnglet' . $i;
            if ($this->$methodEmpty() === true) {
                $onglets[$i] = EtatRemplissageEnum::VIDE;
            } else {
                $method = 'etatOnglet' . $i;
                $onglets[$i] = (($this->$method() === true) && ($this->ficheMatiere->getEtatStep($i) === true)) ? EtatRemplissageEnum::COMPLETE : EtatRemplissageEnum::EN_COURS;
            }
        }

        return $onglets;
    }

    public function isEmptyOnglet1(): bool
    {
        return $this->ficheMatiere->getLibelle() === null && $this->ficheMatiere->getLibelleAnglais() === null;
    }

    public function isEmptyOnglet2(): bool
    {
        return $this->ficheMatiere->getDescription() === null && $this->ficheMatiere->getLangueDispense()->count() === 0 && $this->ficheMatiere->getLangueSupport()->count() === 0 && $this->ficheMatiere->getObjectifs() === null && $this->ficheMatiere->getCompetences()->count() === 0;
    }

    public function isEmptyOnglet3(): bool
    {
        return true;
    }

    public function isEmptyOnglet4(): bool
    {
        return $this->ficheMatiere->getVolumeCmPresentiel() === null && $this->ficheMatiere->getVolumeTdPresentiel() === null && $this->ficheMatiere->getVolumeTpPresentiel() === null && $this->ficheMatiere->getVolumeTe() === null && $this->ficheMatiere->getMcccs()->count() === 0;
    }

    public function valideStep(mixed $value): bool|array
    {
        $method = 'etatOnglet' . ucfirst($value);

        return $this->$method();
    }

    private function etatOnglet1(): bool|array
    {
        $tab['error'] = [];

        if ($this->ficheMatiere->getLibelleAnglais() === null || trim($this->ficheMatiere->getLibelleAnglais()) === '') {
            $tab['error'][] = 'Vous devez indiquer le libellé en anglais.';
        }

        if ($this->ficheMatiere->getLibelle() === null || trim($this->ficheMatiere->getLibelle()) === '') {
            $tab['error'][] = 'Vous devez indiquer le libellé en français.';
        }

        if ($this->ficheMatiere->isEnseignementMutualise() === true) {
            if ($this->ficheMatiere->isIsCmPresentielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en CM présentiel est mutualisé.';
            }

            if ($this->ficheMatiere->isIsTdPresentielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en TD présentiel est mutualisé.';
            }

            if ($this->ficheMatiere->isIsTpPresentielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en TP présentiel est mutualisé.';
            }

            if ($this->ficheMatiere->isIsCmDistancielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en CM distanciel est mutualisé.';
            }

            if ($this->ficheMatiere->isIsTdDistancielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en TD distanciel est mutualisé.';
            }

            if ($this->ficheMatiere->isIsTpDistancielMutualise() === null) {
                $tab['error'][] = 'Vous devez preciser si l\'enseignement en TP distanciel est mutualisé.';
            }
        }

        return count($tab['error']) > 0 ? $tab : true;
    }

    private function etatOnglet2(): bool|array
    {
        $tab['error'] = [];

        if ($this->ficheMatiere->getDescription() === null || trim($this->ficheMatiere->getDescription()) === '') {
            $tab['error'][] = 'Vous devez indiquer une description.';
        }

        if ($this->ficheMatiere->getObjectifs() === null || trim($this->ficheMatiere->getObjectifs()) === '') {
            $tab['error'][] = 'Vous devez indiquer les objectifs.';
        }

        if ($this->ficheMatiere->getLangueDispense()->count() === 0) {
            $tab['error'][] = 'Vous devez choisir au moins une langue de dispense.';
        }

        if ($this->ficheMatiere->getLangueSupport()->count() === 0) {
            $tab['error'][] = 'Vous devez choisir au moins une langue de support.';
        }



        return count($tab['error']) > 0 ? $tab : true;
    }

    private function etatOnglet3(): bool|array
    {
        $tab['error'] = [];

        if ($this->ficheMatiere->getCompetences()->count() === 0 && $this->ficheMatiere->getApprentissagesCritiques()->count() === 0) {
            $tab['error'][] = 'Vous devez associer au moins une compétence.';
        }

        return count($tab['error']) > 0 ? $tab : true;
    }

    private function etatOnglet4(): bool|array
    {
        $totalHeures = $this->ficheMatiere->getTotalHeures();

        $tab['error'] = [];

        if ($totalHeures === 0.0 || ($this->ficheMatiere->getVolumeCmPresentiel() === null && $this->ficheMatiere->getVolumeTdPresentiel() === null && $this->ficheMatiere->getVolumeTpPresentiel() === null && $this->ficheMatiere->getVolumeTe() === null)) {
            if ($this->ficheMatiere->isSansHeures() === false) {
                $tab['error'][] = 'Vous devez indiquer au moins un volume horaire.';
            }
        }

        //MCCC
        if ($this->ficheMatiere->isSansNote() === false) {
            if ($this->ficheMatiere->getMcccs()->count() === 0) {
                $tab['error'][] = 'Vous devez associer au moins un MCCC.';
            }
            $somme = 0;
            foreach ($this->ficheMatiere->getMcccs() as $mccc) {
                if ($mccc->getNbEpreuves() > 0 && ($mccc->getPourcentage() === null || $mccc->getPourcentage() === 0.0)) {
                    $tab['error'][] = 'Vous devez indiquer un % pour chaque épreuve.';
                }
                $somme += $mccc->getPourcentage() * $mccc->getNbEpreuves();
            }

            if ($somme !== 100.0) {
                $tab['error'][] = 'La somme des % des épreuves doit être égale à 100%.';
            }
        }

        return count($tab['error']) > 0 ? $tab : true;
    }
}
