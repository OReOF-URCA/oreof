<?php

namespace App\Entity;

use App\Repository\DpeFormationRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DpeFormationRepository::class)]
class DpeFormation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?CampagneCollecte $campagneCollecte = null;

    #[ORM\ManyToOne(inversedBy: 'dpeFormations')]
    private ?Formation $formation = null;

    #[ORM\Column]
    private array $etatValidation = []; // targets Symfony workflow marking

    #[ORM\Column(length: 10)]
    private ?string $version = '0.1';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $created = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $laissezPasser = null; // store laissez-passer justification details if active

    #[ORM\OneToMany(mappedBy: 'dpeFormation', targetEntity: HistoriqueFormation::class)]
    private Collection $historiqueFormations;

    public function __construct()
    {
        $this->created = new DateTime();
        $this->historiqueFormations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    public function getEtatValidation(): array
    {
        return $this->etatValidation ?? [];
    }

    public function setEtatValidation(array $etatValidation): static
    {
        $this->etatValidation = $etatValidation;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version = '0.1'): static
    {
        $this->version = $version;
        return $this;
    }

    public function getCreated(): ?DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(DateTimeInterface $created): static
    {
        $this->created = $created;
        return $this;
    }

    public function getLaissezPasser(): ?string
    {
        return $this->laissezPasser;
    }

    public function setLaissezPasser(?string $laissezPasser): static
    {
        $this->laissezPasser = $laissezPasser;
        return $this;
    }

    /**
     * @return Collection<int, HistoriqueFormation>
     */
    public function getHistoriqueFormations(): Collection
    {
        return $this->historiqueFormations;
    }

    public function addHistoriqueFormation(HistoriqueFormation $historiqueFormation): static
    {
        if (!$this->historiqueFormations->contains($historiqueFormation)) {
            $this->historiqueFormations->add($historiqueFormation);
            $historiqueFormation->setDpeFormation($this);
        }
        return $this;
    }

    public function removeHistoriqueFormation(HistoriqueFormation $historiqueFormation): static
    {
        if ($this->historiqueFormations->removeElement($historiqueFormation)) {
            // set the owning side to null (unless already changed)
            if ($historiqueFormation->getDpeFormation() === $this) {
                $historiqueFormation->setDpeFormation(null);
            }
        }
        return $this;
    }
}
