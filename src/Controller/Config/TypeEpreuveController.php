<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/TypeEpreuveController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\TypeEpreuve;
use App\Form\TypeEpreuveType;
use App\Repository\TypeEpreuveRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/type-epreuve')]
class TypeEpreuveController extends AbstractController
{
    #[Route('/', name: 'app_type_epreuve_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/type_epreuve/index.html.twig');
    }

    #[Route('/liste', name: 'app_type_epreuve_liste', methods: ['GET'])]
    public function liste(
        Request $request,
        TypeEpreuveRepository $typeEpreuveRepository): Response
    {
        $sort = $request->query->get('sort') ?? 'libelle';
        $direction = $request->query->get('direction') ?? 'asc';
        $q = $request->query->get('q') ?? '';

        return $this->render('config/type_epreuve/_liste.html.twig', [
            'type_epreuves' => $typeEpreuveRepository->findBySearchAndSort($sort, $direction, $q),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'app_type_epreuve_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response
    {
        $typeEpreuve = new TypeEpreuve();
        $form = $this->createForm(TypeEpreuveType::class, $typeEpreuve, [
            'action' => $this->generateUrl('app_type_epreuve_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEpreuveRepository->save($typeEpreuve, true);

            return $this->json(true);
        }

        return $this->render('config/type_epreuve/new.html.twig', [
            'type_epreuve' => $typeEpreuve,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_type_epreuve_show', methods: ['GET'])]
    public function show(TypeEpreuve $typeEpreuve): Response
    {
        return $this->render('config/type_epreuve/show.html.twig', [
            'type_epreuve' => $typeEpreuve,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_type_epreuve_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        TypeEpreuve $typeEpreuve,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response
    {
        $form = $this->createForm(TypeEpreuveType::class, $typeEpreuve, [
            'action' => $this->generateUrl('app_type_epreuve_edit', ['id' => $typeEpreuve->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $typeEpreuveRepository->save($typeEpreuve, true);

            return $this->json(true);
        }

        return $this->render('config/type_epreuve/new.html.twig', [
            'type_epreuve' => $typeEpreuve,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_type_epreuve_duplicate', methods: ['GET'])]
    public function duplicate(
        TypeEpreuveRepository $typeEpreuveRepository,
        TypeEpreuve $typeEpreuve
    ): Response {
        $typeEpreuveNew = clone $typeEpreuve;
        $typeEpreuveNew->setLibelle($typeEpreuve->getLibelle() . ' - Copie');
        $typeEpreuveRepository->save($typeEpreuveNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_type_epreuve_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        TypeEpreuve $typeEpreuve,
        TypeEpreuveRepository $typeEpreuveRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $typeEpreuve->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $typeEpreuveRepository->remove($typeEpreuve, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
