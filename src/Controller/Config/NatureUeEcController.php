<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/NatureUeEcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\NatureUeEc;
use App\Form\NatureUeEcType;
use App\Repository\NatureUeEcRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/nature-ue-ec')]
class NatureUeEcController extends AbstractController
{
    #[Route('/', name: 'app_nature_ue_ec_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/nature_ue_ec/index.html.twig');
    }

    #[Route('/liste', name: 'app_nature_ue_ec_liste', methods: ['GET'])]
    public function liste(NatureUeEcRepository $natureUeEcRepository): Response
    {
        return $this->render('config/nature_ue_ec/_liste.html.twig', [
            'nature_ue_ecs' => $natureUeEcRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_nature_ue_ec_new', methods: ['GET', 'POST'])]
    public function new(Request $request, NatureUeEcRepository $natureUeEcRepository): Response
    {
        $natureUeEc = new NatureUeEc();
        $form = $this->createForm(NatureUeEcType::class, $natureUeEc, [
            'action' => $this->generateUrl('app_nature_ue_ec_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $natureUeEcRepository->save($natureUeEc, true);

            return $this->json(true);
        }

        return $this->render('config/nature_ue_ec/new.html.twig', [
            'nature_ue_ec' => $natureUeEc,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_nature_ue_ec_show', methods: ['GET'])]
    public function show(NatureUeEc $natureUeEc): Response
    {
        return $this->render('config/nature_ue_ec/show.html.twig', [
            'nature_ue_ec' => $natureUeEc,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_nature_ue_ec_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, NatureUeEc $natureUeEc, NatureUeEcRepository $natureUeEcRepository): Response
    {
        $form = $this->createForm(NatureUeEcType::class, $natureUeEc, [
            'action' => $this->generateUrl('app_nature_ue_ec_edit', ['id' => $natureUeEc->getId()]),

        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $natureUeEcRepository->save($natureUeEc, true);

            return $this->json(true);
        }

        return $this->render('config/nature_ue_ec/new.html.twig', [
            'nature_ue_ec' => $natureUeEc,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}/duplicate', name: 'app_nature_ue_ec_duplicate', methods: ['GET'])]
    public function duplicate(
        NatureUeEcRepository $natureUeEcRepository,
        NatureUeEc $natureUeEc
    ): Response {
        $natureUeEcNew = clone $natureUeEc;
        $natureUeEcNew->setLibelle($natureUeEc->getLibelle() . ' - Copie');
        $natureUeEcRepository->save($natureUeEcNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_nature_ue_ec_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        NatureUeEc $natureUeEc,
        NatureUeEcRepository $natureUeEcRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $natureUeEc->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $natureUeEcRepository->remove($natureUeEc, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
