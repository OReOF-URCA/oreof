<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/ComposanteController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Classes\AddUser;
use App\Entity\Composante;
use App\Form\ComposanteType;
use App\Repository\ComposanteRepository;
use App\Utils\JsonRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/composante')]
class ComposanteController extends AbstractController
{
    #[Route('/', name: 'app_composante_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/composante/index.html.twig');
    }

    #[Route('/liste', name: 'app_composante_liste', methods: ['GET'])]
    public function liste(ComposanteRepository $composanteRepository): Response
    {
        return $this->render('config/composante/_liste.html.twig', [
            'composantes' => $composanteRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_composante_new', methods: ['GET', 'POST'])]
    public function new(
        AddUser $addUser,
        Request $request,
        ComposanteRepository $composanteRepository
    ): Response
    {
        $composante = new Composante();
        $form = $this->createForm(ComposanteType::class, $composante, [
            'action' => $this->generateUrl('app_composante_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {//&& $form->isValid()
            if ($composante->getDirecteur() === null) {
                $usr = $addUser->addUser($request->request->get('directeur_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_LECTEUR');
                    $addUser->setCentreComposante($usr, $composante);
                    $composante->setDirecteur($usr);
                }
            }

            if ($composante->getResponsableDpe() === null) {
                $usr = $addUser->addUser($request->request->get('responsableDpe_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_USER');
                    $addUser->setCentreComposante($usr, $composante, 'ROLE_DPE_COMPOSANTE');
                    $composante->setResponsableDpe($usr);
                }
            }

            $composanteRepository->save($composante, true);

            return $this->json(true);
        }

        return $this->render('config/composante/new.html.twig', [
            'composante' => $composante,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_composante_show', methods: ['GET'])]
    public function show(Composante $composante): Response
    {
        return $this->render('config/composante/show.html.twig', [
            'composante' => $composante,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_composante_edit', methods: ['GET', 'POST'])]
    public function edit(AddUser $addUser, Request $request, Composante $composante, ComposanteRepository $composanteRepository): Response
    {
        $form = $this->createForm(ComposanteType::class, $composante, [
            'action' => $this->generateUrl('app_composante_edit', ['id' => $composante->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) { //&& $form->isValid()
            if ($composante->getDirecteur() === null) {
                $usr = $addUser->addUser($request->request->get('directeur_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_LECTEUR');
                    $addUser->setCentreComposante($usr, $composante);
                    $composante->setDirecteur($usr);
                }
            }

            if ($composante->getResponsableDpe() === null) {
                $usr = $addUser->addUser($request->request->get('responsableDpe_toAdd'));
                if ($usr !== null) {
                    $addUser->addRole($usr, 'ROLE_USER');
                    $addUser->setCentreComposante($usr, $composante, 'ROLE_DPE_COMPOSANTE');
                    $composante->setResponsableDpe($usr);
                }
            }

            $composanteRepository->save($composante, true);

            return $this->json(true);
        }

        return $this->render('config/composante/new.html.twig', [
            'composante' => $composante,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_composante_delete', methods: ['POST', 'DELETE'])]
    public function delete(Request $request, Composante $composante, ComposanteRepository $composanteRepository): Response
    {

        if ($this->isCsrfTokenValid('delete'.$composante->getId(), JsonRequest::getValueFromRequest($request, 'csrf'))) {
            $composanteRepository->remove($composante, true);
        }

        return $this->redirectToRoute('app_composante_index', [], Response::HTTP_SEE_OTHER);
    }
}
