<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/EtablissementController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Controller\BaseController;
use App\Entity\Etablissement;
use App\Form\EtablissementType;
use App\Repository\EtablissementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/etablissement')]
class EtablissementController extends BaseController
{
    #[Route('/', name: 'app_etablissement_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/etablissement/index.html.twig');
    }

    #[Route('/liste', name: 'app_etablissement_liste', methods: ['GET'])]
    public function liste(EtablissementRepository $etablissementRepository): Response
    {
        return $this->render('config/etablissement/_liste.html.twig', [
            'etablissements' => $etablissementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_etablissement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EtablissementRepository $etablissementRepository): Response
    {
        $etablissement = new Etablissement();
        $form = $this->createForm(EtablissementType::class, $etablissement, [
            'action' => $this->generateUrl('app_etablissement_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etablissementRepository->save($etablissement, true);

            $this->addFlashBag('success', 'Établissement créé avec succès');
            return $this->redirectToRoute('app_etablissement_index');
        }

        return $this->render('config/etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
            'titre' => "Création d'un établissement"
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_show', methods: ['GET'])]
    public function show(Etablissement $etablissement): Response
    {
        return $this->render('config/etablissement/show.html.twig', [
            'etablissement' => $etablissement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_etablissement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request                 $request,
        Etablissement           $etablissement,
        EtablissementRepository $etablissementRepository
    ): Response {
        $form = $this->createForm(
            EtablissementType::class,
            $etablissement,
            [
                'action' => $this->generateUrl('app_etablissement_edit', ['id' => $etablissement->getId()]),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $etablissement = $form->getData();
            $etablissementRepository->save($etablissement, true);

            $this->addFlashBag('success', 'Établissement modifié avec succès');
            return $this->redirectToRoute('app_etablissement_index');
        }

        return $this->render('config/etablissement/new.html.twig', [
            'etablissement' => $etablissement,
            'form' => $form->createView(),
            'titre' => "Modification d'un établissement"
        ]);
    }

    #[Route('/{id}', name: 'app_etablissement_delete', methods: ['POST'])]
    public function delete(
        Request                 $request,
        Etablissement           $etablissement,
        EtablissementRepository $etablissementRepository
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $etablissement->getId(), $request->request->get('_token'))) {
            $etablissementRepository->remove($etablissement, true);
        }

        return $this->redirectToRoute('app_etablissement_index', [], Response::HTTP_SEE_OTHER);
    }
}
