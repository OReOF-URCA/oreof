<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/LangueController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\Langue;
use App\Form\LangueType;
use App\Repository\LangueRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/langue')]
class LangueController extends AbstractController
{
    #[Route('/', name: 'app_langue_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/langue/index.html.twig');
    }

    #[Route('/liste', name: 'app_langue_liste', methods: ['GET'])]
    public function liste(LangueRepository $langueRepository): Response
    {
        return $this->render('config/langue/_liste.html.twig', [
            'langues' => $langueRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_langue_new', methods: ['GET', 'POST'])]
    public function new(Request $request, LangueRepository $langueRepository): Response
    {
        $langue = new Langue();
        $form = $this->createForm(LangueType::class, $langue, [
            'action' => $this->generateUrl('app_langue_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $langueRepository->save($langue, true);

            return $this->json(true);
        }

        return $this->render('config/langue/new.html.twig', [
            'langue' => $langue,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_langue_show', methods: ['GET'])]
    public function show(Langue $langue): Response
    {
        return $this->render('config/langue/show.html.twig', [
            'langue' => $langue,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_langue_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Langue $langue, LangueRepository $langueRepository): Response
    {
        $form = $this->createForm(LangueType::class, $langue, [
            'action' => $this->generateUrl('app_langue_edit', ['id' => $langue->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $langueRepository->save($langue, true);

            return $this->json(true);
        }

        return $this->render('config/langue/new.html.twig', [
            'langue' => $langue,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_langue_duplicate', methods: ['GET'])]
    public function duplicate(
        LangueRepository $langueRepository,
        Langue $langue
    ): Response {
        $langueNew = clone $langue;
        $langueNew->setLibelle($langue->getLibelle() . ' - Copie');
        $langueRepository->save($langueNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_langue_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Langue $langue,
        LangueRepository $langueRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $langue->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $langueRepository->remove($langue, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
