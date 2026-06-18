<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/DTO/AnneeData.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 13/06/2026 22:10
 */

namespace App\DTO;

use App\Entity\Annee;

class AnneeData
{
    public function __construct(
        public ?Annee  $annee,
        public int     $capaciteAccueil = 0,
        public bool    $isOuvert = true,
        public bool    $hasCapacite = true,
        public bool    $isProposeRecrutement = true,
        public ?string $codeApogeeEtapeAnnee = null,
        public ?string $codeApogeeEtapeVersion = null,
        public array   $plateformes = []
    )
    {
    }

    public static function fromAnnee(?Annee $annee): ?self
    {
        if ($annee === null) {
            return null;
        }

        $plateformes = [];
        foreach ($annee->getAdmissionPlateformeParametres() as $param) {
            $plateforme = $param->getPlateforme();
            if ($plateforme) {
                $plateformes[] = [
                    'plateforme' => $plateforme,
                    'libelle' => $plateforme->getLibelle(),
                    'code' => $plateforme->getCode(),
                    'parametre' => $param
                ];
            }
        }

        return new self(
            annee: $annee,
            capaciteAccueil: $annee->getCapaciteAccueil(),
            isOuvert: $annee->isOuvert() ?? true,
            hasCapacite: $annee->hasCapacite() ?? true,
            isProposeRecrutement: $annee->isProposeRecrutement() ?? true,
            codeApogeeEtapeAnnee: $annee->getCodeApogeeEtapeAnnee(),
            codeApogeeEtapeVersion: $annee->getCodeApogeeEtapeVersion(),
            plateformes: $plateformes
        );
    }
}
