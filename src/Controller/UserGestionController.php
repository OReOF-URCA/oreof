<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UserGestionController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Ldap;
use App\Entity\User;
use App\Events\AddCentreFormationEvent;
use App\Events\AddCentreParcoursEvent;
use App\Events\UserEvent;
use App\Form\UserAddType;
use App\Repository\FicheMatiereRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ProfilRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/user/gestion')]
class UserGestionController extends BaseController
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UserRepository           $userRepository,
    ) {
    }

    #[Route('/ajout/utilisateur', name: 'app_user_missing')]
    public function ajoutUtilisateur(
        ProfilRepository $profilRepository,
        Ldap                   $ldap,
        EntityManagerInterface $entityManager,
        ParcoursRepository     $parcoursRepository,
        FicheMatiereRepository $ficheMatiereRepository,
        FormationRepository    $formationRepository,
        UserRepository         $userRepository,
        Request                $request
    ): Response {
        $user = new  User();

        $form = $this->createForm(UserAddType::class, $user, [
            'action' => $this->generateUrl(
                'app_user_missing',
                ['action' => $request->query->get('action'), 'id' => $request->query->get('id')]
            ),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            //Vérifier qu'il n'existe pas
            $exist = $userRepository->findOneBy(['email' => $email]);
            if ($exist !== null) {
                $ldapUser = $ldap->getDatas($email);
                if ($ldapUser === null) {
                    return $this->json('Cet utilisateur n\'existe pas dans le LDAP !', 500);
                }
                $exist->setNom($ldapUser['nom']);
                $exist->setPrenom($ldapUser['prenom']);
                $exist->setUsername($ldapUser['username']);
                $entityManager->flush();
                return $this->json('Cet utilisateur existe déjà ! Données LDAP mises à jour', 500);
            }

            //récupération depuis le LDAP
            $ldapUser = $ldap->getDatas($email);
            if ($ldapUser === null) {
                return $this->json('Cet utilisateur n\'existe pas dans le LDAP !', 500);
            }

            $existUsername = $userRepository->findOneBy(['username' => $ldapUser['username']]);
            if ($existUsername !== null) {
                return $this->json('Le login de cet utilisateur est déjà présent !', 500);
            }


            //ajout des données
            $user->setNom($ldapUser['nom']);
            $user->setPrenom($ldapUser['prenom']);
            $user->setUsername($ldapUser['username']);
            $user->setIsEnable(true);
            $user->setIsValideAdministration(true);
            $entityManager->persist($user);
            //ajout des droits et du centre

            switch ($request->query->get('action')) {
                case 'responsableFicheMatiere':
                    $fiche = $ficheMatiereRepository->find($request->query->get('id'));
                    //pas besoin d'envoyer un mail dans ce cas
                    if ($fiche !== null) {
                        $fiche->setResponsableFicheMatiere($user);
                        $entityManager->flush();

                        return $this->json(true);
                    }

                    return $this->json('Une erreur est survenue !', 500);

                case 'responsableParcours':
                    $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_PARCOURS']);
                    if ($profil === null) {
                        return $this->json(['error' => 'Profil ROLE_RESP_PARCOURS non trouvé'], 500);
                    }
                    $parcours = $parcoursRepository->find($request->query->get('id'));
                    if ($parcours !== null) {
                        // retirer l'ancien resp des centres et droits et envoyer mail
                        $event = new AddCentreParcoursEvent($parcours, $parcours->getRespParcours(), $profil, $this->getCampagneCollecte());
                        $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);

                        $parcours->setCoResponsable($user);
                        $event = new AddCentreParcoursEvent(
                            $parcours,
                            $user,
                            $profil,
                            $this->getCampagneCollecte()
                        );
                        $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);

                        $entityManager->flush();

                        return $this->json(true);
                    }

                    return $this->json('Une erreur est survenue !', 500);
                case 'responsableFormation':
                    $profil = $profilRepository->findOneBy(['code' => 'ROLE_RESP_FORMATION']);

                    if ($profil === null) {
                        return $this->json(['error' => 'Profil ROLE_RESP_FORMATION non trouvé'], 500);
                    }


                    $formation = $formationRepository->find($request->query->get('id'));
                    if ($formation !== null) {
                        // retirer l'ancien resp des centres et droits et envoyer mail
                        $event = new AddCentreFormationEvent($formation, $formation->getResponsableMention(), $profil, $this->getCampagneCollecte());
                        $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::REMOVE_CENTRE_FORMATION);
                        // ajouter le nouveau resp, ajouter centre et droits et envoyer mail
                        $formation->setResponsableMention($user);
                        $event = new AddCentreFormationEvent(
                            $formation,
                            $user,
                            $profil, $this->getCampagneCollecte()
                        );
                        $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::ADD_CENTRE_FORMATION);

                        $entityManager->flush();

                        return $this->json(true);
                    }

                    return $this->json('Une erreur est survenue !', 500);
                case 'coResponsableParcours':
                    $profil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_PARCOURS']);

                    if ($profil === null) {
                        return $this->json(['error' => 'Profil ROLE_CO_RESP_PARCOURS non trouvé'], 500);
                    }

                    $parcours = $parcoursRepository->find($request->query->get('id'));
                    if ($parcours !== null) {
                        if ($parcours->getCoResponsable() !== null) {
                            // retirer l'ancien resp des centres et droits et envoyer mail
                            $event = new AddCentreParcoursEvent($parcours, $parcours->getCoResponsable(),
                                $profil, $this->getCampagneCollecte());
                            $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::REMOVE_CENTRE_PARCOURS);
                        }
                        // ajouter le nouveau resp, ajouter centre et droits et envoyer mail
                        $parcours->setCoResponsable($user);
                        $event = new AddCentreParcoursEvent(
                            $parcours,
                            $user,
                            $profil, $this->getCampagneCollecte()
                        );
                        $this->eventDispatcher->dispatch($event, AddCentreParcoursEvent::ADD_CENTRE_PARCOURS);

                        $entityManager->flush();

                        return $this->json(true);
                    }

                    return $this->json('Une erreur est survenue !', 500);
                case 'coResponsableMention':
                    $profil = $profilRepository->findOneBy(['code' => 'ROLE_CO_RESP_FORMATION']);

                    if ($profil === null) {
                        return $this->json(['error' => 'Profil ROLE_CO_RESP_FORMATION non trouvé'], 500);
                    }


                    $formation = $formationRepository->find($request->query->get('id'));
                    if ($formation !== null) {

                        if ($formation->getCoResponsable() !== null) {
                            // retirer l'ancien resp des centres et droits et envoyer mail
                            $event = new AddCentreFormationEvent($formation, $formation->getCoResponsable(), $profil, $this->getCampagneCollecte());
                            $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::REMOVE_CENTRE_FORMATION);
                        }
                        // ajouter le nouveau resp, ajouter centre et droits et envoyer mail
                        $formation->setCoResponsable($user);

                        $event = new AddCentreFormationEvent(
                            $formation,
                            $user,
                            $profil, $this->getCampagneCollecte()
                        );
                        $this->eventDispatcher->dispatch($event, AddCentreFormationEvent::ADD_CENTRE_FORMATION);
                        $entityManager->flush();

                        return $this->json(true);
                    }
                    return $this->json('Une erreur est survenue !', 500);
            }
        }

        return $this->render('user/add.html.twig', ['form' => $form->createView(),]);
    }
