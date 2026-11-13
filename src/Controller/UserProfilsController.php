<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UserProfilsController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Controller;

use App\DTO\TranslatableKey;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Enums\TypeRfEnum;
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
use App\Utils\TurboStreamResponseFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/gestion/user/profils', name: 'app_user_profils_')]
final class UserProfilsController extends BaseController
{
    #[Route('/{user}', name: 'gestion')]
    public function gestionUserProfils(
        TurboStreamResponseFactory $turboStream,
        ProfilRepository $profilRepository,
        User             $user
    ): Response
    {
        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user.gestion_profil.title', [], 'modal'),
            'Utilisateur : ' . $user->getDisplay(),
            'user_profils/_gestion_profils.html.twig',
            [
                'user' => $user,
                'profils' => $this->isGranted('ROLE_ADMIN') ? $profilRepository->findAll() : $profilRepository->findByDpe(),
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
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

    #[Route('/{user}/add', name: 'add', methods: ['GET'])]
    public function addUserProfils(
        ProfilRepository $profilRepository,
        User             $user
    ): Response
    {
        return $this->render('user_profils/_gestion_profils.html.twig', [
            'user' => $user,
            'userProfils' => $user->getUserProfils(),
            'profils' => $this->isGranted('ROLE_ADMIN') ? $profilRepository->findAll() : $profilRepository->findByDpe()
        ]);
    }

    #[Route('/{user}/add', name: 'add_valide', methods: ['POST'])]
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

        $profil = $profilRepository->find($data['centreType'] ?? null);
        if (!$profil) {
            return $this->json(['error' => 'Ce rôle n\'existe pas'], 400);
        }

        $centreType = $profil->getCentre() ?? CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT;
        $centreId = $data['centreId'] ?? null;
        $force = (bool)($data['force'] ?? false);

        if (empty($centreId)) {
            return $this->json(['error' => 'Veuillez sélectionner un centre'], 400);
        }

        // Si l'utilisateur n'est pas ROLE_ADMIN et tente d'ajouter un rôle RF ou CORF,
        // on transforme la tentative en redirection vers la demande officielle de changement de RF/CORF
        if (!$this->isGranted('ROLE_ADMIN') && in_array($profil->getCode(), ['ROLE_RESP_FORMATION', 'ROLE_CO_RESP_FORMATION'], true)) {
            $formation = $formationRepository->find($centreId);
            if (!$formation) {
                return $this->json(['error' => 'La formation spécifiée n\'existe pas'], 400);
            }

            $authorizedComposantes = $this->getAuthorizedComposantes($composanteRepository);
            $isAllowed = in_array($formation->getComposantePorteuse(), $authorizedComposantes, true)
                || $formation->getComposantesInscription()->exists(fn($key, $c) => in_array($c, $authorizedComposantes, true));
            if (!$isAllowed) {
                return $this->json(['error' => 'Vous n\'avez pas accès à cette formation'], 403);
            }

            $typeRf = ($profil->getCode() === 'ROLE_RESP_FORMATION') ? TypeRfEnum::RF->value : TypeRfEnum::CORF->value;
            $redirectUrl = $this->generateUrl('app_formation_change_rf', [
                'formation' => $formation->getId(),
                'user' => $user->getId(),
                'typeRf' => $typeRf,
            ]);

            return $this->json([
                'redirectModal' => $redirectUrl,
                'message' => 'L\'attribution d\'un rôle de (co-)responsable de formation nécessite une demande officielle soumise au conseil. Redirection vers le formulaire de demande...',
            ]);
        }

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

        // Contrôle d'accès par composante pour les utilisateurs non-admins
        if (!$this->isGranted('ROLE_ADMIN')) {
            $authorizedComposantes = $this->getAuthorizedComposantes($composanteRepository);
            if ($centreType === CentreGestionEnum::CENTRE_GESTION_COMPOSANTE && !in_array($centre, $authorizedComposantes, true)) {
                return $this->json(['error' => 'Vous n\'avez pas accès à cette composante'], 403);
            }
            if ($centreType === CentreGestionEnum::CENTRE_GESTION_FORMATION) {
                $isAllowed = in_array($centre->getComposantePorteuse(), $authorizedComposantes, true)
                    || $centre->getComposantesInscription()->exists(fn($key, $c) => in_array($c, $authorizedComposantes, true));
                if (!$isAllowed) {
                    return $this->json(['error' => 'Vous n\'avez pas accès à cette formation'], 403);
                }
            }
            if ($centreType === CentreGestionEnum::CENTRE_GESTION_PARCOURS) {
                $pFormation = $centre->getFormation();
                $isAllowed = ($pFormation !== null && in_array($pFormation->getComposantePorteuse(), $authorizedComposantes, true))
                    || ($centre->getComposanteInscription() !== null && in_array($centre->getComposanteInscription(), $authorizedComposantes, true));
                if (!$isAllowed) {
                    return $this->json(['error' => 'Vous n\'avez pas accès à ce parcours'], 403);
                }
            }
        }

        $nCentre = new UserProfil()
            ->setUser($user)
            ->setCampagneCollecte($this->getCampagneCollecte())
            ->setProfil($profil);
        $entityManager->persist($nCentre);

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

        $eventDispatcher->dispatch($event, $event::NOTIF_ADD_CENTRE);

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
            $centre = $profil->getCentre() ?? CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT;
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $authorizedComposantes = $isAdmin ? [] : $this->getAuthorizedComposantes($composanteRepository);

            $composantes = $isAdmin
                ? $composanteRepository->findAll()
                : $authorizedComposantes;

            $formations = $isAdmin
                ? $formationRepository->findByCampagneCollecte($this->getCampagneCollecte())
                : $formationRepository->findByCampagneAndComposantes($this->getCampagneCollecte(), $authorizedComposantes);

            $parcours = $isAdmin
                ? $parcoursRepository->findByCampagneCollecte($this->getCampagneCollecte())
                : $parcoursRepository->findByCampagneAndComposantes($this->getCampagneCollecte(), $authorizedComposantes);

            return match ($centre) {
                CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $this->render('user_profils/_config_profil_etablissement.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'etablissements' => $etablissementRepository->findAll()
                ]),
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $this->render('user_profils/_config_profil_composante.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'composantes' => $composantes,
                ]),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $this->render('user_profils/_config_profil_formation.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'formations' => $formations,
                ]),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $this->render('user_profils/_config_profil_parcours.html.twig', [
                    'user' => $user,
                    'profil' => $profil,
                    'parcours' => $parcours,
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

            // Interdiction de suppression directe d'un RF/CORF pour les non-admins : redirection vers la demande
            if (!$this->isGranted('ROLE_ADMIN') && in_array($profil->getCode(), ['ROLE_RESP_FORMATION', 'ROLE_CO_RESP_FORMATION'], true)) {
                $formation = $userProfil->getFormation();
                if ($formation !== null) {
                    $typeRf = ($profil->getCode() === 'ROLE_RESP_FORMATION') ? TypeRfEnum::RF->value : TypeRfEnum::CORF->value;
                    $redirectUrl = $this->generateUrl('app_formation_change_rf', [
                        'formation' => $formation->getId(),
                        'typeRf' => $typeRf,
                    ]);

                    return $this->json([
                        'redirectModal' => $redirectUrl,
                        'message' => 'La suppression d’un (co-)responsable de formation nécessite une demande officielle soumise au conseil. Redirection vers le formulaire de demande...',
                    ]);
                }

                return $this->json([
                    'error' => 'La suppression directe d’un (co-)responsable de formation est réservée aux administrateurs. Pour modifier un (co-)responsable, veuillez soumettre une demande de changement depuis la page de la formation concernée.'
                ], Response::HTTP_FORBIDDEN);
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
