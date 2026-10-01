<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/AddUser.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Classes;

use App\Entity\Composante;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Repository\ProfilRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class AddUser
{
    public function __construct(
        private ProfilRepository $profilRepository,
        private UserRepository       $userRepository,
        private Ldap                   $ldap,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function addUser(string $email): ?UserInterface
    {
        if ($email !== '') {
            $exist = $this->userRepository->findOneBy(['email' => $email]);
            if ($exist) {
                return $exist;
            }

            $user = $this->ldap->getDatas($email);
            if ($user) {
                $usr = new User();
                $usr->setUsername($user['username']);
                $usr->setEmail($email);
                $usr->setNom($user['nom']);
                $usr->setPrenom($user['prenom']);
                $this->userRepository->save($usr, true);

                return $usr;
            }
        }

        return null;
    }

    public function addRole(User $user, string $role): void
    {
        $user->setRoles([$role]);
    }

    public function setCentreComposante(UserInterface|User $usr, Composante $composante, ?string $role = null): void
    {
        $profil = $this->profilRepository->findOneBy(['code' => $role]);

        if ($profil === null) {
            throw new \InvalidArgumentException(sprintf('Role "%s" does not exist.', $role));
        }

        $centre = new UserProfil();
        $centre->setUser($usr);
        $centre->setComposante($composante);
        $centre->setProfil($profil);
        $this->entityManager->persist($centre);
        $this->entityManager->flush();
    }
}
