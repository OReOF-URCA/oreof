<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UserProfilsController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Controller;

use App\Entity\Profil;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Events\NotifCentreComposanteEvent;
use App\Events\NotifCentreEtablissementEvent;
use App\Events\NotifCentreFormationEvent;
use App\Events\NotifCentreParcoursEvent;
use App\Repository\ComposanteRepository;
use App\Repository\EtablissementRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Repository\UserProfilRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/gestion/user/profils', name: 'app_user_profils_')]
final class UserProfilsController extends BaseController
{
    #[Route('/{user}', name: 'gestion')]
    public function gestionUserProfils(
        ProfilRepository $profilRepository,
        User             $user
    ): Response
    {
        return $this->render('user_profils/_gestion_profils.html.twig', [
            'user' => $user,
            'profils' => $profilRepository->findAll()
        ]);
    }

    #[Route('/{user}/liste', name: 'liste')]
    public function listeUserProfils(
        User             $user
    ): Response
    {
        return $this->render('user_profils/_liste_profils.html.twig', [
            'user' => $user,
            'userProfils' => $user->getUserProfils()
        ]);
    }

    #[Route('/{user}/add', methods: ['GET'], name: 'add')]
    public function addUserProfils(
        ProfilRepository $profilRepository,
        User             $user
    ): Response
    {
        return $this->render('user_profils/_gestion_profils.html.twig', [
            'user' => $user,
            'userProfils' => $user->getUserProfils(),
            'profils' => $profilRepository->findAll()
        ]);
    }

    #[Route('/{user}/add', methods: ['POST'], name: 'add_valide')]
    public function addCentre(
        EntityManagerInterface   $entityManager,
        EventDispatcherInterface $eventDispatcher,
        ProfilRepository         $profilRepository,
        UserProfilRepository     $userProfilRepository,
        ComposanteRepository     $composanteRepository,
        EtablissementRepository  $etablissementRepository,
        FormationRepository      $formationRepository,
        ParcoursRepository       $parcoursRepository,
        Request                  $request,
        User                     $user
    ): Response
    {
        $data = JsonRequest::getFromRequest($request);

        $profil = $profilRepository->find($data['centreType']);
        if (!$profil) {
            return $this->json(['error' => 'Ce rôle n\'existe pas'], 400);
        }

        $nCentre = (new UserProfil())
            ->setUser($user)
            ->setCampagneCollecte($this->getCampagneCollecte())
            ->setProfil($profil);
        $entityManager->persist($nCentre);

        $centreType = $profil->getCentre();
        $centreId = $data['centreId'];
        $force = (bool)$data['force'];

        $centre = match ($centreType) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $composanteRepository->find($centreId),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $etablissementRepository->find($centreId),
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $formationRepository->find($centreId),
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $parcoursRepository->find($centreId),
            default => null,
        };

        if (!$centre) {
            return $this->json(['error' => 'Le centre spécifié n\'existe pas'], 400);
        }

