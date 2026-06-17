<?php

namespace App\Entity;

use App\Repository\PlateformeAdmissionParametreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlateformeAdmissionParametreRepository::class)]
class PlateformeAdmissionParametre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'admissionPlateformeParametres')]
    private ?Annee $annee = null;

    #[ORM\ManyToOne(inversedBy: 'admissionPlateformeParametres')]
    private ?PlateformeAdmission $plateforme = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteGlobale = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteFi = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteAlternance = null;

    #[ORM\Column(nullable: true)]
    private ?int $capaciteSpecifique = null;

    #[ORM\Column(nullable: true)]
    private ?array $donneesSpecifiques = null;

    #[ORM\ManyToOne(inversedBy: 'admissionPlateformeParametres')]
    private ?CampagneCollecte $campagne = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAnnee(): ?Annee
    {
        return $this->annee;
    }

    public function setAnnee(?Annee $annee): static
    {
        $this->annee = $annee;

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

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getCapaciteGlobale(): ?int
    {
        return $this->capaciteGlobale;
    }

    public function setCapaciteGlobale(?int $capaciteGlobale): static
    {
        $this->capaciteGlobale = $capaciteGlobale;

        return $this;
    }

    public function getCapaciteFi(): ?int
    {
        return $this->capaciteFi;
    }

    public function setCapaciteFi(?int $capaciteFi): static
    {
        $this->capaciteFi = $capaciteFi;

        return $this;
    }

    public function getCapaciteAlternance(): ?int
    {
        return $this->capaciteAlternance;
    }

    public function setCapaciteAlternance(?int $capaciteAlternance): static
    {
        $this->capaciteAlternance = $capaciteAlternance;

        return $this;
    }

    public function getCapaciteSpecifique(): ?int
    {
        return $this->capaciteSpecifique;
    }

    public function setCapaciteSpecifique(?int $capaciteSpecifique): static
    {
        $this->capaciteSpecifique = $capaciteSpecifique;

        return $this;
    }

    public function getDonneesSpecifiques(): ?array
    {
        return $this->donneesSpecifiques;
    }

    public function setDonneesSpecifiques(?array $donneesSpecifiques): static
    {
        $this->donneesSpecifiques = $donneesSpecifiques;

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
}
