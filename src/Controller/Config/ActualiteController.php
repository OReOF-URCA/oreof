<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/ActualiteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\Actualite;
use App\Form\ActualiteType;
use App\Repository\ActualiteRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/actualite')]
class ActualiteController extends AbstractController
{
    #[Route('/', name: 'app_actualite_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/actualite/index.html.twig');
    }

    #[Route('/liste', name: 'app_actualite_liste', methods: ['GET'])]
    public function liste(ActualiteRepository $actualiteRepository): Response
    {
        return $this->render('config/actualite/_liste.html.twig', [
            'actualites' => $actualiteRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_actualite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ActualiteRepository $actualiteRepository): Response
    {
        $actualite = new Actualite();
        $form = $this->createForm(ActualiteType::class, $actualite, [
            'action' => $this->generateUrl('app_actualite_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actualiteRepository->save($actualite, true);

            return $this->json(true);
        }

        return $this->render('config/actualite/new.html.twig', [
            'actualite' => $actualite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_actualite_show', methods: ['GET'])]
    public function show(Actualite $actualite): Response
    {
        return $this->render('config/actualite/show.html.twig', [
            'actualite' => $actualite,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_actualite_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Actualite $actualite, ActualiteRepository $actualiteRepository): Response
    {
        $form = $this->createForm(ActualiteType::class, $actualite, [
            'action' => $this->generateUrl('app_actualite_edit', ['id' => $actualite->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actualiteRepository->save($actualite, true);

            return $this->json(true);
        }

        return $this->render('config/actualite/new.html.twig', [
            'actualite' => $actualite,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_actualite_duplicate', methods: ['GET'])]
    public function duplicate(
        ActualiteRepository $actualiteRepository,
        Actualite $actualite
    ): Response {
        $actualiteNew = clone $actualite;
        $actualiteNew->setTitre($actualite->getTitre() . ' - Copie');
        $actualiteRepository->save($actualiteNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_actualite_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Actualite $actualite,
        ActualiteRepository $actualiteRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $actualite->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $actualiteRepository->remove($actualite, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
