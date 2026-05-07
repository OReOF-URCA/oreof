<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Semestre.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 12/03/2023 21:20
 */

namespace App\Entity;

use App\Entity\Traits\HasBeenEditedTrait;
use App\Entity\Traits\ValidatableTrait;
use App\Repository\SemestreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: SemestreRepository::class)]
class Semestre
{
    use HasBeenEditedTrait;
    use ValidatableTrait;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\OneToMany(mappedBy: 'semestre', targetEntity: Ue::class, cascade: ['persist'], fetch: 'EAGER')]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $ues;

    #[Groups('DTO_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'semestre', targetEntity: SemestreParcours::class, cascade: ['persist', 'remove'])]
    private Collection $semestreParcours;

    #[ORM\Column]
    private ?bool $troncCommun = false;

    #[MaxDepth(1)]
    #[Groups('DTO_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'semestre', targetEntity: SemestreMutualisable::class)]
    private Collection $semestreMutualisables;

    #[MaxDepth(1)]
    #[Groups('DTO_json_versioning')]
    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'semestres')]
    private ?SemestreMutualisable $semestreRaccroche = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column]
    private ?bool $nonDispense = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogee = null;

    #[ORM\OneToOne(targetEntity: self::class)]
    private ?self $semestreOrigineCopie = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastModification = null;

    /**
     * @var Collection<int, ValidationIssue>
     */
    #[ORM\OneToMany(mappedBy: 'semestre', targetEntity: ValidationIssue::class)]
    private Collection $validationIssues;

    public function __construct()
    {
        $this->ues = new ArrayCollection();
        $this->semestreParcours = new ArrayCollection();
        $this->semestreMutualisables = new ArrayCollection();
        $this->validationIssues = new ArrayCollection();
        $this->lastModification = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    /**
     * @return Collection<int, Ue>
     */
    public function getUes(): Collection
    {
        return $this->ues;
    }

    public function addUe(Ue $ue): self
    {
        if (!$this->ues->contains($ue)) {
            $this->ues->add($ue);
            $ue->setSemestre($this);
        }

        return $this;
    }

    public function removeUe(Ue $ue): self
    {
        // set the owning side to null (unless already changed)
        if ($this->ues->removeElement($ue) && $ue->getSemestre() === $this) {
            $ue->setSemestre(null);
        }

        return $this;
    }

    public function display(): string
    {
        return 'S' . $this->getOrdre();
    }

    public function totalEctsSemestre(): float
    {
        $total = 0.0;
        foreach ($this->getUes() as $ue) {
            if ($ue->getUeRaccrochee() !== null &&
                $ue->getUeRaccrochee()->getUe() !== null &&
                $ue->getUeRaccrochee()->getUe()->getUeParent() === null) {
                $total += $ue->getUeRaccrochee()->getUe()->totalEctsUe();
            } elseif ($ue->getUeParent() === null) {
                $total += $ue->totalEctsUe();
            }
        }

        return $total;
    }

    /**
     * @return Collection<int, SemestreParcours>
     */
    public function getSemestreParcours(): Collection
    {
        return $this->semestreParcours;
    }

    public function addSemestreParcour(SemestreParcours $semestreParcour): self
    {
        if (!$this->semestreParcours->contains($semestreParcour)) {
            $this->semestreParcours->add($semestreParcour);
            $semestreParcour->setSemestre($this);
        }

        return $this;
    }

    public function removeSemestreParcour(SemestreParcours $semestreParcour): self
    {
        // set the owning side to null (unless already changed)
        if ($this->semestreParcours->removeElement($semestreParcour) && $semestreParcour->getSemestre() === $this) {
            $semestreParcour->setSemestre(null);
        }

        return $this;
    }

    public function isTroncCommun(): ?bool
    {
        return $this->troncCommun;
    }

    public function setTroncCommun(bool $troncCommun): self
    {
        $this->troncCommun = $troncCommun;

        return $this;
    }

    public function nbUes(): int
    {
        $tabUes = [];

        foreach ($this->getUes() as $ue) {
            $tabUes[$ue->getOrdre()] = 1;
        }

        return count($tabUes);
    }

    /**
     * @return Collection<int, SemestreMutualisable>
     */
    public function getSemestreMutualisables(): Collection
    {
        return $this->semestreMutualisables;
    }

    public function addSemestreMutualisable(SemestreMutualisable $semestreMutualisable): self
    {
        if (!$this->semestreMutualisables->contains($semestreMutualisable)) {
            $this->semestreMutualisables->add($semestreMutualisable);
            $semestreMutualisable->setSemestre($this);
        }

        return $this;
    }

    public function removeSemestreMutualisable(SemestreMutualisable $semestreMutualisable): self
    {
        if ($this->semestreMutualisables->removeElement($semestreMutualisable)) {
            // set the owning side to null (unless already changed)
            if ($semestreMutualisable->getSemestre() === $this) {
                $semestreMutualisable->setSemestre(null);
            }
        }

        return $this;
    }

    public function getSemestreRaccroche(): ?SemestreMutualisable
    {
        return $this->semestreRaccroche;
    }

    public function setSemestreRaccroche(?SemestreMutualisable $semestreRaccroche): self
    {
        $this->semestreRaccroche = $semestreRaccroche;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function isNonDispense(): ?bool
    {
        return $this->nonDispense;
    }

    public function setNonDispense(bool $nonDispense): self
    {
        $this->nonDispense = $nonDispense;

        return $this;
    }

    public function getCodeApogee(): ?string
    {
        return $this->codeApogee;
    }

    public function setCodeApogee(?string $codeApogee): static
    {
        $this->codeApogee = $codeApogee;

        return $this;
    }

    public function getSemestreOrigineCopie(): ?self
    {
        return $this->semestreOrigineCopie;
    }

    public function setSemestreOrigineCopie(?self $semestreOrigineCopie): static
    {
        $this->semestreOrigineCopie = $semestreOrigineCopie;

        return $this;
    }

    public function getLastModification(): ?\DateTimeImmutable
    {
        return $this->lastModification;
    }

    public function setLastModification(\DateTimeImmutable $lastModification): static
    {
        $this->lastModification = $lastModification;

        return $this;
    }

    /**
     * @return Collection<int, ValidationIssue>
     */
    public function getValidationIssues(): Collection
    {
        return $this->validationIssues;
    }

    public function addValidationIssue(ValidationIssue $validationIssue): static
    {
        if (!$this->validationIssues->contains($validationIssue)) {
            $this->validationIssues->add($validationIssue);
            $validationIssue->setSemestre($this);
        }

        return $this;
    }

    public function removeValidationIssue(ValidationIssue $validationIssue): static
    {
        if ($this->validationIssues->removeElement($validationIssue)) {
            // set the owning side to null (unless already changed)
            if ($validationIssue->getSemestre() === $this) {
                $validationIssue->setSemestre(null);
            }
        }

        return $this;
    }
}
