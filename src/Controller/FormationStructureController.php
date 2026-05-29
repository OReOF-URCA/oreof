<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/FormationStructureController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\FormationStructure;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Repository\ParcoursRepository;
use App\Utils\JsonRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FormationStructureController extends BaseController
{
    #[Route('/formation/structure/pas-parcours/{formation}', name: 'app_formation_genere_structure_pas_parcours')]
    public function genereStructurePasParcours(
        FormationStructure $formationStructure,
        Formation $formation
    ): Response {
        $formationStructure->genereStructrePasParcours($formation, $this->getCampagneCollecte());

        $this->addFlashBag('success', 'La structure de la formation a été générée');

        return $this->json(true);
    }

    #[Route('/formation/structure/{parcours}', name: 'app_formation_genere_structure')]
    public function genereStructure(
        ParcoursRepository $parcoursRepository,
        Request $request,
        FormationStructure $formationStructure,
        Parcours $parcours
    ): Response {
        $action = JsonRequest::getValueFromRequest($request, 'action');

        switch ($action) {
            case 'recopieStructure':
                $parcoursOriginal = $parcoursRepository->find(JsonRequest::getValueFromRequest($request, 'value'));
                $formationStructure->recopieParcours($parcours, $parcoursOriginal);
                break;
            case 'genereStructure':
            case 'reinitialiseStructure':
                $formationStructure->genereStructure($parcours, $this->getCampagneCollecte());
                break;
        }


        $this->addFlashBag('success', 'La structure de la formation a été générée');

        return $this->json(true);
    }
}
