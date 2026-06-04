<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/UserController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Config;

use App\Classes\Ldap;
use App\Classes\Mailer;
use App\Controller\BaseController;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Form\UserHorsUrcaType;
use App\Form\UserLdapType;
use App\Form\UserType;
use App\Repository\ProfilRepository;
use App\Repository\UserProfilRepository;
use App\Repository\UserRepository;
use App\Utils\JsonRequest;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/utilisateurs')]
class UserController extends BaseController
{
    #[Route('/repertoire', name: 'app_user_repertoire', methods: ['GET'])]
    public function repertoire(): Response
    {
        return $this->render('config/user/repertoire.html.twig');
    }

    #[Route('/repertoire/liste', name: 'app_user_repertoire_liste', methods: ['GET'])]
    public function repertoireListe(
        Request        $request,
        UserRepository $userRepository
    ): Response
    {
        $sort = $request->query->get('sort') ?? 'nom';
        $direction = $request->query->get('direction') ?? 'asc';
        $q = $request->query->get('q') ?? null;

        if ($this->isGranted('ROLE_ADMIN')) {
            if ($q) {
                $users = $userRepository->findEnableBySearch($this->getCampagneCollecte(), $q, $sort, $direction);
            } else {
                $users = $userRepository->findEnable($this->getCampagneCollecte(), $sort, $direction);
            }
        } elseif (
            $this->isGranted('MANAGE', ['route' => 'app_composante', 'subject' => 'composante'])
        ) {
            /** @var UserProfil $centre */
            foreach ($this->getUser()?->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composante = $centre->getComposante(); //au moins une composante, todo: si plusieurs ?
                }
            }
            if ($composante !== null) {
                if ($q) {
                    $users = $userRepository->findByComposanteEnableBySearch($this->getCampagneCollecte(), $composante, $q, $sort, $direction);
                } else {
                    $users = $userRepository->findByComposanteEnable($this->getCampagneCollecte(), $composante, $sort, $direction);
                }
            } else {
                $users = [];
            }
        }

