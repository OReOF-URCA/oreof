<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/EcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Classes\GetDpeParcours;
use App\Controller\BaseController;
use App\Entity\Parcours;
use App\Entity\Ue;
use App\Repository\ElementConstitutifRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/element-constitutif', name: 'structure_ec_')]
class EcController extends BaseController
{

    #[Route('/detail/ue/{ue}/{parcours}', name: 'detail_ue')]
    public function detailComposante(
        Request $request,
        ElementConstitutifRepository $elementConstitutifRepository,
        Ue $ue,
        Parcours $parcours
    ): Response {
        $isUeRaccrochee = (bool) $request->query->get('raccroche', false);

        if ($ue->getUeRaccrochee() === null) {
            $ecs = $elementConstitutifRepository->findBy(['ue' => $ue], ['ordre' => 'ASC']);
        } else {
            $ecs = $elementConstitutifRepository->findBy(['ue' => $ue->getUeRaccrochee()->getUe()], ['ordre' => 'ASC']);
        }

        return $this->render('structure/ec/_liste.html.twig', [
            'isBut' => $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT',
            'ecs' => $ecs,
            'dpeParcours' => GetDpeParcours::getFromParcours($parcours),
            'ue' => $ue,
            'parcours' => $parcours,
            'deplacer' => true,
            'mode' => 'detail',
            'isUeRaccrochee' => $isUeRaccrochee,
            'editable' => $isUeRaccrochee === false && $ue->getUeRaccrochee() === null,
        ]);
    }
}
