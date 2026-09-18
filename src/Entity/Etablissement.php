<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Etablissement.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/02/2023 23:23
 */

namespace App\Entity;

use App\Repository\EtablissementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: EtablissementRepository::class)]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: User::class)]
    private Collection $users;

    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: Ville::class)]
    private Collection $villes;

    #[Groups('formation_json_versioning')]
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Adresse $adresse = null;

    #[ORM\Column]
    private ?array $options = [];

    #[ORM\OneToOne(mappedBy: 'etablissement', cascade: ['persist', 'remove'])]
    private ?EtablissementInformation $etablissement_information = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $numero_SIRET = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numero_activite = null;

    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: Composante::class)]
    private Collection $composantes;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailCentral = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailOreof = null;

    /**
     * @var Collection<int, UserProfil>
     */
    #[ORM\OneToMany(mappedBy: 'etablissement', targetEntity: UserProfil::class)]
    private Collection $userProfils;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->villes = new ArrayCollection();

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $this->setOptions($resolver->resolve($this->options));
        $this->composantes = new ArrayCollection();
        $this->userProfils = new ArrayCollection();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'domaine'       => '@univ-reims.fr',
        ]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setEtablissement($this);
        }

        return $this;
    }

    public function removeUser(User $user): self
    {
        // set the owning side to null (unless already changed)
        if ($this->users->removeElement($user) && $user->getEtablissement() === $this) {
            $user->setEtablissement(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Ville>
     */
    public function getVilles(): Collection
    {
        return $this->villes;
    }

    public function addVille(Ville $ville): self
    {
        if (!$this->villes->contains($ville)) {
            $this->villes->add($ville);
            $ville->setEtablissement($this);
        }

        return $this;
    }

    public function removeVille(Ville $ville): self
    {
        // set the owning side to null (unless already changed)
        if ($this->villes->removeElement($ville) && $ville->getEtablissement() === $this) {
            $ville->setEtablissement(null);
        }

        return $this;
    }

    public function getAdresse(): ?Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(?Adresse $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getOptions(): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        return $resolver->resolve($this->options ?? []);
    }

    public function setOptions(array $options = []): static
    {
        $this->options = $options;

        return $this;
    }

    public function getEtablissementInformation(): ?EtablissementInformation
    {
        return $this->etablissement_information;
    }

    public function setEtablissementInformation(?EtablissementInformation $etablissement_information): static
    {
        // unset the owning side of the relation if necessary
        if ($etablissement_information === null && $this->etablissement_information !== null) {
            $this->etablissement_information->setEtablissement(null);
        }

        // set the owning side of the relation if necessary
        if ($etablissement_information !== null && $etablissement_information->getEtablissement() !== $this) {
            $etablissement_information->setEtablissement($this);
        }

        $this->etablissement_information = $etablissement_information;

        return $this;
    }

    public function getNumeroSIRET(): ?string
    {
        return $this->numero_SIRET;
    }

    public function setNumeroSIRET(?string $numero_SIRET): static
    {
        $this->numero_SIRET = $numero_SIRET;

        return $this;
    }

    public function getNumeroActivite(): ?string
    {
        return $this->numero_activite;
    }

    public function setNumeroActivite(?string $numero_activite): static
    {
        $this->numero_activite = $numero_activite;

        return $this;
    }

    /**
     * @return Collection<int, Composante>
     */
    public function getComposantes(): Collection
    {
        return $this->composantes;
    }

    public function addComposante(Composante $composante): static
    {
        if (!$this->composantes->contains($composante)) {
            $this->composantes->add($composante);
            $composante->setEtablissement($this);
        }

        return $this;
    }

    public function removeComposante(Composante $composante): static
    {
        if ($this->composantes->removeElement($composante)) {
            // set the owning side to null (unless already changed)
            if ($composante->getEtablissement() === $this) {
                $composante->setEtablissement(null);
            }
        }

        return $this;
    }

    public function getEmailCentral(): ?string
    {
        return $this->emailCentral ?? 'cfvu-secretariat@univ-reims.fr';
    }

    public function setEmailCentral(?string $emailCentral): static
    {
        $this->emailCentral = $emailCentral;

        return $this;
    }

    public function getEmailOreof(): ?string
    {
        return $this->emailOreof ?? 'oreof@univ-reims.fr';
    }

    public function setEmailOreof(?string $emailOreof): static
    {
        $this->emailOreof = $emailOreof;

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
            $userProfil->setEtablissement($this);
        }

        return $this;
    }

    public function removeUserProfil(UserProfil $userProfil): static
    {
        if ($this->userProfils->removeElement($userProfil)) {
            // set the owning side to null (unless already changed)
            if ($userProfil->getEtablissement() === $this) {
                $userProfil->setEtablissement(null);
            }
        }

        return $this;
    }
}