        return $this->render('config/user/_repertoireListe.html.twig', [
            'users' => $users,
            'sort' => $sort,
            'direction' => $direction,
            'campagneCollecte' => $this->getCampagneCollecte()
        ]);
    }

    #[Route('/ajouter-ldap', name: 'app_user_new_ldap', methods: ['GET'])]
    public function newLdap(
        Request $request
    ): Response {
        $dpe = false;

        if ($request->query->has('access') && $request->query->get('access') === 'dpe') {
            $dpe = true;
        }


        $user = new User();
        $form = $this->createForm(UserLdapType::class, $user, [
            'action' => $dpe ? $this->generateUrl('app_user_new_ldap', ['access' => 'dpe']) : $this->generateUrl('app_user_new_ldap'),
        ]);

        return $this->render('config/user/new-ldap.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ajouter-hors-urca', name: 'app_user_new_hors_urca', methods: ['GET'])]
    public function horsUrca(
        Request $request
    ): Response {
        $user = new User();
        $form = $this->createForm(UserHorsUrcaType::class, $user, [
            'action' => $this->generateUrl('app_user_new_hors_urca'),
        ]);

        return $this->render('config/user/new-hors-urca.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ajouter-ldap', name: 'app_user_new_ldap_valide', methods: ['POST'])]
    public function saveLdap(
        Mailer         $myMailer,
        Ldap           $ldap,
        Request        $request,
        UserRepository $userRepository
    ): Response {
        $dpe = false;

        if ($request->query->has('access') && $request->query->get('access') === 'dpe') {
            $dpe = true;
        }

        $email = $request->request->get('user_ldap_email');

        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user !== null) {
            // utilisateur déjà en BDD, on le réactive et on met à jour ses infos depuis le LDAP

            $dataUsers = $ldap->getDatas($email);
            if ($dataUsers === null) {
                $this->addFlash('danger', 'L\'utilisateur n\'a pas été trouvé dans l\'annuaire LDAP');
                return $this->json(false, 500);
            }
            $user->setIsDeleted(false);
            $user->setUsername($dataUsers['username']);
            $user->setNom($dataUsers['nom']);
            $user->setPrenom($dataUsers['prenom']);
            $this->addFlash('info', 'L\'utilisateur existant a été mis à jour avec les informations LDAP.');
        } else {
            $user = new User();
            $user->setEmail($email);
            $dataUsers = $ldap->getDatas($email);
            if ($dataUsers === null) {
                $this->addFlash('danger', 'L\'utilisateur n\'a pas été trouvé dans l\'annuaire LDAP');
                return $this->json(false, 500);
            }
            $user->setUsername($dataUsers['username']);
            $user->setNom($dataUsers['nom']);
            $user->setPrenom($dataUsers['prenom']);
        }

        $user->setIsEnable(true);
        if ($dpe === false) {
            $user->setIsValideAdministration(true);
            $user->setDateValideAdministration(new DateTime());
            $this->addFlash('success', 'L\'utilisateur a été ajouté avec succès');

            $myMailer->initEmail();
            $myMailer->setTemplate(
                'mails/user/acces_ajoute.txt.twig',
                ['user' => $user]
            );
        } else {
            $admins = $userRepository->findByRole('ROLE_ADMIN');

            $user->setIsValidDpe(true);
            $user->setComposanteDemande($this->getUser()?->getComposanteResponsableDpe()->first());
            $user->setDateDemande(new DateTime());
            $user->setDateValideDpe(new DateTime());
            $this->addFlash('success', 'L\'utilisateur a été ajouté avec succès, il est en attente de validation par le SES');
            // mail pour l'administrateur
            foreach ($admins as $admin) {
                $myMailer->initEmail();
                $myMailer->setTemplate(
                    'mails/user/ajout_oreof_dpe.txt.twig',
                    [
                        'user' => $user,
                        'dpe' => $this->getUser()]
                );
                $myMailer->sendMessage([$admin->getEmail()], '[ORéOF] Nouvel ajout d\'un utilisateur par un DPE');
            }
            $myMailer->initEmail();
            $myMailer->setTemplate(
                'mails/user/ajout_oreof.txt.twig',
                ['user' => $user, 'dpe' => $this->getUser()]
            );
        }
        $myMailer->sendMessage([$user->getEmail()], '[ORéOF] Accès ORéOF');
        $userRepository->save($user, true);

        // mail pour le nouvel utilisateur

        return $this->json([
            'success' => true,
            'url' => $this->generateUrl('app_user_profils_gestion', ['user' => $user->getId()])
        ]);
    }

    #[Route('/ajouter-hors-urca', name: 'app_user_new_hors_urca_valide', methods: ['POST'])]
    public function saveHorsUrca(
        UserPasswordHasherInterface $passwordEncoder,
        Mailer                      $myMailer,
        Request                     $request,
        UserRepository              $userRepository
    ): Response {
        $email = $request->request->get('user_ldap_email');
        $nom = $request->request->get('user_nom');
        $prenom = $request->request->get('user_prenom');

        $user = $userRepository->findOneBy(['email' => $email]);

        // genre un mot de passe aléatoire de 8 caractères
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#!?&@'), 0, 8);

        if ($user !== null) {
            $user->setIsDeleted(false);
            $passwordEncode = $passwordEncoder->hashPassword($user, $password);
        } else {
            $user = new User();
            $passwordEncode = $passwordEncoder->hashPassword($user, $password);
            $user->setEmail($email);
            $user->setUsername($email);
            $user->setNom($nom);
            $user->setPrenom($prenom);
        }
        $user->setPassword($passwordEncode);

        $user->setIsEnable(true);
        $user->setIsValideAdministration(true);
        $user->setDateValideAdministration(new DateTime());
        $this->addFlash('success', 'L\'utilisateur a été ajouté avec succès, un email lui a été envoyé.');

        $myMailer->initEmail();
        $myMailer->setTemplate(
            'mails/user/acces_ajoute_hors_urca.html.twig',
            ['user' => $user,
                'password' => $password]
        );
        $myMailer->sendMessage([$user->getEmail()], '[ORéOF] Accès ORéOF');

        $userRepository->save($user, true);

        return $this->json([
            'success' => true,
            'url' => $this->generateUrl('app_user_profils_gestion', ['user' => $user->getId()])
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(
        User           $user
    ): Response {
        return $this->render('config/user/show.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/show-attente/{id}', name: 'app_user_show_attente', methods: ['GET'])]
    public function showAttente(
        Request        $request,
        ProfilRepository $profilRepository,
        User           $user
    ): Response {
        $dpe = (bool)$request->query->get('dpe', false);
        if ($dpe) {
            $profils = $profilRepository->findByDpe();
        } else {
            $profils = $profilRepository->findAll();
        }

        return $this->render('config/user_profil/_show_attente.html.twig', [
            'user' => $user,
            'typeCentres' => CentreGestionEnum::cases(),
            'centresUser' => $user->getUserProfils(),
            'profils' => $profils,
            'dpe' => $dpe
        ]);
    }
//
//    /**
//     * @throws JsonException
//     */
//    #[Route('/change-role/{id}', name: 'app_user_roles', methods: ['POST'])]
//    public function changeRole(
//        Request        $request,
//        UserRepository $userRepository,
//        User           $user
//    ): Response {
//        $data = JsonRequest::getFromRequest($request);
//        $roles = $user->getRoles();
//
//        if ($data['checked']) {
//            $roles[] = $data['role'];
//        } else {
//            $roles = array_diff($roles, [$data['role']]);
//        }
//        $user->setRoles($roles);
//        $userRepository->save($user, true);
//
//        return $this->json(true);
//    }
//
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, User $user, UserRepository $userRepository): Response
    {
        $form = $this->createForm(
            UserType::class,
            $user,
            [
                'action' => $this->generateUrl('app_user_edit', ['id' => $user->getId()]),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([strtoupper($request->request->all()['user']['role'])]);
            $userRepository->save($user, true);

            return $this->json(true);
        }

        return $this->render('config/user/new.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @throws JsonException
     */
    #[Route('/{id}', name: 'app_user_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request              $request,
        User                 $user,
        UserRepository       $userRepository,
        UserProfilRepository $userProfilRepository
    ): Response {
        if ($this->isCsrfTokenValid(
            'delete' . $user->getId(),
            JsonRequest::getValueFromRequest($request, 'csrf')
        )) {
            foreach ($user->getUserProfils() as $centre) {
                $userProfilRepository->remove($centre, true);
            }

            $user->setIsDeleted(true);//on met le flag supprimé à true.
            $userRepository->save($user, true);
        }

        return $this->redirectToRoute('app_user_repertoire', [], Response::HTTP_SEE_OTHER);
    }
}
