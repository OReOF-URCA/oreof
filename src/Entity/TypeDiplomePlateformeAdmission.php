<?php

namespace App\Entity;

use App\Repository\TypeDiplomePlateformeAdmissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TypeDiplomePlateformeAdmissionRepository::class)]
class TypeDiplomePlateformeAdmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'typeDiplomePlateformeAdmissions')]
    private ?TypeDiplome $typeDiplome = null;

    #[ORM\ManyToOne(inversedBy: 'typeDiplomePlateformeAdmissions')]
    private ?PlateformeAdmission $plateforme = null;

    #[ORM\ManyToOne(inversedBy: 'typeDiplomePlateformeAdmissions')]
    private ?CampagneCollecte $campagne = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $annees = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeDiplome(): ?TypeDiplome
    {
        return $this->typeDiplome;
    }

    public function setTypeDiplome(?TypeDiplome $typeDiplome): static
    {
        $this->typeDiplome = $typeDiplome;

        return $this;
    }

    public function getPlateforme(): ?PlateformeAdmission
    {
        return $this->plateforme;
    }

    public function setPlateforme(?PlateformeAdmission $plateforme): static
    {
        $this->plateforme = $plateforme;

        return $this;
    }

    public function getCampagne(): ?CampagneCollecte
    {
        return $this->campagne;
    }

    public function setCampagne(?CampagneCollecte $campagne): static
    {
        $this->campagne = $campagne;

        return $this;
    }

    public function getAnnees(): ?array
    {
        return $this->annees;
    }

    public function setAnnees(?array $annees): static
    {
        $this->annees = $annees;

        return $this;
    }
}
