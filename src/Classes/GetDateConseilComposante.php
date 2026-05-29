<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/GetDateConseilComposante.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 30/10/2025 21:14
 */

namespace App\Classes;

use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Repository\HistoriqueParcoursRepository;

final class GetDateConseilComposante
{
    // on récupére la date du conseil de composante, si le parcours a été édité sur la campagne on récupère la date dans l'historique ou la demande de laissez-passer, si pas d'édition, on remonte sur la version précédente, et on fait la même recherche, si besoin on continue de remonter


    public function __construct(
        private GetHistorique $getHistorique,
    )
    {
    }

    public function getDateConseilComposante(DpeParcours|Parcours|null $parcours): ?\DateTimeInterface
    {
        if ($parcours === null) {
            return null;
        }

        if ($parcours instanceof Parcours) {
            $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        } else {
            $dpeParcours = $parcours;
            $parcours = $dpeParcours->getParcours();
        }

        // on regarde s'il y a eu une demande de modification (donc un état "en_cours_redaction")
        if ($this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'en_cours_redaction') !== null || $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'parcours_rf') !== null) {
            // édité sur cette campagne, donc on récupère la date du conseil de composante dans l'historique ou null si pas passé
            return $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'soumis_conseil')?->getDate();
        }

        if ($parcours->getParcoursOrigineCopie() !== null) {
            return $this->getDateConseilComposante($parcours->getParcoursOrigineCopie());
        }
        return null;
    }
}
