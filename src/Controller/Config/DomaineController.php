<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/DomaineController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\Domaine;
use App\Form\DomaineType;
use App\Repository\DomaineRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/domaine')]
class DomaineController extends AbstractController
{
    #[Route('/', name: 'app_domaine_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/domaine/index.html.twig');
    }

    #[Route('/liste', name: 'app_domaine_liste', methods: ['GET'])]
    public function liste(DomaineRepository $domaineRepository): Response
    {
        return $this->render('config/domaine/_liste.html.twig', [
            'domaines' => $domaineRepository->findAll(),//filtrer par établissement du connecté
        ]);
    }

    #[Route('/new', name: 'app_domaine_new', methods: ['GET', 'POST'])]
    public function new(Request $request, DomaineRepository $domaineRepository): Response
    {
        $domaine = new Domaine();
        $form = $this->createForm(DomaineType::class, $domaine, [
            'action' => $this->generateUrl('app_domaine_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $domaineRepository->save($domaine, true);
            return $this->json(true);
        }

        return $this->render('config/domaine/new.html.twig', [
            'domaine' => $domaine,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_domaine_show', methods: ['GET'])]
    public function show(Domaine $domaine): Response
    {
        return $this->render('config/domaine/show.html.twig', [
            'domaine' => $domaine,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_domaine_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Domaine $domaine, DomaineRepository $domaineRepository): Response
    {
        $form = $this->createForm(DomaineType::class, $domaine, [
            'action' => $this->generateUrl('app_domaine_edit', ['id' => $domaine->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $domaineRepository->save($domaine, true);
            return $this->json(true);
        }

        return $this->render('config/domaine/new.html.twig', [
            'domaine' => $domaine,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_domaine_duplicate', methods: ['GET'])]
    public function duplicate(
        DomaineRepository $domaineRepository,
        Domaine $domaine
    ): Response {
        $domaineNew = clone $domaine;
        $domaineNew->setLibelle($domaine->getLibelle() . ' - Copie');
        $domaineRepository->save($domaineNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_domaine_delete', methods: ['DELETE'])]
    public function delete(
        Request $request,
        Domaine $domaine,
        DomaineRepository $domaineRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $domaine->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $domaineRepository->remove($domaine, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
