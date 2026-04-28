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
use App\Repository\ProfilRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DroitsController extends BaseController
{
    #[Route('/api/droits', name: 'api_droits')]
    #[IsGranted('ROLE_USER')]
    public function getDroits(
        Request        $request,
        ProfilRepository $roleRepository,
    ): Response {
        $centre = $request->query->get('centre', '');
        $dpe = (bool)$request->query->get('dpe', false);

        if ($dpe === true) {
            $droits = $roleRepository->findByCentreDpe($centre);
        } else {
            $droits = $roleRepository->findByCentre($centre);
        }


        $t = [];
        foreach ($droits as $droit) {
            $t[] = [
                'id' => $droit->getId(),
                'libelle' => $droit->getLibelle(),
            ];
        }
        return $this->json($t);
    }
}
