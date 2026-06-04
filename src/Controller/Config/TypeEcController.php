<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeEcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\TypeEc;
use App\Form\TypeEcType;
use App\Repository\TypeEcRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-ec')]
class TypeEcController extends AbstractController
{
    #[Route('/', name: 'app_type_ec_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/type_ec/index.html.twig');
    }

    #[Route('/liste', name: 'app_type_ec_liste', methods: ['GET'])]
    public function liste(TypeEcRepository $typeEcRepository): Response
    {
        return $this->render('config/type_ec/_liste.html.twig', [
            'type_ecs' => $typeEcRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_type_ec_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TypeEcRepository $typeEcRepository
    ): Response
    {
        $typeEc = new TypeEc();
        $form = $this->createForm(TypeEcType::class, $typeEc, [
            'action' => $this->generateUrl('app_type_ec_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEcRepository->save($typeEc, true);

            return $this->json(true);
        }

        return $this->render('config/type_ec/new.html.twig', [
            'type_ec' => $typeEc,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_type_ec_show', methods: ['GET'])]
    public function show(TypeEc $typeEc): Response
    {
        return $this->render('config/type_ec/show.html.twig', [
            'type_ec' => $typeEc,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_ec_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeEc $typeEc,
        TypeEcRepository $typeEcRepository
    ): Response
    {
        $form = $this->createForm(TypeEcType::class, $typeEc, [
            'action' => $this->generateUrl('app_type_ec_edit', ['id' => $typeEc->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEcRepository->save($typeEc, true);

            return $this->json(true);
        }

        return $this->render('config/type_ec/new.html.twig', [
            'type_ec' => $typeEc,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_type_ec_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeEcRepository $typeEcRepository,
        TypeEc $typeEc
    ): Response {
        $typeEcNew = clone $typeEc;
        $typeEcNew->setLibelle($typeEc->getLibelle() . ' - Copie');
        $typeEcRepository->save($typeEcNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_ec_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeEc $typeEc,
        TypeEcRepository $typeEcRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeEc->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeEcRepository->remove($typeEc, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
