<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/VilleController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\Ville;
use App\Form\VilleType;
use App\Repository\VilleRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/ville')]
class VilleController extends AbstractController
{
    #[Route('/', name: 'app_ville_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/ville/index.html.twig');
    }

    #[Route('/liste', name: 'app_ville_liste', methods: ['GET'])]
    public function liste(VilleRepository $villeRepository): Response
    {
        return $this->render('config/ville/_liste.html.twig', [
            'villes' => $villeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_ville_new', methods: ['GET', 'POST'])]
    public function new(Request $request, VilleRepository $villeRepository): Response
    {
        $ville = new Ville();
        $form = $this->createForm(VilleType::class, $ville, [
            'action' => $this->generateUrl('app_ville_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $villeRepository->save($ville, true);
            return $this->json(true);
        }

        return $this->render('config/ville/new.html.twig', [
            'ville' => $ville,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_ville_show', methods: ['GET'])]
    public function show(Ville $ville): Response
    {
        return $this->render('config/ville/show.html.twig', [
            'ville' => $ville,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ville_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ville $ville, VilleRepository $villeRepository): Response
    {
        $form = $this->createForm(VilleType::class, $ville, [
            'action' => $this->generateUrl('app_ville_edit', ['id' => $ville->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $villeRepository->save($ville, true);
            return $this->json(true);
        }

        return $this->render('config/ville/new.html.twig', [
            'ville' => $ville,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_ville_duplicate', methods: ['GET'])]
    public function duplicate(
        VilleRepository $villeRepository,
        Ville $ville
    ): Response {
        $villeNew = clone $ville;
        $villeNew->setLibelle($ville->getLibelle() . ' - Copie');
        $villeRepository->save($villeNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_ville_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Ville $ville,
        VilleRepository $villeRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $ville->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $villeRepository->remove($ville, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
