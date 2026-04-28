<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/API/ComposanteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\API;

use App\Repository\ComposanteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ComposanteController extends AbstractController
{
    #[Route('/api/composante', name: 'api_composante')]
    public function getComposante(
        Request $request,
        ComposanteRepository $composanteRepository,
    ): Response {
        $dpe = (bool) $request->query->get('dpe', false);

        if ($dpe === false) {
            $composantes = $composanteRepository->findAll();
        } else {
            $composantes = $composanteRepository->findByCentreGestion($this->getUser());
        }
        $t = [];
        foreach ($composantes as $composante) {
            $t[] = [
                'id' => $composante->getId(),
                'libelle' => $composante->getLibelle(),
            ];
        }
        return $this->json($t);
    }
}