        $existingCentre = match ($centreType) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfilRepository->findOneBy(['user' => $user, 'composante' => $centre]),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $userProfilRepository->findOneBy(['user' => $user, 'etablissement' => $centre]),
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfilRepository->findFormationWithSameRole($centre, $profil, $this->getCampagneCollecte()),
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $userProfilRepository->findParcoursWithSameRole($centre, $profil, $this->getCampagneCollecte()),
        };

        if ($existingCentre && $profil->isExclusif()) {
            if (!$force) {
                return $this->json(['error' => 'already_exist'], 400);
            }

            $event = match ($centreType) {
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => new NotifCentreComposanteEvent($centre, $user, $profil),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => new NotifCentreFormationEvent($centre, $user, $profil),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => new NotifCentreParcoursEvent($centre, $user, $profil),
                default => null,
            };

            if ($event) {
                if ($existingCentre instanceof UserProfil) {
                    $entityManager->remove($existingCentre);
                }

                $eventDispatcher->dispatch($event, $event::NOTIF_REMOVE_CENTRE);
            }
        }

        match ($centreType) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $nCentre->setComposante($centre),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $nCentre->setEtablissement($centre),
            CentreGestionEnum::CENTRE_GESTION_FORMATION => $nCentre->setFormation($centre),
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => $nCentre->setParcours($centre),
        };

        $event = match ($centreType) {
            CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => new NotifCentreComposanteEvent($centre, $user, $profil),
            CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => new NotifCentreEtablissementEvent($centre, $user, $profil),
            CentreGestionEnum::CENTRE_GESTION_FORMATION => new NotifCentreFormationEvent($centre, $user, $profil),
            CentreGestionEnum::CENTRE_GESTION_PARCOURS => new NotifCentreParcoursEvent($centre, $user, $profil),
        };

        if ($event) {
            $eventDispatcher->dispatch($event, $event::NOTIF_ADD_CENTRE);
        }

        if ($centreType === CentreGestionEnum::CENTRE_GESTION_COMPOSANTE) {
            if ($profil->getCode() === 'ROLE_DPE') {
                $centre->setResponsableDpe($user);
            } elseif ($profil->getCode() === 'ROLE_DIRECTEUR') {
                $centre->setDirecteur($user);
            }
        } elseif ($centreType === CentreGestionEnum::CENTRE_GESTION_FORMATION) {
            if ($profil->getCode() === 'ROLE_RESP_FORMATION') {
                $centre->setResponsableMention($user);
            } elseif ($profil->getCode() === 'ROLE_CO_RESP_FORMATION') {
                $centre->setCoResponsable($user);
            }
        } elseif ($centreType === CentreGestionEnum::CENTRE_GESTION_PARCOURS) {
            if ($profil->getCode() === 'ROLE_RESP_PARCOURS') {
                $centre->setRespParcours($user);
            } elseif ($profil->getCode() === 'ROLE_CO_RESP_PARCOURS') {
                $centre->setCoResponsable($user);
            }
        }

        $entityManager->flush();

        return $this->json(['success' => 'Centre ajouté avec succès']);
    }

    #[Route('/config-profil/{user}', name: 'config')]
    public function configProfil(
        FormationRepository     $formationRepository,
        ComposanteRepository    $composanteRepository,
        EtablissementRepository $etablissementRepository,
        ParcoursRepository $parcoursRepository,
        User                    $user,
        Request                 $request,
        ProfilRepository        $profilRepository,
    ): Response
    {
        $profilId = JsonRequest::getValueFromRequest($request, 'profilId');
        $profil = $profilRepository->find($profilId);

        if ($profil !== null) {
            return match ($profil->getCentre()) {
                CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $this->render('user_profils/_config_profil_etablissement.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'etablissements' => $etablissementRepository->findAll()
                ]),
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $this->render('user_profils/_config_profil_composante.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'composantes' => $composanteRepository->findAll()
                ]),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $this->render('user_profils/_config_profil_formation.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'formations' => $formationRepository->findByCampagneCollecte($this->getCampagneCollecte())
                ]),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $this->render('user_profils/_config_profil_parcours.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'parcours' => $parcoursRepository->findByCampagneCollecte($this->getCampagneCollecte())
                ]),
                default => $this->render('communs/_erreur.html.twig', [
                    'message' => 'Le centre de gestion n\'est pas reconnu'
                ]),
            };


        }

        return $this->render('communs/_erreur.html.twig', [
            'message' => 'Le profil n\'existe pas'
        ])->setStatusCode(Response::HTTP_NOT_FOUND);
    }

    //app_user_profils_delete
    #[Route('/{userProfil}/delete', name: 'delete', methods: ['POST', 'DELETE'])]
    public function deleteUserProfils(
        EntityManagerInterface   $entityManager,
        EventDispatcherInterface $eventDispatcher,
        Request $request,
        UserProfil               $userProfil
    ): Response
    {
        // vérifier le CSRF
        if ($this->isCsrfTokenValid(
            'delete' . $userProfil->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {


            $profil = $userProfil->getProfil();
            if ($profil === null) {
                return $this->json(['error' => 'Ce profil n\'existe pas'], 400);
            }

            $centreType = $profil->getCentre();
            $user = $userProfil->getUser();

            $centre = match ($centreType) {
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $userProfil->getComposante(),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $userProfil->getFormation(),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $userProfil->getParcours(),
                default => null,
            };

            $event = match ($centreType) {
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => new NotifCentreComposanteEvent($centre, $user, $profil),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => new NotifCentreFormationEvent($centre, $user, $profil),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => new NotifCentreParcoursEvent($centre, $user, $profil),
                default => null,
            };

            if ($profil->isExclusif()) {
                if ($centreType === CentreGestionEnum::CENTRE_GESTION_COMPOSANTE) {
                    if ($profil->getCode() === 'ROLE_DPE') {
                        $centre->setResponsableDpe(null);
                    } elseif ($profil->getCode() === 'ROLE_DIRECTEUR') {
                        $centre->setDirecteur(null);
                    }
                } elseif ($centreType === CentreGestionEnum::CENTRE_GESTION_FORMATION) {
                    if ($profil->getCode() === 'ROLE_RESP_FORMATION') {
                        $centre->setResponsableMention(null);
                    } elseif ($profil->getCode() === 'ROLE_CO_RESP_FORMATION') {
                        $centre->setCoResponsable(null);
                    }
                } elseif ($centreType === CentreGestionEnum::CENTRE_GESTION_PARCOURS) {
                    if ($profil->getCode() === 'ROLE_RESP_PARCOURS') {
                        $centre->setRespParcours(null);
                    } elseif ($profil->getCode() === 'ROLE_CO_RESP_PARCOURS') {
                        $centre->setCoResponsable(null);
                    }
                }
            }

            if ($event) {
                $eventDispatcher->dispatch($event, $event::NOTIF_REMOVE_CENTRE);
            }

            $entityManager->remove($userProfil);
            $entityManager->flush();

            return $this->json(['success' => 'Profil supprimé avec succès']);
        }
        return $this->json(['error' => 'Le token CSRF est invalide'], 400);
    }
}
