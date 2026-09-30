<?php

namespace App\Entity;

use App\Repository\AnneeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnneeRepository::class)]
class Annee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'annees')]
    private ?Parcours $parcours = null;

    /**
     * @var Collection<int, SemestreParcours>
     */
    #[ORM\OneToMany(mappedBy: 'annee', targetEntity: SemestreParcours::class)]
    private Collection $parcoursSemestre;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogeeEtapeAnnee = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $codeApogeeEtapeVersion = null;

    #[ORM\Column]
    private ?bool $isOuvert = true;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $anneeOrigineCopie = null;

    /** @var Annee $anneeCopieAnneeUniversitaire Référence l'élément copié, depuis l'année d'origine */
    private ?self $anneeCopieAnneeUniversitaire = null;

    #[ORM\Column()]
    private int $capaciteAccueil = 0;

    #[ORM\Column]
    private ?bool $hasCapacite = true;

    #[ORM\Column]
    private ?bool $isProposeRecrutement = true;

    #[ORM\Column(nullable: true)]
    private ?array $regimeInscription = [];

    /**
     * @var Collection<int, PlateformeAdmissionParametre>
     */
    #[ORM\OneToMany(targetEntity: PlateformeAdmissionParametre::class, mappedBy: 'annee')]
    private Collection $admissionPlateformeParametres;

    public function __construct()
    {
        $this->parcoursSemestre = new ArrayCollection();
        $this->admissionPlateformeParametres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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

    /**
     * @return Collection<int, SemestreParcours>
     */
    public function getParcoursSemestre(): Collection
    {
        return $this->parcoursSemestre;
    }

    public function addParcoursSemestre(SemestreParcours $parcoursSemestre): static
    {
        if (!$this->parcoursSemestre->contains($parcoursSemestre)) {
            $this->parcoursSemestre->add($parcoursSemestre);
            $parcoursSemestre->setAnnee($this);
        }

        return $this;
    }

    public function removeParcoursSemestre(SemestreParcours $parcoursSemestre): static
    {
        if ($this->parcoursSemestre->removeElement($parcoursSemestre)) {
            // set the owning side to null (unless already changed)
            if ($parcoursSemestre->getOrdreAnnee() === $this) {
                $parcoursSemestre->setAnnee(null);
            }
        }

        return $this;
    }

    public function getCodeApogeeEtapeAnnee(): ?string
    {
        return $this->codeApogeeEtapeAnnee;
    }

    public function setCodeApogeeEtapeAnnee(?string $codeApogeeEtapeAnnee): static
    {
        $this->codeApogeeEtapeAnnee = $codeApogeeEtapeAnnee;

        return $this;
    }

    public function getCodeApogeeEtapeVersion(): ?string
    {
        return $this->codeApogeeEtapeVersion;
    }

    public function setCodeApogeeEtapeVersion(?string $codeApogeeEtapeVersion): static
    {
        $this->codeApogeeEtapeVersion = $codeApogeeEtapeVersion;

        return $this;
    }

    public function isOuvert(): ?bool
    {
        return $this->isOuvert;
    }

    public function setIsOuvert(bool $isOuvert): static
    {
        $this->isOuvert = $isOuvert;

        return $this;
    }

    public function getAnneeOrigineCopie(): ?self
    {
        return $this->anneeOrigineCopie;
    }

    public function setAnneeOrigineCopie(?self $anneeOrigineCopie): static
    {
        $this->anneeOrigineCopie = $anneeOrigineCopie;

        return $this;
    }

    public function getAnneeCopieAnneeUniversitaire(): ?self
    {
        return $this->anneeCopieAnneeUniversitaire;
    }

    public function setAnneeCopieAnneeUniversitaire(?self $anneeCopieAnneeUniversitaire): static
    {
        // unset the owning side of the relation if necessary
        if ($anneeCopieAnneeUniversitaire === null && $this->anneeCopieAnneeUniversitaire !== null) {
            $this->anneeCopieAnneeUniversitaire->setAnneeOrigineCopie(null);
        }

        // set the owning side of the relation if necessary
        if ($anneeCopieAnneeUniversitaire !== null && $anneeCopieAnneeUniversitaire->getAnneeOrigineCopie() !== $this) {
            $anneeCopieAnneeUniversitaire->setAnneeOrigineCopie($this);
        }

        $this->anneeCopieAnneeUniversitaire = $anneeCopieAnneeUniversitaire;

        return $this;
    }

    public function getCapaciteAccueil(): int
    {
        return $this->capaciteAccueil ?? 0;
    }

    public function setCapaciteAccueil(int $capaciteAccueil = 0): static
    {
        $this->capaciteAccueil = $capaciteAccueil;

        return $this;
    }

    public function hasCapacite(): ?bool
    {
        return $this->hasCapacite;
    }

    public function setHasCapacite(bool $hasCapacite): static
    {
        $this->hasCapacite = $hasCapacite;

        return $this;
    }

    public function isProposeRecrutement(): ?bool
    {
        return $this->isProposeRecrutement;
    }

    public function setIsProposeRecrutement(bool $isProposeRecrutement): static
    {
        $this->isProposeRecrutement = $isProposeRecrutement;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->getParcours() !== null;
    }

    /**
     * @return Collection<int, PlateformeAdmissionParametre>
     */
    public function getAdmissionPlateformeParametres(): Collection
    {
        return $this->admissionPlateformeParametres;
    }

    public function addAdmissionPlateformeParametre(PlateformeAdmissionParametre $admissionPlateformeParametre): static
    {
        if (!$this->admissionPlateformeParametres->contains($admissionPlateformeParametre)) {
            $this->admissionPlateformeParametres->add($admissionPlateformeParametre);
            $admissionPlateformeParametre->setAnnee($this);
        }

        return $this;
    }

    public function removeAdmissionPlateformeParametre(PlateformeAdmissionParametre $admissionPlateformeParametre): static
    {
        if ($this->admissionPlateformeParametres->removeElement($admissionPlateformeParametre)) {
            // set the owning side to null (unless already changed)
            if ($admissionPlateformeParametre->getAnnee() === $this) {
                $admissionPlateformeParametre->setAnnee(null);
            }
        }

        return $this;
    }

    /**
     * @return list<\App\Enums\RegimeInscriptionEnum>
     */
    public function getRegimeInscription(): array
    {
        if ($this->regimeInscription !== null && count($this->regimeInscription) === 0) {
            if ($this->getParcours() !== null && count($this->getParcours()->getRegimeInscription()) !== 0) {
                return array_values($this->getParcours()->getRegimeInscription());
            }
        }

        if ($this->regimeInscription === null) {
            return [];
        }

        $t = [];
        foreach ($this->regimeInscription as $value) {
            if ($value instanceof \App\Enums\RegimeInscriptionEnum) {
                $t[] = $value;
            } elseif (is_string($value)) {
                $case = \App\Enums\RegimeInscriptionEnum::tryFrom($value);
                if ($case !== null) {
                    $t[] = $case;
                }
            }
        }

        return $t;
    }

    /**
     * @return list<string>
     */
    public function getRegimeInscriptionValues(): array
    {
        $regimes = $this->getRegimeInscription();
        return array_map(static fn(\App\Enums\RegimeInscriptionEnum $r) => $r->value, $regimes);
    }

    public function hasRegimeInscription(string|\App\Enums\RegimeInscriptionEnum $regime): bool
    {
        $val = $regime instanceof \App\Enums\RegimeInscriptionEnum ? $regime->value : $regime;
        return in_array($val, $this->getRegimeInscriptionValues(), true);
    }

    /**
     * @param array<int, string|\App\Enums\RegimeInscriptionEnum>|null $regimeInscription
     */
    public function setRegimeInscription(?array $regimeInscription): self
    {
        if ($regimeInscription === null) {
            $this->regimeInscription = [];
            return $this;
        }

        $t = [];
        foreach ($regimeInscription as $value) {
            if ($value instanceof \App\Enums\RegimeInscriptionEnum) {
                $t[] = $value->value;
            } elseif (is_string($value)) {
                $t[] = $value;
            }
        }
        $this->regimeInscription = array_values(array_unique($t));

        return $this;
    }
}

