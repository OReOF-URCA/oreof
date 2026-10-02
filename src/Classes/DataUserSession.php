<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/DataUserSession.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Classes;

use App\Entity\CampagneCollecte;
use App\Entity\Etablissement;
use App\Entity\User;
use App\Repository\CampagneCollecteRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DataUserSession
{
    private ?User $user = null;
    private string $dir;
    private ?CampagneCollecte $campagneCollecte = null;
    private ?Etablissement $etablissement = null;

    public function __construct(
        private RequestStack               $requestStack,
        private CampagneCollecteRepository $campagneCollecteRepository,
        TokenStorageInterface              $tokenStorage,
        KernelInterface                    $kernel,
    ) {
        $this->dir = $kernel->getProjectDir();
        $user = $tokenStorage->getToken()?->getUser();
        if ($user instanceof User) {
            $this->user = $user;
        }
    }

    public function getCampagneCollecte(): ?CampagneCollecte
    {
        $session = $this->requestStack->getSession();
        if ($this->campagneCollecte === null) {
            if ($session->get('campagneCollecte') !== null) {
                $this->campagneCollecte = $this->campagneCollecteRepository->find($session->get('campagneCollecte'));
            } else {
                $this->campagneCollecte = $this->campagneCollecteRepository->findOneBy(['defaut' => true]);
            }
        }

        return $this->campagneCollecte;
    }

    public function version(): ?string
    {
        $filename = $this->dir . '/package.json';
        if (!is_file($filename)) {
            return null;
        }
        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }
        $composerData = json_decode($content, true);

        return is_array($composerData) ? ($composerData['version'] ?? null) : null;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getEtablissement(): ?Etablissement
    {
        if ($this->etablissement === null) {
            $this->etablissement = $this->getUser()?->getEtablissement();
        }

        return $this->etablissement;
    }

    public function dpes(): array
    {
        return $this->campagneCollecteRepository->findAll();
    }
}
