<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Json/ExportReferentielCompetencesBut.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/05/2025 05:57
 */

namespace App\Classes\Json;

use App\Entity\Formation;

class ExportReferentielCompetencesBut
{
    public function exportToArray(Formation $formation): array
    {
        $competences = $formation->getButCompetences();

        $competencesArray = [];
        foreach ($competences as $competence) {
            $idCompetence = $competence->getId();
            $competencesArray[$idCompetence] = [
                'id' => $idCompetence,
                'libelle' => $competence->getLibelle(),
                'composantes_essentielles' => $competence->getComposantes(),
                'nomCourt' => $competence->getNomCourt(),
                'numero' => $competence->getNumero(),
                'situations_professionnelles' => $competence->getSituations(),
                'niveaux' => []
            ];

            foreach ($competence->getButNiveaux() as $niveau) {
                $idNiveau = $niveau->getId();

                $competencesArray[$idCompetence]['niveaux'][$idNiveau] = [
                    'id' => $idNiveau,
                    'libelle' => $niveau->getLibelle(),
                    'ordre' => $niveau->getOrdre(),
                    'acs' => [],
                ];

                foreach ($niveau->getButApprentissageCritiques() as $critique) {
                    $idCritique = $critique->getId();
                    $competencesArray[$idCompetence]['niveaux'][$idNiveau]['acs'][$idCritique] = [
                        'id' => $idCritique,
                        'libelle' => $critique->getLibelle(),
                        'code' => $critique->getCode(),
                    ];

                }
            }
        }

        return $competencesArray;
    }
}
