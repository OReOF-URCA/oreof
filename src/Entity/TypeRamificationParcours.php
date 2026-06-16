<?php

namespace App\Entity;

use App\Repository\TypeRamificationParcoursRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeRamificationParcoursRepository::class)]
class TypeRamificationParcours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, ParcoursRamification>
     */
    #[ORM\OneToMany(mappedBy: 'typeRamification', targetEntity: ParcoursRamification::class)]
    private Collection $parcoursRamifications;

    public function __construct()
    {
        $this->parcoursRamifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
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

    /**
     * @return Collection<int, ParcoursRamification>
     */
    public function getParcoursRamifications(): Collection
    {
        return $this->parcoursRamifications;
    }

    public function addParcoursRamification(ParcoursRamification $parcoursRamification): static
    {
        if (!$this->parcoursRamifications->contains($parcoursRamification)) {
            $this->parcoursRamifications->add($parcoursRamification);
            $parcoursRamification->setTypeRamification($this);
        }

        return $this;
    }

    public function removeParcoursRamification(ParcoursRamification $parcoursRamification): static
    {
        if ($this->parcoursRamifications->removeElement($parcoursRamification)) {
            // set the owning side to null (unless already changed)
            if ($parcoursRamification->getTypeRamification() === $this) {
                $parcoursRamification->setTypeRamification(null);
            }
        }

        return $this;
    }
}
