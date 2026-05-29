<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/UserProfil.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 26/05/2025 16:32
 */

namespace App\Entity;

use App\Enums\CentreGestionEnum;
use App\Repository\UserProfilRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserProfilRepository::class)]
class UserProfil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?Profil $profil = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?Composante $composante = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?CampagneCollecte $campagneCollecte = null;

    #[ORM\ManyToOne(inversedBy: 'userProfils')]
    private ?Etablissement $etablissement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCampagneCollecte(): ?CampagneCollecte
    {
        return $this->campagneCollecte;
    }

    public function setCampagneCollecte(?CampagneCollecte $campagneCollecte): static
    {
        $this->campagneCollecte = $campagneCollecte;

        return $this;
    }

    public function getDisplayCentre(): string
    { // affiche la bonne info selon le centre du profil
        if ($this->getProfil() !== null) {
            return match ($this->getProfil()->getCentre()) {
                CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT => $this->getEtablissement()?->getLibelle(),
                CentreGestionEnum::CENTRE_GESTION_COMPOSANTE => $this->getComposante()?->getLibelle(),
                CentreGestionEnum::CENTRE_GESTION_FORMATION => $this->getFormation()?->getDisplayLong(),
                CentreGestionEnum::CENTRE_GESTION_PARCOURS => $this->getParcours()?->getDisplay(),
                default => '',
            };
        }

        return 'non précisé';
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

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): static
    {
        $this->etablissement = $etablissement;

        return $this;
    }

    public function getComposante(): ?Composante
    {
        return $this->composante;
    }

    public function setComposante(?Composante $composante): static
    {
        $this->composante = $composante;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): static
    {
        $this->parcours = $parcours;

        return $this;
    }
}
