<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/RegisterController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller;

use App\Classes\Ldap;
use App\Entity\User;
use App\Enums\CentreGestionEnum;
use App\Events\UserRegisterEvent;
use App\Form\RegisterType;
use App\Repository\ComposanteRepository;
use App\Repository\EtablissementRepository;
use App\Repository\UserRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    #[Route('/demande-acces', name: 'app_register')]
    public function index(
        EtablissementRepository  $etablissementRepository,
        ComposanteRepository     $composanteRepository,
        Ldap                     $ldap,
        EventDispatcherInterface $eventDispatcher,
        UserRepository           $userRepository,
        Request                  $request
    ): Response {
        $user = new User();
        $form = $this->createForm(RegisterType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!str_ends_with($user->getEmail(), '@univ-reims.fr')) {
                return $this->render('register/erreur_compte.html.twig', [
                    'user' => null,
                    'erreur' => 'domain_invalide',
                ]);
            }

            $existUser = $userRepository->findOneBy(['email' => $user->getEmail()]);

            if ($existUser === null) {
                $ldapUser = $ldap->getDatas($user->getEmail());
                if ($ldapUser !== null) {
                    $user->setUsername($ldapUser['username'] ?? $user->getEmail());
                    $user->setNom($ldapUser['nom'] ?? $user->getNom());
                    $user->setPrenom($ldapUser['prenom'] ?? $user->getPrenom());
                    $user->setDateDemande(new DateTime());


                    $centre = $form['centreDemande']->getData();
                    $ajout = false;
                    $userEvent = new UserRegisterEvent($user, $centre);
                    switch ($centre) {
                        case CentreGestionEnum::CENTRE_GESTION_COMPOSANTE:
                            $composante = $composanteRepository->find($request->request->get('selectListe'));
                            $user->setComposanteDemande($composante);
                            $userEvent->setComposante($composante);
                            $ajout = true;
                            break;
                        case CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT:
                            $etablissement = $etablissementRepository->find(1);//imposé car juste URCA
                            $user->setEtablissementDemande($etablissement);
                            $userEvent->setEtablissement($etablissement);
                            $ajout = true;
                            break;
                    }

                    if ($ajout) {
                        $userRepository->save($user, true);
                        $this->addFlash('success', 'Votre demande a bien été prise en compte');

                        $eventDispatcher->dispatch($userEvent, UserRegisterEvent::USER_DEMANDE_ACCES);

                        return $this->render('register/confirm.html.twig', [
                            'user' => $user,
                        ]);
                    }

                    $this->addFlash('Erreur', 'Une erreur est survenue, le centre n\'existe pas');
                    return $this->redirectToRoute('app_register');
                }

                $this->addFlash('danger', 'Cet utilisateur n\'est pas dans le LDAP');

                return $this->render('register/compte_existe.html.twig', [
                    'user' => null,
                    'email' => $user->getEmail()
                ]);
            }

            $this->addFlash('danger', 'Cet utilisateur existe déjà');

            return $this->render('register/compte_existe.html.twig', [
                'user' => $existUser,
            ]);
        }

        return $this->render('register/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
