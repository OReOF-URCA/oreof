<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/AnneeUniversitaireController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Entity\CampagneCollecte;
use App\Form\CampagneCollecteType;
use App\Repository\CampagneCollecteRepository;
use App\Utils\JsonRequest;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/annee/universitaire')]
class AnneeUniversitaireController extends AbstractController
{
    #[Route('/', name: 'app_annee_universitaire_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/annee_universitaire/index.html.twig');
    }

    #[Route('/liste', name: 'app_annee_universitaire_liste', methods: ['GET'])]
    public function liste(CampagneCollecteRepository $anneeUniversitaireRepository): Response
    {
        return $this->render('config/annee_universitaire/_liste.html.twig', [
            'annee_universitaires' => $anneeUniversitaireRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_annee_universitaire_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CampagneCollecteRepository $annee_universitaireRepository): Response
    {
        $annee_universitaire = new CampagneCollecte();
        $form = $this->createForm(
            CampagneCollecteType::class,
            $annee_universitaire,
            ['action' => $this->generateUrl('app_annee_universitaire_new')]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $annee_universitaireRepository->save($annee_universitaire, true);

            return $this->json(true);
        }

        return $this->render('config/annee_universitaire/new.html.twig', [
            'annee_universitaire' => $annee_universitaire,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_annee_universitaire_show', methods: ['GET'])]
    public function show(CampagneCollecte $annee_universitaire): Response
    {
        return $this->render('config/annee_universitaire/show.html.twig', [
            'annee_universitaire' => $annee_universitaire,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_annee_universitaire_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request          $request,
        CampagneCollecte $annee_universitaire,
        CampagneCollecteRepository $annee_universitaireRepository
    ): Response {
        $form = $this->createForm(
            CampagneCollecteType::class,
            $annee_universitaire,
            [
                'action' => $this->generateUrl('app_annee_universitaire_edit', [
                    'id' => $annee_universitaire->getId()
                ])
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $annee_universitaireRepository->save($annee_universitaire, true);

            return $this->json(true);
        }

        return $this->render('config/annee_universitaire/new.html.twig', [
            'annee_universitaire' => $annee_universitaire,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/duplicate', name: 'app_annee_universitaire_duplicate', methods: ['GET'])]
    public function duplicate(
        CampagneCollecteRepository $annee_universitaireRepository,
        CampagneCollecte           $annee_universitaire
    ): Response {
        $annee_universitaireNew = clone $annee_universitaire;
        $annee_universitaireNew->setLibelle($annee_universitaire->getLibelle() . ' - Copie');
        $annee_universitaireRepository->save($annee_universitaireNew, true);
        return $this->json(true);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_annee_universitaire_delete', methods: ['DELETE'])]
    public function delete(
        Request          $request,
        CampagneCollecte $annee_universitaire,
        CampagneCollecteRepository $annee_universitaireRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $annee_universitaire->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            $annee_universitaireRepository->remove($annee_universitaire, true);

            return $this->json(true);
        }

        return $this->json(false);
    }
}
