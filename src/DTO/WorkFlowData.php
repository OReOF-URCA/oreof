<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/DTO/WorkFlowData.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 04/10/2025 06:31
 */

namespace App\DTO;

use App\Entity\ChangeRf;
use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;

class WorkFlowData
{
    public const EMAIL_CFVU = 'cfvu-secretariat@univ-reims.fr'; //todo: a mettre sur établissement ?
    public const EMAIL_OREOF = 'oreof@univ-reims.fr'; //todo: a mettre sur établissement ?

    public ?Composante $composante;
    public ?DpeParcours $dpeParcours;
    public ?Parcours $parcours;
    public ?Formation $formation;
    public ?FicheMatiere $fiche;
    public ?bool $hasParcours = false;

    public function __construct(object $data)
    {
        switch (get_class($data)) {
            case Composante::class:
                $this->composante = $data;
                $this->parcours = null;
                $this->formation = null;
                $this->dpeParcours = null;
                $this->fiche = null;
                break;
            case DpeParcours::class:
                $this->dpeParcours = $data;
                $this->parcours = $data->getParcours();
                $this->formation = $this->parcours?->getFormation();
                $this->hasParcours = $this->parcours?->isParcoursDefaut() === false;
                $this->composante = $this->formation?->getComposantePorteuse();
                $this->fiche = null;
                break;
            case Parcours::class:
                $this->parcours = $data;
                $this->formation = $data->getFormation();
                $this->hasParcours = $this->parcours?->isParcoursDefaut() === false;
                $this->composante = $this->formation?->getComposantePorteuse();
                $this->dpeParcours = $this->parcours?->getDpeParcours()->first();
                $this->fiche = null;
                break;
            case Formation::class:
                $this->formation = $data;
                $this->composante = $this->formation?->getComposantePorteuse();
                $this->dpeParcours = $this->formation?->getDpeParcours()->first();
                $this->parcours = $this->dpeParcours?->getParcours();
                $this->hasParcours = $this->parcours?->isParcoursDefaut() === false;
                $this->fiche = null;
                break;
            case FicheMatiere::class:
                $this->fiche = $data;
                $this->parcours = $this->fiche->getParcours();
                $this->formation = $this->parcours?->getFormation();
                $this->hasParcours = $this->parcours?->isParcoursDefaut() === false;
                $this->composante = $this->formation?->getComposantePorteuse();
                $this->dpeParcours = $this->parcours?->getDpeParcours()->first();
                break;
            default:
                break;
        }
    }

    public function toArray(): array
    {
        return [
            'composante' => $this->composante,
            'dpeParcours' => $this->dpeParcours,
            'parcours' => $this->parcours,
            'formation' => $this->formation,
            'fiche' => $this->fiche,
            'hasParcours' => $this->hasParcours,
        ];
    }

    public function getTitre(array $meta): ?string
    {
        if ($this->hasParcours) {
            if (array_key_exists('titre_with_parcours', $meta) && $meta['titre_with_parcours'] !== '') {
                return sprintf($meta['titre_with_parcours'], $this->parcours->getLibelle(), $this->formation->getDisplay());
            }
        }

        if (array_key_exists('titre', $meta) && $meta['titre'] !== '') {
            return sprintf($meta['titre'], $this->formation->getDisplay());
        }

        return '';
    }
}
