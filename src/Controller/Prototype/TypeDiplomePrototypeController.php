<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Prototype/TypeDiplomePrototypeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/04/2026 16:41
 */

declare(strict_types=1);

namespace App\Controller\Prototype;

use App\Entity\TypeDiplome;
use App\Form\TypeDiplomeType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\TypeDiplomeRepository;
use App\Service\Prototype\TypeDiplomeImpactEstimator;
use App\Service\Prototype\TypeDiplomePrototypeDuplicator;
use App\Service\Prototype\TypeDiplomePrototypeQueryService;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/prototype/type-diplome', name: 'app_prototype_type_diplome_')]
final class TypeDiplomePrototypeController extends AbstractController
{
    public function __construct(
        private readonly TypeDiplomePrototypeQueryService $queryService,
        private readonly TypeDiplomeImpactEstimator       $impactEstimator,
        private readonly TypeDiplomePrototypeDuplicator   $duplicator,
        private readonly TypeDiplomeRepository            $typeDiplomeRepository
    )
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    #[Breadcrumb(menuKey: 'administration.type_diplome')]
    #[Breadcrumb(label: 'Prototype CRUD')]
    public function index(): Response
    {
        return $this->render('prototype/type_diplome/index.html.twig');
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $result = $this->queryService->search($request->query->all());

        return $this->render('prototype/type_diplome/_list.html.twig', [
            'type_diplomes' => $result['items'],
            'meta' => $result,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.type_diplome')]
    #[Breadcrumb(label: 'Création')]
    public function new(Request $request): Response
    {
        $typeDiplome = new TypeDiplome();
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_prototype_type_diplome_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->typeDiplomeRepository->save($typeDiplome, true);
            $this->addFlash('toast', [
                'type' => 'success',
                'title' => 'Succès',
                'text' => 'Type de diplôme créé (prototype).',
            ]);

            return $this->redirectToRoute('app_prototype_type_diplome_index');
        }

        return $this->render('prototype/type_diplome/form.html.twig', [
            'form' => $form->createView(),
            'titre' => 'Prototype - Créer un type de diplôme',
            'is_edit' => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[Breadcrumb(menuKey: 'administration.type_diplome')]
    #[Breadcrumb(label: 'Modification')]
    public function edit(Request $request, TypeDiplome $typeDiplome): Response
    {
        $form = $this->createForm(TypeDiplomeType::class, $typeDiplome, [
            'action' => $this->generateUrl('app_prototype_type_diplome_edit', ['id' => $typeDiplome->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->typeDiplomeRepository->save($typeDiplome, true);
            $this->addFlash('toast', [
                'type' => 'success',
                'title' => 'Succès',
                'text' => 'Type de diplôme modifié (prototype).',
            ]);

            return $this->redirectToRoute('app_prototype_type_diplome_index');
        }

        return $this->render('prototype/type_diplome/form.html.twig', [
            'form' => $form->createView(),
            'titre' => 'Prototype - Modifier un type de diplôme',
            'is_edit' => true,
            'type_diplome' => $typeDiplome,
        ]);
    }

    #[Route('/{id}/impact', name: 'impact', methods: ['GET'])]
    public function impact(TypeDiplome $typeDiplome): JsonResponse
    {
        return $this->json($this->impactEstimator->estimate($typeDiplome));
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(Request $request, TypeDiplome $typeDiplome): JsonResponse
    {
        $csrf = (string)JsonRequest::getValueFromRequest($request, 'csrf');
        if (!$this->isCsrfTokenValid('duplicate' . $typeDiplome->getId(), $csrf)) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $copy = $this->duplicator->duplicate($typeDiplome);
        $this->typeDiplomeRepository->save($copy, true);

        return $this->json([
            'success' => true,
            'id' => $copy->getId(),
            'label' => $copy->getLibelle(),
            'message' => 'Duplication effectuée.',
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Request $request, TypeDiplome $typeDiplome): JsonResponse
    {
        $csrf = (string)JsonRequest::getValueFromRequest($request, 'csrf');
        if (!$this->isCsrfTokenValid('delete' . $typeDiplome->getId(), $csrf)) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton CSRF invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $impact = $this->impactEstimator->estimate($typeDiplome);
        if ($impact['canDelete'] !== true) {
            return $this->json([
                'success' => false,
                'message' => 'Suppression bloquée: détacher les éléments liés avant suppression.',
                'impact' => $impact,
            ], Response::HTTP_CONFLICT);
        }

        $this->typeDiplomeRepository->remove($typeDiplome, true);

        return $this->json([
            'success' => true,
            'message' => 'Suppression effectuée.',
        ]);
    }
}

