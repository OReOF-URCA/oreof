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
use App\Service\DataTableBuilder;
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
    public function index(
        DataTableBuilder $builder
    ): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        $composanteId = null;
        if ($this->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $this->getUser()?->getComposanteResponsableDpe()->first()
        ])) {
            foreach ($this->getUser()?->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composanteId = $centre->getComposante()->getId();
                    break;
                }
            }
        }

        $typeCentreChoices = [];
        foreach (CentreGestionEnum::cases() as $case) {
            if ($case->value !== '') {
                $typeCentreChoices[$case->value] = $case->getLibelle();
            }
        }

        $table = $builder
            ->setEntity(UserProfil::class)
            ->setPerPage(20)
            ->setDefaultSort('user.nom')
            ->addBaseJoin('inner', 'e.user', 'u')
            ->addBaseJoin('inner', 'e.profil', 'p')
            ->addBaseWhere('u.isEnable = :isEnable')
            ->addBaseWhere('u.isDeleted = :isDeleted')
            ->addBaseWhere('(IDENTITY(e.campagneCollecte) = :campagneId OR e.campagneCollecte IS NULL)')
            ->addBaseParameter('isEnable', true)
            ->addBaseParameter('isDeleted', false)
            ->addBaseParameter('campagneId', $this->getCampagneCollecte()?->getId())
            ->addColumn('user.nom', [
                'label' => 'Nom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('user.prenom', [
                'label' => 'Prénom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('user.username', [
                'label' => 'Login URCA',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('profil', [
                'label' => 'Profil',
                'type' => 'entity',
                'entity' => Profil::class,
                'entity_label' => 'libelle',
                'filterable' => $isAdmin,
                'sortable' => true,
            ])
            ->addColumn('profil.centre', [
                'label' => 'Type centre',
                'sortable' => true,
                'filterable' => $isAdmin,
                'type' => 'select',
                'choices' => $typeCentreChoices,
                'template' => 'config/user_profil/_datatable_type_centre.html.twig',
                'searchable' => false,
            ])
            ->addColumn('displayCentre()', [
                'label' => 'Centre',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'config/user_profil/_datatable_actions.html.twig',
                'class' => 'text-right',
            ]);

        if ($composanteId !== null) {
            $table
                ->addBaseJoin('left', 'e.formation', 'f')
                ->addBaseJoin('left', 'e.parcours', 'pa')
                ->addBaseJoin('left', 'pa.formation', 'pf')
                ->addBaseWhere('(
                    IDENTITY(e.composante) = :composanteId
                    OR IDENTITY(f.composantePorteuse) = :composanteId
                    OR IDENTITY(pf.composantePorteuse) = :composanteId
                )')
                ->addBaseParameter('composanteId', $composanteId);
        }

        return $this->render('config/user_profil/index.html.twig', [
            'table' => $table->build(),
        ]);
    }

    #[Route('/attente-validation', name: 'attente', methods: ['GET'])]
    public function attente(
        DataTableBuilder $builder
    ): Response
    {
        $isDpe = false;
        $composanteId = null;

        if ($this->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $this->getUser()?->getComposanteResponsableDpe()->first()
        ])) {
            $isDpe = true;
            foreach ($this->getUser()?->getUserProfils() as $userProfil) {
                if ($userProfil->getComposante() !== null) {
                    $composanteId = $userProfil->getComposante()->getId();
                    break;
                }
            }
        }

        $table = $builder
            ->setEntity(User::class)
            ->setPerPage(20)
            ->setDefaultSort('nom')
            ->addBaseWhere('e.isEnable = :isEnable')
            ->addBaseWhere('e.dateDemande IS NOT NULL')
            ->addBaseWhere('e.isDeleted = :isDeleted')
            ->addBaseParameter('isEnable', false)
            ->addBaseParameter('isDeleted', false)
            ->addColumn('nom', [
                'label' => 'Nom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('prenom', [
                'label' => 'Prénom',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('email', [
                'label' => 'Email',
                'sortable' => false,
                'filterable' => true,
            ])
            ->addColumn('username', [
                'label' => 'Login URCA',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('userProfils', [
                'label' => 'Centre(s) / Droits',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'config/user_profil/_datatable_attente_centres.html.twig',
            ])
            ->addColumn('dateDemande', [
                'label' => 'Date demande',
                'sortable' => true,
                'filterable' => false,
                'searchable' => false,
                'format' => 'datetime',
            ])
            ->addColumn('serviceDemande', [
                'label' => 'Service/fonction',
                'sortable' => false,
                'filterable' => false,
            ])
            ->addColumn('composanteDemande', [
                'label' => 'Validé DPE ?',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'config/user_profil/_datatable_attente_dpe.html.twig',
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'config/user_profil/_datatable_attente_actions.html.twig',
            ]);


        if ($isDpe && $composanteId !== null) {
            $table
                ->addBaseWhere('IDENTITY(e.composanteDemande) = :composanteId')
                ->addBaseParameter('composanteId', $composanteId);
        } elseif ($isDpe) {
            // DPE sans composante identifiée : on retourne rien
            $table->addBaseWhere('1 = 0');
        } else {
            // Admin : restreindre aux non validés admin
            $table->addBaseWhere('e.isValideAdministration = :isValideAdmin')
                ->addBaseParameter('isValideAdmin', false);
        }

        return $this->render('config/user_profil/attente.html.twig', [
            'table' => $table->build(),
            'dpe' => $isDpe,
        ]);
    }

    #[Route('/delete-demande/{id}', name: 'delete_demande', methods: ['POST', 'DELETE'])]
    public function deleteDemande(
        Request $request,
        User $user,
        UserRepository $userRepository,
        UserProfilRepository $userProfilRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = $request->request->get('_token') ?? JsonRequest::getValueFromRequest($request, 'csrf');
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $token)) {
            foreach ($user->getUserProfils() as $centre) {
                $userProfilRepository->remove($centre, true);
            }

            $user->setIsDeleted(true);
            $userRepository->save($user, true);

            $this->addFlash('success', 'La demande a bien été supprimée.');
        }

        return $this->redirectToRoute('app_user_profil_attente');
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
