<?php

namespace App\Entity;

use App\Enums\TypeRfEnum;
use App\Repository\ChangeRfRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChangeRfRepository::class)]
class ChangeRf
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'changeRves')]
    private ?Formation $formation = null;

    #[ORM\Column(length: 20, enumType: TypeRfEnum::class)]
    private ?TypeRfEnum $typeRf;

    #[ORM\Column(nullable: true)]
    private ?array $etatDemande = [];

    #[ORM\ManyToOne]
    private ?User $nouveauResponsable = null;

    #[ORM\ManyToOne]
    private ?User $ancienResponsable = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $dateDemande = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $dateValidationCfvu = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $fichier_pv = null;

    #[ORM\Column(nullable: true)]
    private ?bool $laisserPasser = null;

    /**
     * @var Collection<int, HistoriqueFormation>
     */
    #[ORM\OneToMany(mappedBy: 'changeRf', targetEntity: HistoriqueFormation::class, cascade: ['persist', 'remove'])]
    private Collection $historiqueFormations;

    #[ORM\ManyToOne(inversedBy: 'changeRves')]
    private ?CampagneCollecte $campagneCollecte = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePriseFonction = null;

    public function __construct()
    {
        $this->typeRf = TypeRfEnum::RF;
        $this->historiqueFormations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEtatDemande(): ?array
    {
        return $this->etatDemande ?? [];
    }

    public function setEtatDemande(array $etatDemande): static
    {
        $this->etatDemande = $etatDemande;

        return $this;
    }

    public function getTypeRf(): ?TypeRfEnum
    {
        return $this->typeRf;
    }

    public function setTypeRf(TypeRfEnum $typeRf): static
    {
        $this->typeRf = $typeRf;

        return $this;
    }

    public function getNouveauResponsable(): ?User
    {
        return $this->nouveauResponsable;
    }

    public function setNouveauResponsable(?User $nouveauResponsable): static
    {
        $this->nouveauResponsable = $nouveauResponsable;

        return $this;
    }

    public function getAncienResponsable(): ?User
    {
        return $this->ancienResponsable;
    }

    public function setAncienResponsable(?User $ancienResponsable): static
    {
        $this->ancienResponsable = $ancienResponsable;

        return $this;
    }

    public function getDateDemande(): ?DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(DateTimeInterface $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getDateValidationCfvu(): ?DateTimeInterface
    {
        return $this->dateValidationCfvu;
    }

    public function setDateValidationCfvu(?DateTimeInterface $dateValidationCfvu): static
    {
        $this->dateValidationCfvu = $dateValidationCfvu;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getFichierPv(): ?string
    {
        return $this->fichier_pv;
    }

    public function setFichierPv(?string $fichier_pv): static
    {
        $this->fichier_pv = $fichier_pv;

        return $this;
    }

    public function isLaisserPasser(): ?bool
    {
        return $this->laisserPasser;
    }

    public function setLaisserPasser(?bool $laisserPasser): static
    {
        $this->laisserPasser = $laisserPasser;

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
            $historiqueFormation->setChangeRf($this);
        }

        return $this;
    }

    public function removeHistoriqueFormation(HistoriqueFormation $historiqueFormation): static
    {
        if ($this->historiqueFormations->removeElement($historiqueFormation)) {
            // set the owning side to null (unless already changed)
            if ($historiqueFormation->getChangeRf() === $this) {
                $historiqueFormation->setChangeRf(null);
            }
        }

        return $this;
    }

    public function getDatePriseFonction(): ?\DateTimeInterface
    {
        return $this->datePriseFonction;
    }

    public function setDatePriseFonction(?\DateTimeInterface $datePriseFonction): static
    {
        $this->datePriseFonction = $datePriseFonction;

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

    public function getAnneeUniversitaireDebut(): ?int
    {
        if ($this->datePriseFonction === null) {
            return $this->campagneCollecte?->getAnneeUniversitaire()?->getAnnee();
        }

        $month = (int) $this->datePriseFonction->format('n');
        $year = (int) $this->datePriseFonction->format('Y');

        return $month >= 9 ? $year : $year - 1;
    }

    public function getAnneeUniversitaireLibelle(): ?string
    {
        $debut = $this->getAnneeUniversitaireDebut();
        if ($debut === null) {
            return null;
        }

        return sprintf('%d-%d', $debut, $debut + 1);
    }
}
