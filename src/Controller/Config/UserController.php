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
use App\Service\DataTableBuilder;
use App\Service\DetailBuilder;
use App\Utils\JsonRequest;
use App\Utils\TurboStreamResponseFactory;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs')]
class UserController extends BaseController
{
    #[Route('/repertoire', name: 'app_user_repertoire', methods: ['GET'])]
    public function repertoire(DataTableBuilder $builder): Response
    {
        $composanteId = null;
        if ($this->isGranted('MANAGE', ['route' => 'app_composante', 'subject' => 'composante'])) {
            foreach ($this->getUser()?->getUserProfils() as $centre) {
                if ($centre->getComposante() !== null) {
                    $composanteId = $centre->getComposante()->getId();
                    break;
                }
            }
        }

        $table = $builder
            ->setEntity(User::class)
            ->setPerPage(20)
            ->setDefaultSort('nom')
            ->addBaseWhere('e.isEnable = :isEnable')
            ->addBaseWhere('e.isDeleted = :isDeleted')
            ->addBaseWhere('e.userProfils IS EMPTY')
            ->addBaseParameter('isEnable', true)
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
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('username', [
                'label' => 'Login URCA',
                'sortable' => true,
                'filterable' => true,
            ])
            ->addColumn('id', [
                'label' => 'Actions',
                'sortable' => false,
                'filterable' => false,
                'searchable' => false,
                'template' => 'config/user/_datatable_repertoire_actions.html.twig',
            ]);

        if ($composanteId !== null) {
            $table
                ->addBaseJoin('left', 'e.userProfils', 'up_filter')
                ->addBaseJoin('left', 'up_filter.formation', 'uf_f')
                ->addBaseJoin('left', 'up_filter.parcours', 'uf_pa')
                ->addBaseJoin('left', 'uf_pa.formation', 'uf_pf')
                ->addBaseWhere('(
                    IDENTITY(up_filter.composante) = :composanteId
                    OR IDENTITY(uf_f.composantePorteuse) = :composanteId
                    OR IDENTITY(uf_pf.composantePorteuse) = :composanteId
                )')
                ->addBaseParameter('composanteId', $composanteId);
        }

        return $this->render('config/user/repertoire.html.twig', [
            'table' => $table->build(),
        ]);
    }

    #[Route('/ajouter-ldap', name: 'app_user_new_ldap', methods: ['GET'])]
    public function newLdap(
        TurboStreamResponseFactory $turboStream,
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

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user_new_ldap.new.title', [], 'modal'),
            '',
            'config/user/new-ldap.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
    }

    #[Route('/ajouter-hors-urca', name: 'app_user_new_hors_urca', methods: ['GET'])]
    public function horsUrca(
        TurboStreamResponseFactory $turboStream,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserHorsUrcaType::class, $user, [
            'action' => $this->generateUrl('app_user_new_hors_urca'),
        ]);

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('user_new_hors_urca.new.title', [], 'modal'),
            '',
            'config/user/new-hors-urca.html.twig',
            [
                'form' => $form->createView(),
            ],
            '_ui/_footer_submit_cancel.html.twig',
            []
        );
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

    #[Route('/show-attente/{id}', name: 'app_user_show_attente', methods: ['GET'])]
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

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
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
            $user->setRoles([strtoupper($request->request->all()['user']['role'])]);
            $userRepository->save($user, true);

            return $this->json(true);
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
        $token = $request->request->get('_token') ?? $request->request->get('csrf_token');
        if ($token === null) {
            try {
                $token = JsonRequest::getValueFromRequest($request, 'csrf');
            } catch (\JsonException) {
                $token = null;
            }
        }

        if ($this->isCsrfTokenValid(
            'delete' . $user->getId(),
            $token
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
