<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/RythmeFormationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\RythmeFormation;
use App\Form\RythmeFormationType;
use App\Repository\RythmeFormationRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/rythme/formation')]
class RythmeFormationController extends AbstractController
{
    #[Route('/', name: 'app_rythme_formation_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/rythme_formation/index.html.twig');
    }

    #[Route('/liste', name: 'app_rythme_formation_liste', methods: ['GET'])]
    public function liste(RythmeFormationRepository $rythmeFormationRepository): Response
    {
        return $this->render('config/rythme_formation/_liste.html.twig', [
            'rythme_formations' => $rythmeFormationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_rythme_formation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, RythmeFormationRepository $rythmeFormationRepository): Response
    {
        $rythmeFormation = new RythmeFormation();
        $form = $this->createForm(RythmeFormationType::class, $rythmeFormation, [
            'action' => $this->generateUrl('app_rythme_formation_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rythmeFormationRepository->save($rythmeFormation, true);

            return $this->json(true);
        }

        return $this->render('config/rythme_formation/new.html.twig', [
            'rythme_formation' => $rythmeFormation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_rythme_formation_show', methods: ['GET'])]
    public function show(RythmeFormation $rythmeFormation): Response
    {
        return $this->render('config/rythme_formation/show.html.twig', [
            'rythme_formation' => $rythmeFormation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rythme_formation_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        RythmeFormation $rythmeFormation,
        RythmeFormationRepository $rythmeFormationRepository
    ): Response {
        $form = $this->createForm(RythmeFormationType::class, $rythmeFormation, [
            'action' => $this->generateUrl('app_rythme_formation_edit', ['id' => $rythmeFormation->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rythmeFormationRepository->save($rythmeFormation, true);

            return $this->json(true);
        }

        return $this->render('config/rythme_formation/new.html.twig', [
            'rythme_formation' => $rythmeFormation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_rythme_formation_duplicate', methods: ['GET'])]
    public function duplicate(
        RythmeFormationRepository $rythmeFormationRepository,
        RythmeFormation $rythmeFormation
    ): Response {
        $rythmeFormationNew = clone $rythmeFormation;
        $rythmeFormationNew->setLibelle($rythmeFormation->getLibelle() . ' - Copie');
        $rythmeFormationRepository->save($rythmeFormationNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_rythme_formation_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        RythmeFormation $rythmeFormation,
        RythmeFormationRepository $rythmeFormationRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $rythmeFormation->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $rythmeFormationRepository->remove($rythmeFormation, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