//
//    #[
//        Route('/ajout/utilisateur/verification', name: 'app_user_missing_ldap')]
//    public function ajoutLdap(): Response
//    {
//        return $this->render('user/add.html.twig');
//    }
//
    #[Route('/valid/admin/{user}', name: 'app_user_gestion_valid_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function validAdmin(User $user): Response
    {
        if ($user->isIsValidDpe() === false) {
            $user->setDateValideDpe(new DateTime());
            $user->setIsValidDpe(true);
        }

        $user->setIsEnable(true);
        $user->setComposanteDemande(null);
        $user->setEtablissementDemande(null);
        $user->setIsValideAdministration(true);
        $user->setDateValideAdministration(new DateTime());

        $this->userRepository->save($user, true);

        $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::USER_VALIDE_ADMIN);

        return $this->redirectToRoute('app_user_profil_attente');
    }

    #[Route('/refuser/admin/{user}', name: 'app_user_gestion_refuser_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function refuserAdmin(
        Request $request,
        User $user): Response
    {
        $motif = $request->request->get('motif');
        $this->userRepository->remove($user, true);

        $userEvent = new UserEvent($user);
        $userEvent->setMotif($motif);
        $this->eventDispatcher->dispatch($userEvent, UserEvent::USER_REFUSER_ADMIN);

        return $this->json(true);
    }


    #[Route('/valid/dpe/{user}', name: 'app_user_gestion_valid_dpe')]
    public function validDpe(User $user): Response
    {
        // $this->denyAccessUnlessGranted('CAN_EDIT_CENTRE');//todo: faire sur manage composante
        if (count($user->getUserProfils()) > 0) {
            $user->setDateValideDpe(new DateTime());
            $user->setIsValidDpe(true);
            $user->setDateValideAdministration(new DateTime());

            $this->userRepository->save($user, true);
            $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::USER_VALIDE_DPE);
            $this->addFlash('success', 'L\'utilisateur a bien été validé !');
            return $this->redirectToRoute('app_user_profil_attente');
        }

        $this->addFlash('error', 'L\'utilisateur doit avoir au moins un centre !');

        return $this->json(false, 500);
    }

    #[Route('/revoque/admin/{user}', name: 'app_user_gestion_revoque_admin')]
    #[IsGranted('ROLE_ADMIN')]
    public function revoqueAdmin(User $user): Response
    {
        $user->setIsEnable(false);
        $user->setIsValideAdministration(false);
        $user->setDateValideAdministration(new DateTime());

        $this->userRepository->save($user, true);
        $this->eventDispatcher->dispatch(new UserEvent($user), UserEvent::USER_REVOQUE_ADMIN);

        return $this->redirectToRoute('app_user_profil_attente');
    }
}
