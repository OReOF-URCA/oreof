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
use App\Controller\Traits\CsrfDeleteTrait;
use App\DataTable\UserRepertoireDataTable;
use App\DTO\TranslatableKey;
use App\Entity\User;
use App\Enums\CentreGestionEnum;
use App\Form\UserHorsUrcaType;
use App\Form\UserLdapType;
use App\Form\UserType;
use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Repository\ProfilRepository;
use App\Repository\UserProfilRepository;
use App\Repository\UserRepository;
use App\Service\DetailBuilder;
use App\Utils\TurboStreamResponseFactory;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs')]
class UserController extends BaseController
{
    use CsrfDeleteTrait;
    
    #[Route('/repertoire', name: 'app_user_repertoire', methods: ['GET', 'POST'])]
    public function repertoire(UserRepertoireDataTable $table): Response
    {
        return $this->render('config/user/repertoire.html.twig', [
            'table' => $table,
        ]);
    }

    #[Route('/ajouter-ldap', name: 'app_user_new_ldap', methods: ['GET', 'POST'])]
    public function newLdap(
        TurboStreamResponseFactory $turboStream,
        Request $request,
        Mailer $myMailer,
        Ldap $ldap,
        UserRepository $userRepository,
        ProfilRepository $profilRepository
    ): Response {
        $dpe = ($request->query->get('access') === 'dpe' || $request->query->get('acces') === 'dpe');

        $user = new User();
        $form = $this->createForm(UserLdapType::class, $user, [
            'action' => $dpe ? $this->generateUrl('app_user_new_ldap', ['access' => 'dpe']) : $this->generateUrl('app_user_new_ldap'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string)$user->getEmail());

            // 1. Vérification par adresse email en base de données
            $existingUserByEmail = $userRepository->findOneBy(['email' => $email]);
            if ($existingUserByEmail !== null) {
                return $this->handleExistingUser(
                    $existingUserByEmail,
                    $email,
                    $dpe,
                    $ldap,
                    $userRepository,
                    $profilRepository,
                    $turboStream,
                    sprintf("L'utilisateur « %s » existe déjà en base de données. Basculement sur la gestion de ses profils.", $existingUserByEmail->getDisplay())
                );
            }

            // 2. Recherche dans l'annuaire LDAP
            $ldapResult = $ldap->lookupUser($email);
            if (!$ldapResult->isSuccess()) {
                $errorMessage = $ldapResult->getErrorMessage() ?? "L'utilisateur n'a pas été trouvé dans l'annuaire LDAP.";
                return $turboStream->streamToastError($errorMessage);
            }

            $dataUsers = $ldapResult->getData();
            if ($dataUsers === null || empty($dataUsers['username'])) {
                return $turboStream->streamToastError("Données LDAP introuvables ou identifiant (uid) manquant pour cet utilisateur.");
            }

            $username = $dataUsers['username'];

            // 3. Vérification par login URCA en BDD (cas d'un collègue avec plusieurs emails : nom composé, mariage, alias...)
            $existingUserByUsername = $userRepository->findOneBy(['username' => $username]);
            if ($existingUserByUsername !== null) {
                $oldEmail = $existingUserByUsername->getEmail();
                $toastMessage = ($oldEmail !== null && strcasecmp($oldEmail, $email) !== 0)
                    ? sprintf(
                        "L'utilisateur « %s » existe déjà avec le login URCA « %s » (enregistré sous « %s »). Son adresse a été actualisée (« %s ») et vous avez été redirigé vers la gestion de ses profils.",
                        $existingUserByUsername->getDisplay(),
                        $username,
                        $oldEmail,
                        $email
                    )
                    : sprintf(
                        "L'utilisateur « %s » existe déjà avec le login URCA « %s ». Basculement sur la gestion de ses profils.",
                        $existingUserByUsername->getDisplay(),
                        $username
                    );

                return $this->handleExistingUser(
                    $existingUserByUsername,
                    $email,
                    $dpe,
                    $ldap,
                    $userRepository,
                    $profilRepository,
                    $turboStream,
                    $toastMessage,
                    $dataUsers
                );
            }

            // 4. Nouvel utilisateur : initialisation et enregistrement
            $user->setUsername($username);
            $user->setNom($dataUsers['nom']);
            $user->setPrenom($dataUsers['prenom']);
            $user->setIsEnable(true);
            $user->setIsValideAdministration(true);
            $user->setDateValideAdministration(new DateTime());

            if ($dpe) {
                $currentUser = $this->getUser();
                if (!$currentUser instanceof User) {
                    throw $this->createAccessDeniedException();
                }

                $user->setIsValidDpe(true);
                $composanteResponsableDpe = $currentUser->getComposanteResponsableDpe()->first();
                $user->setComposanteDemande($composanteResponsableDpe !== false ? $composanteResponsableDpe : null);
                $user->setDateValideDpe(new DateTime());
            }

            try {
                $userRepository->save($user, true);
            } catch (UniqueConstraintViolationException) {
                $existing = $userRepository->findOneBy(['username' => $username]) ?? $userRepository->findOneBy(['email' => $email]);
                $existingInfo = $existing ? sprintf(" (associé à « %s », email : « %s »)", $existing->getDisplay(), (string)$existing->getEmail()) : '';
                return $turboStream->streamToastError(
                    sprintf("Impossible d'enregistrer : le login URCA « %s » est déjà présent en base de données%s.", $username, $existingInfo)
                );
            }

            $myMailer->initEmail();
            $myMailer->setTemplate(
                'mails/user/acces_ajoute.txt.twig',
                ['user' => $user]
            );
            $myMailer->sendMessage([$user->getEmail()], '[ORéOF] Accès ORéOF');

            $profils = $dpe ? $profilRepository->findByDpe() : $profilRepository->findAll();

            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('user.gestion_profil.title', [], 'modal'),
                'Utilisateur : ' . $user->getDisplay(),
                'user_profils/_gestion_profils.html.twig',
                [
                    'user' => $user,
                    'profils' => $profils,
                ],
                '_ui/_footer_cancel.html.twig',
                []
            );
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user_new_ldap.new.title', [], 'modal'),
            '',
            'config/user/new-ldap.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [],
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        );
    }

    /**
     * @param array{username: string, nom: string, prenom: string, email: string}|null $dataUsers
     */
    private function handleExistingUser(
        User                       $existingUser,
        string                     $email,
        bool                       $dpe,
        Ldap                       $ldap,
        UserRepository             $userRepository,
        ProfilRepository           $profilRepository,
        TurboStreamResponseFactory $turboStream,
        string                     $toastMessage,
        ?array                     $dataUsers = null
    ): Response
    {
        $existingUser->setIsDeleted(false);
        $existingUser->setIsEnable(true);
        $existingUser->setIsValideAdministration(true);

        if ($dpe) {
            $currentUser = $this->getUser();
            if (!$currentUser instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $existingUser->setIsValidDpe(true);
            $composanteResponsableDpe = $currentUser->getComposanteResponsableDpe()->first();
            if ($composanteResponsableDpe !== false) {
                $existingUser->setComposanteDemande($composanteResponsableDpe);
            }
        }

        if ($dataUsers === null) {
            $ldapResult = $ldap->lookupUser($email);
            if ($ldapResult->isSuccess()) {
                $dataUsers = $ldapResult->getData();
            }
        }

        if ($dataUsers !== null) {
            if (!empty($dataUsers['username'])) {
                $existingUser->setUsername($dataUsers['username']);
            }
            if (!empty($dataUsers['nom'])) {
                $existingUser->setNom($dataUsers['nom']);
            }
            if (!empty($dataUsers['prenom'])) {
                $existingUser->setPrenom($dataUsers['prenom']);
            }
        }

        if ($email !== '' && filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $existingUser->setEmail($email);
        }

        try {
            $userRepository->save($existingUser, true);
        } catch (UniqueConstraintViolationException) {
            // Ne bloque pas si l'email ou un champ secondaire est en conflit
        }

        $profils = $dpe ? $profilRepository->findByDpe() : $profilRepository->findAll();

        return $turboStream->appendStreams(
            $turboStream->renderModalStreams(
                new TranslatableKey('user.gestion_profil.title', [], 'modal'),
                'Utilisateur : ' . $existingUser->getDisplay(),
                'user_profils/_gestion_profils.html.twig',
                [
                    'user' => $existingUser,
                    'profils' => $profils,
                ],
                '_ui/_footer_cancel.html.twig',
                []
            ),
            $turboStream->renderToastStream($toastMessage, 'info')
        );
    }

    #[Route('/ajouter-hors-urca', name: 'app_user_new_hors_urca', methods: ['GET', 'POST'])]
    public function horsUrca(
        TurboStreamResponseFactory $turboStream,
        UserPasswordHasherInterface $passwordEncoder,
        Mailer $myMailer,
        Request $request,
        UserRepository $userRepository,
        ProfilRepository $profilRepository
    ): Response {
        $user = new User();
        $form = $this->createForm(UserHorsUrcaType::class, $user, [
            'action' => $this->generateUrl('app_user_new_hors_urca'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $userRepository->findOneBy(['email' => $user->getEmail()]);

            $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#!?&@'), 0, 8);

            if ($existingUser !== null) {
                $user = $existingUser;
                $user->setIsDeleted(false);
                $passwordEncode = $passwordEncoder->hashPassword($user, $password);
            } else {
                $passwordEncode = $passwordEncoder->hashPassword($user, $password);
                $user->setUsername($user->getEmail());
            }
            $user->setPassword($passwordEncode);
            $user->setIsEnable(true);
            $user->setIsValideAdministration(true);
            $user->setDateValideAdministration(new DateTime());

            $myMailer->initEmail();
            $myMailer->setTemplate(
                'mails/user/acces_ajoute_hors_urca.html.twig',
                [
                    'user' => $user,
                    'password' => $password,
                ]
            );
            $myMailer->sendMessage([$user->getEmail()], '[ORéOF] Accès ORéOF');

            $userRepository->save($user, true);

            return $turboStream->streamOpenModalFromTemplates(
                new TranslatableKey('user.gestion_profil.title', [], 'modal'),
                'Utilisateur : ' . $user->getDisplay(),
                'user_profils/_gestion_profils.html.twig',
                [
                    'user' => $user,
                    'profils' => $profilRepository->findAll(),
                ],
                '_ui/_footer_cancel.html.twig',
                []
            );
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user_new_hors_urca.new.title', [], 'modal'),
            '',
            'config/user/new-hors-urca.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            [],
            $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK
        );
    }


    #[Route('/{id}', name: 'app_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        TurboStreamResponseFactory $turboStream,
        DetailBuilder              $builder,
        User           $user
    ): Response {

        $detail = $builder
            ->setEntity(User::class)
            ->addField('nom', ['label' => 'Nom'])
            ->addField('prenom', ['label' => 'Prénom'])
            ->addField('email', ['label' => 'Email'])
            ->addField('username', ['label' => 'Login URCA'])
            ->addField('dateCreation', ['label' => 'Date d\'inscription'])
            ->addField('telFixe', ['label' => 'Téléphone fixe'])
            ->addField('telPortable', ['label' => 'Téléphone portable'])
            ->build();

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user.show.title', [], 'modal'),
            'Utilisateur : ' . $user->getDisplay(),
            '_ui/_modal_show_generic.html.twig',
            [
                'entity' => $user,
                'detail' => $detail,
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );


    }

    #[Route('/show-attente/{id}', name: 'app_user_show_attente', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showAttente(
        TurboStreamResponseFactory $turboStream,
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

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('validation.user.attente.title', [], 'modal'),
            'Utilisateur : ' . $user->getDisplay(),
            'config/user_profil/_show_attente.html.twig',
            [
                'user' => $user,
                'typeCentres' => CentreGestionEnum::cases(),
                'centresUser' => $user->getUserProfils(),
                'profils' => $profils,
                'dpe' => $dpe
            ],
            '_ui/_footer_cancel.html.twig',
            []
        );
    }

    #[Route('/{id}/edit', name: 'app_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        TurboStreamResponseFactory $turboStream,
        Request                    $request, User $user, UserRepository $userRepository): Response
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
            $userData = $request->request->all('user');
            $role = $userData['role'] ?? null;
            if (is_string($role) && $role !== '') {
                $user->setRoles([strtoupper($role)]);
            }
            $userRepository->save($user, true);

            return $turboStream->streamToastSuccess('Utilisateur modifié avec succès', true);
        }

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user.edit.title', [], 'modal'),
            'Utilisateur : ' . $user->getDisplay(),
            '_ui/_modal_new_generic.html.twig',
            [
                'user' => $user,
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    /**
     */
    #[Route('/{id}', name: 'app_user_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        TurboStreamResponseFactory $turboStream,
        Request              $request,
        User                 $user,
        UserRepository       $userRepository,
        UserProfilRepository $userProfilRepository
    ): Response {
        $token = $this->getCsrfTokenFromRequest($request);

        if ($this->isDeleteTokenValid($user, $token)) {
            foreach ($user->getUserProfils() as $centre) {
                $userProfilRepository->remove($centre, true);
            }

            $user->setIsDeleted(true);//on met le flag supprimé à true.
            $userRepository->save($user, true);

            return $turboStream->streamToastSuccess('Utilisateur supprimé avec succès', true);
        }

        return $turboStream->streamToastError('Erreur lors de la suppression', true);
    }
}
