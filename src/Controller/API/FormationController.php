<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/API/FormationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\API;

use App\Controller\BaseController;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FormationController extends BaseController
{
    #[Route('/api/formation', name: 'api_formation')]
    #[IsGranted('ROLE_USER')]
    public function getFormation(
        Request $request,
        FormationRepository $formationRepository,
    ): Response {
        $dpe = (bool) $request->query->get('dpe', false);

        if ($dpe === false) {
            $formations = $formationRepository->findAll();
        } else {
            $formations = $formationRepository->findByComposanteDpe($this->getUser(), $this->getCampagneCollecte());
        }

        $t = [];
        foreach ($formations as $formation) {
            $t[] = [
                'id' => $formation->getId(),
                'libelle' => $formation->getDisplayLong(),
            ];
        }
        return $this->json($t);
    }
}
