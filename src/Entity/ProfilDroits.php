<?php

namespace App\Entity;

use App\Enums\PermissionEnum;
use App\Enums\RessourceEnum;
use App\Repository\ProfilDroitsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfilDroitsRepository::class)]
class ProfilDroits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'profilDroits')]
    private ?Profil $profil = null;

    #[ORM\Column(length: 255, enumType: PermissionEnum::class)]
    private ?PermissionEnum $permission = null;

    #[ORM\Column(length: 255, enumType: RessourceEnum::class)]
    private ?RessourceEnum $ressource = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfil(): ?Profil
    {
        return $this->profil;
    }

    public function setProfil(?Profil $profil): static
    {
        $this->profil = $profil;

        return $this;
    }

    public function getPermission(): ?PermissionEnum
    {
        return $this->permission;
    }

    public function setPermission(PermissionEnum $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getRessource(): ?RessourceEnum
    {
        return $this->ressource;
    }

    public function setRessource(RessourceEnum $ressource): static
    {
        $this->ressource = $ressource;

        return $this;
    }
}
