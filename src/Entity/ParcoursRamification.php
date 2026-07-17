<?php

namespace App\Entity;

use App\Repository\ParcoursRamificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParcoursRamificationRepository::class)]
class ParcoursRamification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'parcoursRamifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeRamificationParcours $typeRamification = null;

    #[ORM\ManyToOne(inversedBy: 'parcoursRamifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Parcours $parcoursOrigine = null;

    #[ORM\ManyToOne(inversedBy: 'parcoursCibleRamifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Parcours $parcoursCible = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeRamification(): ?TypeRamificationParcours
    {
        return $this->typeRamification;
    }

    public function setTypeRamification(?TypeRamificationParcours $typeRamification): static
    {
        $this->typeRamification = $typeRamification;

        return $this;
    }

    public function getParcoursOrigine(): ?Parcours
    {
        return $this->parcoursOrigine;
    }

    public function setParcoursOrigine(?Parcours $parcoursOrigine): static
    {
        $this->parcoursOrigine = $parcoursOrigine;

        return $this;
    }

    public function getParcoursCible(): ?Parcours
    {
        return $this->parcoursCible;
    }

    public function setParcoursCible(?Parcours $parcoursCible): static
    {
        $this->parcoursCible = $parcoursCible;

        return $this;
    }
}
