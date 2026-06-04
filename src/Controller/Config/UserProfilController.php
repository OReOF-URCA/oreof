<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/UserProfilController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Controller\Config;

use App\Controller\BaseController;
use App\Entity\Profil;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Events\NotifUpdateUserProfilEvent;
use App\Repository\ComposanteRepository;
use App\Repository\EtablissementRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Repository\UserProfilRepository;
use App\Repository\UserRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/utilisateurs/profils', name: 'app_user_profil_')]
class UserProfilController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('config/user_profil/index.html.twig');
    }

    #[Route('/attente-validation', name: 'attente', methods: ['GET'])]
    public function attente(
        UserRepository $userRepository
    ): Response
    {
        //todo: gérer par le responsable de DPE ?? pour affecter les droits et "pré-valider" les utilisateurs
        if ($this->isGranted('ROLE_ADMIN')) {
            $users = $userRepository->findNotEnableAvecDemande();
            $dpe = false;
        } elseif (
            $this->isGranted('MANAGE', [
                'route' => 'app_composante',
                'subject' => $this->getUser()?->getComposanteResponsableDpe()->first()
            ])
        ) {
            $dpe = true;
            $users = [];
            /** @var UserProfil $userProfil */
            foreach ($this->getUser()?->getUserProfils() as $userProfil) {
                if ($userProfil->getComposante() !== null) {
                    $users[] = $userRepository->findByComposanteNotEnableAvecDemande($userProfil->getComposante());
                }
            }
            $users = array_merge(...$users);
        }

        return $this->render('config/user_profil/attente.html.twig', [
            'users' => $users,
            'dpe' => $dpe
        ]);
    }

    #[Route('/add/profil/{user}', name: 'add')]
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

        $role = $profilRepository->find($data['role']);
        if ($role === null) {
            return $this->json(['error' => 'Ce rôle n\'existe pas'], 400);
        }

        //vérifier si le centre existe dans l'enum
        if (!CentreGestionEnum::has($data['centre'])) {
            return $this->json(['error' => 'Ce centre n\'existe pas'], 400);
        }

        $userProfil = new UserProfil();
        $userProfil->setUser($user);
        $userProfil->setProfil($role);

        // selon le centre, la composante, l'établissement, la formation ou le parcours, on vérifie que la valeur du centre n'est pas déjà existante, si oui, on modifie le profil existant, si non, on crée un nouveau profil

        $event = false;
        switch ($data['centre']) {
            case CentreGestionEnum::CENTRE_GESTION_COMPOSANTE->value:
                $composante = $composanteRepository->find($data['cible']);
                if ($composante === null) {
                    return $this->json(['error' => 'Cette composante n\'existe pas'], 400);
                }

                // Vérifier si l'utilisateur a déjà un profil pour cette composante
                $existingProfil = $userProfilRepository->findOneBy([
                    'user' => $user,
                    'profil' => $role,
                    'composante' => $composante
                ]);

                if ($existingProfil === null) {
                    $userProfil->setComposante($composante);
                    $entityManager->persist($userProfil);
                    $event = NotifUpdateUserProfilEvent::ADD_USER_PROFIL;
                } else {
                    // Si le profil existe déjà, on met à jour les informations
                    $existingProfil->setProfil($role);
                    unset($userProfil);
                    $event = NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL;
                }

                break;
            case CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT->value:
                $etablissement = $etablissementRepository->find($data['cible']);
                if ($etablissement === null) {
                    return $this->json(['error' => 'Cet établissement n\'existe pas'], 400);
                }

                // Vérifier si l'utilisateur a déjà un profil pour cet établissement
                $existingProfil = $userProfilRepository->findOneBy([
                    'user' => $user,
                    'profil' => $role,
                    'etablissement' => $etablissement
                ]);
                if ($existingProfil === null) {
                    $userProfil->setEtablissement($etablissement);
                    $entityManager->persist($userProfil);
                    $event = NotifUpdateUserProfilEvent::ADD_USER_PROFIL;
                } else {
                    // Si le profil existe déjà, on met à jour les informations
                    $existingProfil->setProfil($role);
                    unset($userProfil);
                    $event = NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL;
                }
                break;
            case CentreGestionEnum::CENTRE_GESTION_FORMATION->value:
                $formation = $formationRepository->find($data['cible']);
                if ($formation === null) {
                    return $this->json(['error' => 'Cette formation n\'existe pas'], 400);
                }

                // Vérifier si l'utilisateur a déjà un profil pour cette formation
                $existingProfil = $userProfilRepository->findOneBy([
                    'user' => $user,
                    'profil' => $role,
                    'formation' => $formation
                ]);
                if ($existingProfil === null) {
                    $userProfil->setFormation($formation);
                    $entityManager->persist($userProfil);
                    $event = NotifUpdateUserProfilEvent::ADD_USER_PROFIL;
                } else {
                    // Si le profil existe déjà, on met à jour les informations
                    $existingProfil->setProfil($role);
                    unset($userProfil);
                    $event = NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL;
                }
                break;
            case CentreGestionEnum::CENTRE_GESTION_PARCOURS->value:
                $parcours = $parcoursRepository->find($data['cible']);
                if ($parcours === null) {
                    return $this->json(['error' => 'Ce parcours n\'existe pas'], 400);
                }

                // Vérifier si l'utilisateur a déjà un profil pour ce parcours
                $existingProfil = $userProfilRepository->findOneBy([
                    'user' => $user,
                    'profil' => $role,
                    'parcours' => $parcours
                ]);
                if ($existingProfil === null) {
                    $userProfil->setParcours($parcours);
                    $entityManager->persist($userProfil);
                    $event = NotifUpdateUserProfilEvent::ADD_USER_PROFIL;
                } else {
                    // Si le profil existe déjà, on met à jour les informations
                    $existingProfil->setProfil($role);
                    unset($userProfil);
                    $event = NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL;
                }
                break;
        }

        if ($event !== false) {
            $this->entityManager->flush();
            $eventDispatcher->dispatch(new NotifUpdateUserProfilEvent($existingProfil ?? $userProfil), $event);
        }

        if ($event === NotifUpdateUserProfilEvent::UPDATE_USER_PROFIL) {
            return $this->json(['success' => true, 'message' => 'Profil modifié avec succès']);
        }

        return $this->json(['success' => true, 'message' => 'Profil ajouté avec succès']);
    }

    #[Route('/liste', name: 'liste', methods: ['GET'])]
    public function liste(
        Request              $request,
        UserProfilRepository $userProfilRepository,
        ProfilRepository     $profilRepository
    ): Response
    {
        $sort = $request->query->get('sort') ?? 'nom';
        $direction = $request->query->get('direction') ?? 'asc';
        $params = $request->query->all();

        $composante = null;
        if ($this->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $this->getUser()?->getComposanteResponsableDpe()->first()
        ])) {
            foreach ($this->getUser()?->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composante = $centre->getComposante(); // au moins une composante, todo: si plusieurs ?
                    break;
                }
            }
        }

        $users = $userProfilRepository->findForListe($this->getCampagneCollecte(), $composante, $params);

        return $this->render('config/user_profil/_liste.html.twig', [
            'users' => $users,
            'sort' => $sort,
            'direction' => $direction,
            'campagneCollecte' => $this->getCampagneCollecte(),
            'profils' => $profilRepository->findAll(),
            'centres' => CentreGestionEnum::cases(),
            'params' => $params,
        ]);
    }


    /**
     * @throws JsonException
     */
    #[Route('/change-role/{id}', name: 'roles', methods: ['POST'])]
    public function changeRole(
        Request        $request,
        UserRepository $userRepository,
        User           $user
    ): Response
    {
        $data = JsonRequest::getFromRequest($request);
        $roles = $user->getRoles();

        if ($data['checked']) {
            $roles[] = $data['role'];
        } else {
            $roles = array_diff($roles, [$data['role']]);
        }
        $user->setRoles($roles);
        $userRepository->save($user, true);

        return $this->json(true);
    }
}
