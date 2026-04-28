<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/ProfilsController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

declare(strict_types=1);

namespace App\Controller\Config;

use App\Classes\JsonReponse;
use App\Controller\BaseController;
use App\Entity\Profil;
use App\Entity\ProfilDroits;
use App\Enums\PermissionEnum;
use App\Enums\RessourceEnum;
use App\Form\ProfilType;
use App\Repository\ProfilRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/profils', name: 'app_administration_profils_')]
#[IsGranted('ROLE_ADMIN')]
class ProfilsController extends BaseController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ProfilRepository $profilRepository,
    ): Response
    {
        return $this->render('config/profils/index.html.twig', [
            'profils' => $profilRepository->findAll(),
            'profilId' => $request->query->get('profil') ?? 0,
        ]);
    }

    #[Route('/_liste', name: 'liste', methods: ['GET'])]
    public function liste(
        Request          $request,
        ProfilRepository $profilRepository,
    ): Response
    {
        $profil = $profilRepository->find($request->query->get('profil'));
        if ($profil) {
            $form = $this->createForm(ProfilType::class, $profil, [
                'action' => $this->generateUrl('app_administration_profils_editer', ['profil' => $profil->getId()]),
            ]);
            $form->handleRequest($request);
            $permissions = $profil->getProfilDroits();
            $ressourcesProfil = [];
            foreach ($permissions as $permission) {
                $ressourcesProfil[$permission->getRessource()->name] = $permission->getPermission()->value;
            }
            ksort($ressourcesProfil);
        } else {
            $ressourcesProfil = [];
        }

        return $this->render('config/profils/_liste.html.twig', [
            'profil' => $profil,
            'ressources' => RessourceEnum::getRessources(),
            'droits' => PermissionEnum::cases(),
            'ressourcesProfil' => $ressourcesProfil,
            'form' => isset($form) ? $form->createView() : null,
        ]);
    }

    #[Route('/creer', name: 'creer')]
    public function new(
        Request                $request,
        EntityManagerInterface $entityManager
    ): Response
    {
        $profil = new Profil();
        $form = $this->createForm(ProfilType::class, $profil, [
            'action' => $this->generateUrl('app_administration_profils_creer'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($profil);
            $entityManager->flush();
            return $this->json(true);
        }

        return $this->render('config/profils/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/modifier/{profil}', name: 'editer')]
    public function editer(
        Request                $request,
        EntityManagerInterface $entityManager,
        Profil                 $profil,
    ): Response
    {
        $form = $this->createForm(ProfilType::class, $profil, [
            'action' => $this->generateUrl('app_administration_profils_editer', ['profil' => $profil->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($profil);
            $entityManager->flush();
        }

        return $this->redirect($this->generateUrl('app_administration_profils_index', ['profil' => $profil->getId()]));
    }

    #[Route('/change-droit', name: 'change_droit')]
    public function changeDroit(
        Request                $request,
        EntityManagerInterface $entityManager,
        ProfilRepository       $profilRepository,
    ): Response
    {
        $data = JsonRequest::getFromRequest($request);
        $profil = $profilRepository->find($data['profil']);

        if ($profil === null) {
            return JsonReponse::error('Profil introuvable');
        }

        $ressource = RessourceEnum::from($data['ressource']);
        $permission = PermissionEnum::from($data['droit']);
        $profilDroit = $profil->getProfilDroits()->filter(function ($profilDroit) use ($ressource) {
            return $profilDroit->getRessource() === $ressource;
        })->first();
        if ($profilDroit && $profilDroit->getPermission() !== $permission) {
            $profilDroit->setPermission($permission);
        } elseif ($profilDroit === false) {
            $profilDroit = new ProfilDroits();
            $profilDroit->setProfil($profil);
            $profilDroit->setRessource($ressource);
            $profilDroit->setPermission($permission);
            $entityManager->persist($profilDroit);
        }
        $entityManager->flush();

        return JsonReponse::success('Profil mis à jour');

    }
}
