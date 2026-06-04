<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/ComposanteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Repository\ComposanteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/composante', name: 'structure_composante_')]
class ComposanteController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('structure/composante/index.html.twig');
    }

    #[Route('/liste', name: 'liste')]
    public function liste(
        ComposanteRepository $composanteRepository
    ): Response {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('SHOW', ['route' => 'app_composante', 'subject' => 'composante'])) {
            $composantes = $composanteRepository->findAll();
        } else {
            $composantes = $composanteRepository->findByCentreGestion($this->getUser());
        }

        return $this->render('structure/composante/_liste.html.twig', [
            'composantes' => $composantes
        ]);
    }
}
