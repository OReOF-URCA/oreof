<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/User.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/02/2023 23:23
 */

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    private ?Etablissement $etablissement = null;

    #[ORM\OneToMany(targetEntity: Composante::class, mappedBy: 'directeur')]
    private Collection $composantes;

    #[ORM\OneToMany(targetEntity: Composante::class, mappedBy: 'responsableDpe')]
    private Collection $composanteResponsableDpe;

    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'responsableMention')]
    private Collection $formationsResponsableMention;

    #[ORM\Column(length: 50)]
    #[Groups(['fiche_matiere:read', 'parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Groups(['fiche_matiere:read', 'parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['fiche_matiere:read', 'parcours_json_versioning', 'fiche_matiere_versioning', 'formation_json_versioning'])]
    private ?string $email = null;

    #[ORM\Column]
    private ?bool $isEnable = false;

    #[ORM\Column]
    private ?bool $isValidDpe = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateValideDpe = null;

    #[ORM\Column]
    private ?bool $isValideAdministration = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateValideAdministration = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateDemande = null;

    #[ORM\Column]
    private ?bool $isDeleted = false;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $civilite = null;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $telFixe = null;

    #[Groups('formation_json_versioning')]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $telPortable = null;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'destinataire')]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: Parcours::class, mappedBy: 'coResponsable')]
    private Collection $coParcours;

    #[ORM\OneToMany(targetEntity: Formation::class, mappedBy: 'coResponsable')]
    private Collection $coFormations;

    #[ORM\ManyToOne]
    private ?Composante $composanteDemande = null;

    #[ORM\ManyToOne]
    private ?Etablissement $etablissementDemande = null;

    #[ORM\OneToMany(targetEntity: Historique::class, mappedBy: 'user')]
    private Collection $historiques;

    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'user')]
    private Collection $commentaires;

    /**
     * @var Collection<int, DpeDemande>
     */
    #[ORM\OneToMany(targetEntity: DpeDemande::class, mappedBy: 'auteur')]
    private Collection $dpeDemandes;

    /**
     * @var Collection<int, UserProfil>
     */
    #[ORM\OneToMany(targetEntity: UserProfil::class, mappedBy: 'user')]
    private Collection $userProfils;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serviceDemande = null;

    #[ORM\OneToOne(targetEntity: UserNotificationPreference::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserNotificationPreference $notificationPreference = null;

    /**
     * @var Collection<int, UserWorkflowNotificationSetting>
     */
    #[ORM\OneToMany(targetEntity: UserWorkflowNotificationSetting::class, mappedBy: 'user')]
    private Collection $userWorkflowNotificationSettings;

    /**
     * @var Collection<int, UserCategoryNotificationSetting>
     */
    #[ORM\OneToMany(targetEntity: UserCategoryNotificationSetting::class, mappedBy: 'user')]
    private Collection $userCategoryNotificationSettings;

    /**
     * @var Collection<int, ChangeParcours>
     */
    #[ORM\OneToMany(targetEntity: ChangeParcours::class, mappedBy: 'auteur')]
    private Collection $changeParcours;

    public function __construct()
    {
        $this->composantes = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->composanteResponsableDpe = new ArrayCollection();
        $this->formationsResponsableMention = new ArrayCollection();
        $this->coParcours = new ArrayCollection();
        $this->coFormations = new ArrayCollection();
        $this->historiques = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->dpeDemandes = new ArrayCollection();
        $this->userProfils = new ArrayCollection();
        $this->userWorkflowNotificationSettings = new ArrayCollection();
        $this->userCategoryNotificationSettings = new ArrayCollection();
        $this->changeParcours = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getDisplay();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_LECTEUR';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): self
    {
        $this->etablissement = $etablissement;

        return $this;
    }

    /**
     * @return Collection<int, Composante>
     */
    public function getComposantes(): Collection
    {
        return $this->composantes;
    }

    public function addComposante(Composante $composante): self
    {
        if (!$this->composantes->contains($composante)) {
            $this->composantes->add($composante);
            $composante->setDirecteur($this);
        }

        return $this;
    }

    public function removeComposante(Composante $composante): self
    {
        // set the owning side to null (unless already changed)
        if ($this->composantes->removeElement($composante) && $composante->getDirecteur() === $this) {
            $composante->setDirecteur(null);
        }

        return $this;
    }

    public function getPrenom(): ?string
    {
        return ucwords(mb_strtolower($this->prenom));
    }

    public function setPrenom(?string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function getNom(): ?string
    {
        return mb_strtoupper($this->nom);
    }

    public function setNom(?string $nom): void
    {
        $this->nom = $nom;
    }

    #[Groups(['fiche_matiere:read'])]
    public function getDisplay(): string
    {
        return mb_strtoupper($this->getNom()).' '. ucwords(mb_strtolower($this->getPrenom()));
    }

    public function getAvatarInitiales(): ?string
    {
        return mb_strtoupper(mb_substr(trim($this->getPrenom()), 0, 1).mb_substr(trim($this->getNom()), 0, 1));
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }


    public function isIsEnable(): ?bool
    {
        return $this->isEnable;
    }

    public function isEnable(): ?bool
    {
        return $this->isEnable;
    }

    public function setIsEnable(bool $isEnable): self
    {
        $this->isEnable = $isEnable;

        return $this;
    }

    public function isIsValidDpe(): ?bool
    {
        return $this->isValidDpe;
    }

    public function setIsValidDpe(bool $isValidDpe): self
    {
        $this->isValidDpe = $isValidDpe;

        return $this;
    }

    public function getDateValideDpe(): ?DateTimeInterface
    {
        return $this->dateValideDpe;
    }

    public function setDateValideDpe(DateTimeInterface $dateValideDpe): self
    {
        $this->dateValideDpe = $dateValideDpe;

        return $this;
    }

    public function isIsValideAdministration(): ?bool
    {
        return $this->isValideAdministration;
    }

    public function setIsValideAdministration(bool $isValideAdministration): self
    {
        $this->isValideAdministration = $isValideAdministration;

        return $this;
    }

    public function getDateValideAdministration(): ?DateTimeInterface
    {
        return $this->dateValideAdministration;
    }

    public function setDateValideAdministration(?DateTimeInterface $dateValideAdministration): self
    {
        $this->dateValideAdministration = $dateValideAdministration;

        return $this;
    }

    public function getDateDemande(): ?DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(?DateTimeInterface $dateDemande): self
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function isIsDeleted(): ?bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getCivilite(): ?string
    {
        return $this->civilite;
    }

    public function setCivilite(?string $civilite): self
    {
        $this->civilite = $civilite;

        return $this;
    }

    public function getTelFixe(): ?string
    {
        return $this->telFixe;
    }

    public function setTelFixe(?string $telFixe): self
    {
        $this->telFixe = $telFixe;

        return $this;
    }

    public function getTelPortable(): ?string
    {
        return $this->telPortable;
    }

    public function setTelPortable(?string $telPortable): self
    {
        $this->telPortable = $telPortable;

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setDestinataire($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        // set the owning side to null (unless already changed)
        if ($this->notifications->removeElement($notification) && $notification->getDestinataire() === $this) {
            $notification->setDestinataire(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Composante>
     */
    public function getComposanteResponsableDpe(): Collection
    {
        return $this->composanteResponsableDpe;
    }

    public function addComposanteResponsableDpe(Composante $composanteResponsableDpe): self
    {
        if (!$this->composanteResponsableDpe->contains($composanteResponsableDpe)) {
            $this->composanteResponsableDpe->add($composanteResponsableDpe);
            $composanteResponsableDpe->setResponsableDpe($this);
        }

        return $this;
    }

    public function removeComposanteResponsableDpe(Composante $composanteResponsableDpe): self
    {
        // set the owning side to null (unless already changed)
        if ($this->composanteResponsableDpe->removeElement($composanteResponsableDpe) && $composanteResponsableDpe->getResponsableDpe() === $this) {
            $composanteResponsableDpe->setResponsableDpe(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getFormationsResponsableMention(): Collection
    {
        return $this->formationsResponsableMention;
    }

    public function addFormationsResponsableMention(Formation $formationsResponsableMention): self
    {
        if (!$this->formationsResponsableMention->contains($formationsResponsableMention)) {
            $this->formationsResponsableMention->add($formationsResponsableMention);
            $formationsResponsableMention->setResponsableMention($this);
        }

        return $this;
    }

    public function removeFormationsResponsableMention(Formation $formationsResponsableMention): self
    {
        // set the owning side to null (unless already changed)
        if ($this->formationsResponsableMention->removeElement($formationsResponsableMention) && $formationsResponsableMention->getResponsableMention() === $this) {
            $formationsResponsableMention->setResponsableMention(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Parcours>
     */
    public function getCoParcours(): Collection
    {
        return $this->coParcours;
    }

    public function addCoParcour(Parcours $coParcour): self
    {
        if (!$this->coParcours->contains($coParcour)) {
            $this->coParcours->add($coParcour);
            $coParcour->setCoResponsable($this);
        }

        return $this;
    }

    public function removeCoParcour(Parcours $coParcour): self
    {
        if ($this->coParcours->removeElement($coParcour)) {
            // set the owning side to null (unless already changed)
            if ($coParcour->getCoResponsable() === $this) {
                $coParcour->setCoResponsable(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getCoFormations(): Collection
    {
        return $this->coFormations;
    }

    public function addCoFormation(Formation $coFormation): self
    {
        if (!$this->coFormations->contains($coFormation)) {
            $this->coFormations->add($coFormation);
            $coFormation->setCoResponsable($this);
        }

        return $this;
    }

    public function removeCoFormation(Formation $coFormation): self
    {
        if ($this->coFormations->removeElement($coFormation)) {
            // set the owning side to null (unless already changed)
            if ($coFormation->getCoResponsable() === $this) {
                $coFormation->setCoResponsable(null);
            }
        }

        return $this;
    }

    public function getComposanteDemande(): ?Composante
    {
        return $this->composanteDemande;
    }

    public function setComposanteDemande(?Composante $composanteDemande): self
    {
        $this->composanteDemande = $composanteDemande;

        return $this;
    }

    public function getEtablissementDemande(): ?Etablissement
    {
        return $this->etablissementDemande;
    }

    public function setEtablissementDemande(?Etablissement $etablissementDemande): self
    {
        $this->etablissementDemande = $etablissementDemande;

        return $this;
    }

    /**
     * @return Collection<int, Historique>
     */
    public function getHistoriques(): Collection
    {
        return $this->historiques;
    }

    public function addHistorique(Historique $historique): static
    {
        if (!$this->historiques->contains($historique)) {
            $this->historiques->add($historique);
            $historique->setUser($this);
        }

        return $this;
    }

    public function removeHistorique(Historique $historique): static
    {
        if ($this->historiques->removeElement($historique)) {
            // set the owning side to null (unless already changed)
            if ($historique->getUser() === $this) {
                $historique->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Commentaire>
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(Commentaire $commentaire): static
    {
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires->add($commentaire);
            $commentaire->setUser($this);
        }

        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
        if ($this->commentaires->removeElement($commentaire)) {
            // set the owning side to null (unless already changed)
            if ($commentaire->getUser() === $this) {
                $commentaire->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DpeDemande>
     */
    public function getDpeDemandes(): Collection
    {
        return $this->dpeDemandes;
    }

    public function addDpeDemande(DpeDemande $dpeDemande): static
    {
        if (!$this->dpeDemandes->contains($dpeDemande)) {
            $this->dpeDemandes->add($dpeDemande);
            $dpeDemande->setAuteur($this);
        }

        return $this;
    }

    public function removeDpeDemande(DpeDemande $dpeDemande): static
    {
        if ($this->dpeDemandes->removeElement($dpeDemande)) {
            // set the owning side to null (unless already changed)
            if ($dpeDemande->getAuteur() === $this) {
                $dpeDemande->setAuteur(null);
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
            $userProfil->setUser($this);
        }

        return $this;
    }

    public function removeUserProfil(UserProfil $userProfil): static
    {
        if ($this->userProfils->removeElement($userProfil)) {
            // set the owning side to null (unless already changed)
            if ($userProfil->getUser() === $this) {
                $userProfil->setUser(null);
            }
        }

        return $this;
    }

    public function getServiceDemande(): ?string
    {
        return $this->serviceDemande;
    }

    public function setServiceDemande(?string $serviceDemande): static
    {
        $this->serviceDemande = $serviceDemande;

        return $this;
    }

    /**
     * @return Collection<int, UserWorkflowNotificationSetting>
     */
    public function getUserWorkflowNotificationSettings(): Collection
    {
        return $this->userWorkflowNotificationSettings;
    }

    public function addUserWorkflowNotificationSetting(UserWorkflowNotificationSetting $userWorkflowNotificationSetting): static
    {
        if (!$this->userWorkflowNotificationSettings->contains($userWorkflowNotificationSetting)) {
            $this->userWorkflowNotificationSettings->add($userWorkflowNotificationSetting);
            $userWorkflowNotificationSetting->setUser($this);
        }

        return $this;
    }

    public function removeUserWorkflowNotificationSetting(UserWorkflowNotificationSetting $userWorkflowNotificationSetting): static
    {
        if ($this->userWorkflowNotificationSettings->removeElement($userWorkflowNotificationSetting)) {
            // set the owning side to null (unless already changed)
            if ($userWorkflowNotificationSetting->getUser() === $this) {
                $userWorkflowNotificationSetting->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserCategoryNotificationSetting>
     */
    public function getUserCategoryNotificationSettings(): Collection
    {
        return $this->userCategoryNotificationSettings;
    }

    public function addUserCategoryNotificationSetting(UserCategoryNotificationSetting $userCategoryNotificationSetting): static
    {
        if (!$this->userCategoryNotificationSettings->contains($userCategoryNotificationSetting)) {
            $this->userCategoryNotificationSettings->add($userCategoryNotificationSetting);
            $userCategoryNotificationSetting->setUser($this);
        }

        return $this;
    }

    public function removeUserCategoryNotificationSetting(UserCategoryNotificationSetting $userCategoryNotificationSetting): static
    {
        if ($this->userCategoryNotificationSettings->removeElement($userCategoryNotificationSetting)) {
            // set the owning side to null (unless already changed)
            if ($userCategoryNotificationSetting->getUser() === $this) {
                $userCategoryNotificationSetting->setUser(null);
            }
        }

        return $this;
    }

    public function getNotificationPreference(): ?UserNotificationPreference
    {
        return $this->notificationPreference;
    }

    public function setNotificationPreference(UserNotificationPreference $pref): self
    {
        $this->notificationPreference = $pref;
        if ($pref->getUser() !== $this) {
            $pref->setUser($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, ChangeParcours>
     */
    public function getChangeParcours(): Collection
    {
        return $this->changeParcours;
    }

    public function addChangeParcour(ChangeParcours $changeParcour): static
    {
        if (!$this->changeParcours->contains($changeParcour)) {
            $this->changeParcours->add($changeParcour);
            $changeParcour->setAuteur($this);
        }

        return $this;
    }

    public function removeChangeParcour(ChangeParcours $changeParcour): static
    {
        if ($this->changeParcours->removeElement($changeParcour)) {
            // set the owning side to null (unless already changed)
            if ($changeParcour->getAuteur() === $this) {
                $changeParcour->setAuteur(null);
            }
        }

        return $this;
    }
}
