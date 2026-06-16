<?php

namespace App\Entity;

use App\Enums\CentreGestionEnum;
use App\Repository\ProfilRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProfilRepository::class)]
#[ORM\Table(name: 'profil', uniqueConstraints: [new ORM\UniqueConstraint(name: 'unique_code', columns: ['code'])])]
class Profil
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true, enumType: CentreGestionEnum::class)]
    private ?CentreGestionEnum $centre = null;

    /**
     * @var Collection<int, ProfilDroits>
     */
    #[ORM\OneToMany(mappedBy: 'profil', targetEntity: ProfilDroits::class)]
    private Collection $profilDroits;

    /**
     * @var Collection<int, UserProfil>
     */
    #[ORM\OneToMany(mappedBy: 'profil', targetEntity: UserProfil::class)]
    private Collection $userProfils;

    #[ORM\Column]
    private ?bool $onlyAdmin = false;

    #[ORM\Column]
    private ?bool $isExclusif = false;

    #[ORM\Column]
    private ?bool $isMailing = false;

    /**
     * @var Collection<int, NotificationListe>
     */
    #[ORM\OneToMany(mappedBy: 'profil', targetEntity: NotificationListe::class)]
    private Collection $notificationListes;

    public function __construct()
    {
        $this->profilDroits = new ArrayCollection();
        $this->userProfils = new ArrayCollection();
        $this->notificationListes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCentre(): ?CentreGestionEnum
    {
        return $this->centre;
    }

    public function setCentre(?CentreGestionEnum $centre): static
    {
        $this->centre = $centre;

        return $this;
    }

    /**
     * @return Collection<int, ProfilDroits>
     */
    public function getProfilDroits(): Collection
    {
        return $this->profilDroits;
    }

    public function addProfilDroit(ProfilDroits $profilDroit): static
    {
        if (!$this->profilDroits->contains($profilDroit)) {
            $this->profilDroits->add($profilDroit);
            $profilDroit->setProfil($this);
        }

        return $this;
    }

    public function removeProfilDroit(ProfilDroits $profilDroit): static
    {
        if ($this->profilDroits->removeElement($profilDroit)) {
            // set the owning side to null (unless already changed)
            if ($profilDroit->getProfil() === $this) {
                $profilDroit->setProfil(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserProfil>
     */
    public function getUserProfils(): Collection
    {
        return $this->userProfils;
    }

    public function addUserProfil(UserProfil $userProfil): static
    {
        if (!$this->userProfils->contains($userProfil)) {
            $this->userProfils->add($userProfil);
            $userProfil->setProfil($this);
        }

        return $this;
    }

    public function removeUserProfil(UserProfil $userProfil): static
    {
        if ($this->userProfils->removeElement($userProfil)) {
            // set the owning side to null (unless already changed)
            if ($userProfil->getProfil() === $this) {
                $userProfil->setProfil(null);
            }
        }

        return $this;
    }

    public function isOnlyAdmin(): ?bool
    {
        return $this->onlyAdmin ?? false;
    }

    public function setOnlyAdmin(bool $onlyAdmin): static
    {
        $this->onlyAdmin = $onlyAdmin;

        return $this;
    }

    public function isExclusif(): ?bool
    {
        return $this->isExclusif ?? false;
    }

    public function setIsExclusif(bool $isExclusif): static
    {
        $this->isExclusif = $isExclusif;

        return $this;
    }

    public function isMailing(): ?bool
    {
        return $this->isMailing ?? false;
    }

    public function setIsMailing(bool $isMailing): static
    {
        $this->isMailing = $isMailing;

        return $this;
    }

    /**
     * @return Collection<int, NotificationListe>
     */
    public function getNotificationListes(): Collection
    {
        return $this->notificationListes;
    }

    public function addNotificationListe(NotificationListe $notificationListe): static
    {
        if (!$this->notificationListes->contains($notificationListe)) {
            $this->notificationListes->add($notificationListe);
            $notificationListe->setProfil($this);
        }

        return $this;
    }

    public function removeNotificationListe(NotificationListe $notificationListe): static
    {
        if ($this->notificationListes->removeElement($notificationListe)) {
            // set the owning side to null (unless already changed)
            if ($notificationListe->getProfil() === $this) {
                $notificationListe->setProfil(null);
            }
        }

        return $this;
    }
}
