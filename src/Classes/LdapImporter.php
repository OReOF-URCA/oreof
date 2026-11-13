<?php

namespace App\Classes;

use App\Entity\User;
use App\Repository\UserRepository;

readonly class LdapImporter
{
    public function __construct(
        private Ldap           $ldap,
        private UserRepository $userRepo
    )
    {
    }


    public function addFromLdap(string $email): ?User
    {
        // vérifie si existe déjà, si oui retourne, sinon va chercher dans LDAP, si oui ajoute, sinon erreur
        $email = trim((string)$email);
        if ($email === '') {
            return null;
        }

        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $findExist = $this->userRepo->findOneBy(['email' => $email]);
        if ($findExist) {
            return $findExist;
        }

        //cherche dans LDAP
        $datas = $this->ldap->getDatas($email);
        if (!$datas) {
            return null;
        }

        $findExistByUsername = $this->userRepo->findOneBy(['username' => $datas['username']]);
        if ($findExistByUsername !== null) {
            return $findExistByUsername;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setNom($datas['nom']);
        $user->setPrenom($datas['prenom']);
        $user->setUsername($datas['username']);
        $this->userRepo->save($user, true);

        return $user;
    }
}
