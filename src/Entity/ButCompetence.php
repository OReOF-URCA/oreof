<?php

namespace App\Entity;

use App\Repository\ButCompetenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ButCompetenceRepository::class)]
class ButCompetence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nomCourt = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column]
    private ?int $numero = null;

    #[ORM\ManyToOne(inversedBy: 'butCompetences')]
    private ?Formation $formation = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private array $situations = [];

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(nullable: true)]
    private array $composantes = [];

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'competence', targetEntity: ButNiveau::class)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $butNiveaux;

    #[ORM\ManyToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $butCompetenceOrigineCopie = null;

    #[ORM\ManyToOne(inversedBy: 'butCompetences')]
    private ?CampagneCollecte $campagneCollecte = null;

    public function __construct()
    {
        $this->butNiveaux = new ArrayCollection();
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

    public function getNomCourt(): ?string
    {
        return $this->nomCourt;
    }

    public function setNomCourt(?string $nomCourt): self
    {
        $this->nomCourt = $nomCourt;

        return $this;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getSituations(): array
    {
        return $this->situations;
    }

    public function setSituations(?array $situations): self
    {
        $this->situations = $situations;

        return $this;
    }

    public function getComposantes(): array
    {
        return $this->composantes;
    }

    public function setComposantes(?array $composantes): self
    {
        $this->composantes = $composantes;

        return $this;
    }

    /**
     * @return Collection<int, ButNiveau>
     */
    public function getButNiveaux(): Collection
    {
        return $this->butNiveaux;
    }

    public function addButNiveau(ButNiveau $butNiveau): self
    {
        if (!$this->butNiveaux->contains($butNiveau)) {
            $this->butNiveaux->add($butNiveau);
            $butNiveau->setCompetence($this);
        }

        return $this;
    }

    public function removeButNiveau(ButNiveau $butNiveau): self
    {
        if ($this->butNiveaux->removeElement($butNiveau)) {
            // set the owning side to null (unless already changed)
            if ($butNiveau->getCompetence() === $this) {
                $butNiveau->setCompetence(null);
            }
        }

        return $this;
    }

    public function getDisplay(): string
    {
        return '['.$this->getNomCourt().'] ' . $this->getLibelle();
    }

    public function getButCompetenceOrigineCopie(): ?self
    {
        return $this->butCompetenceOrigineCopie;
    }

    public function setButCompetenceOrigineCopie(?self $butCompetenceOrigineCopie): static
    {
        $this->butCompetenceOrigineCopie = $butCompetenceOrigineCopie;

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
}
