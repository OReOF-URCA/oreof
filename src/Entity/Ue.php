<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Ue.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 21:19
 */

namespace App\Entity;

use App\Entity\Traits\HasBeenEditedTrait;
use App\Entity\Traits\ValidatableTrait;
use App\Repository\UeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UeRepository::class)]
class Ue
{
    use HasBeenEditedTrait;
    use ValidatableTrait;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\ManyToOne(inversedBy: 'ues')]
    private ?Semestre $semestre = null;

    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER')]
    private ?TypeUe $typeUe = null;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER')]
    private ?NatureUeEc $natureUeEc = null;

    #[ORM\OneToMany(mappedBy: 'ue', targetEntity: ElementConstitutif::class, cascade: [
        'persist',
        'remove'
    ], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $elementConstitutifs;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $libelle = null;

    #[ORM\OneToMany(mappedBy: 'ue', targetEntity: UeMutualisable::class, cascade: ['remove'])]
    private Collection $ueMutualisables;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'ues')]
    private ?UeMutualisable $ueRaccrochee = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'ueEnfants')]
    private ?self $ueParent = null;

    #[ORM\OneToMany(mappedBy: 'ueParent', targetEntity: self::class, cascade: [
        'persist',
        'remove'
    ], orphanRemoval: true)]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $ueEnfants;

    #[Groups(['DTO_json_versioning'])]
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionUeLibre = null;

    #[ORM\Column(nullable: true)]
    private ?float $ects = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogee = null;

    #[ORM\OneToOne(targetEntity: self::class)]
    private ?self $ueOrigineCopie = null;

    private ?int $deserializedId = null;

    public function __construct()
    {
        $this->elementConstitutifs = new ArrayCollection();
        $this->ueMutualisables = new ArrayCollection();
        $this->ueEnfants = new ArrayCollection();
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

    public function getSemestre(): ?Semestre
    {
        return $this->semestre;
    }

    public function setSemestre(?Semestre $semestre): self
    {
        $this->semestre = $semestre;

        return $this;
    }

    /**
     * @var ?int $semestreOrdre Fix l'affichage du versioning
     */
    public function display(?Parcours $parcours = null, ?int $semestreOrdre = null, bool $subsubniveau = false, string $subniveau = ''): string
    {
        if ($this->ueParent === null) {
            $ordreue = $this->ordre;
        } else {
            if ($subsubniveau === true) {
                return $subniveau . '.' . chr($this->ordre + 64);
            }

            $ordreue = $this->ueParent->ordre . '.' . chr($this->ordre + 64);
        }

        if ($parcours !== null) {
            if($this->getSemestre()) {
                if($this->getSemestre()->getSemestreParcours()) {
                    foreach ($this->getSemestre()?->getSemestreParcours() as $semestreParcours) {
                        if ($semestreParcours->getParcours() === $parcours) {
                            if ($parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT' || $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'M2E') {
                                return 'UE ' . $semestreParcours->getOrdre() . '.' . $ordreue . ' (' . $this->getLibelle() . ')';
                            }

                            return 'UE ' . $semestreParcours->getOrdre() . '.' . $ordreue;
                        }
                    }
                }
            }


            if ($parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'BUT' || $parcours->getFormation()?->getTypeDiplome()?->getLibelleCourt() === 'M2E') {
                return 'UE ' . $this->getSemestre()?->getOrdre() . '.' . $ordreue . ' (' . $this->getLibelle() . ')';
            }
        }

        return 'UE ' . ($this->getSemestre()?->getOrdre() ?? $semestreOrdre ?? "") . '.' . $ordreue;
    }

    public function getTypeUe(): ?TypeUe
    {
        return $this->typeUe;
    }

    public function setTypeUe(?TypeUe $typeUe): self
    {
        $this->typeUe = $typeUe;

        return $this;
    }

    public function totalEctsUe(): float
    {
        if ($this->getEcts() !== null && $this->getEcts() !== 0.0) {
            return $this->getEcts();
        }

        //        if ($this->getUeRaccrochee() !== null) {
        //            return $this->getUeRaccrochee()->getUe()?->totalEctsUe();
        //        }

        $total = 0;
        if ($this->getUeEnfants()->count() === 0) {
            foreach ($this->getElementConstitutifs() as $ec) {
                if ($ec->getFicheMatiere() !== null && $ec->getFicheMatiere()->isEctsImpose()) {
                    $total += $ec->getFicheMatiere()->getEcts();
                } elseif ($ec->getEcParent() === null) {
                    $total += $ec->getEcts();
                }
            }
        } else {
            $ects = [];
            foreach ($this->getUeEnfants() as $ue) {
                $ects[] = $ue->totalEctsUe();
            }
            $total = min($ects);
        }

        return $total;
    }

    public function getNatureUeEc(): ?NatureUeEc
    {
        return $this->natureUeEc;
    }

    public function setNatureUeEc(?NatureUeEc $natureUeEc): self
    {
        $this->natureUeEc = $natureUeEc;

        return $this;
    }

    /**
     * @return Collection<int, ElementConstitutif>
     */
    public function getElementConstitutifs(): Collection
    {
        return $this->elementConstitutifs;
    }

    public function addElementConstitutif(ElementConstitutif $elementConstitutif): self
    {
        if (!$this->elementConstitutifs->contains($elementConstitutif)) {
            $this->elementConstitutifs->add($elementConstitutif);
            $elementConstitutif->setUe($this);
        }

        return $this;
    }

    public function removeElementConstitutif(ElementConstitutif $elementConstitutif): self
    {
        if ($this->elementConstitutifs->removeElement($elementConstitutif)) {
            // set the owning side to null (unless already changed)
            if ($elementConstitutif->getUe() === $this) {
                $elementConstitutif->setUe(null);
            }
        }

        return $this;
    }

    public function nbElementConstitutifs(): int
    {
        $tabElement = [];

        foreach ($this->getElementConstitutifs() as $elementConstitutif) {
            $tabElement[$elementConstitutif->getOrdre()] = 1;
        }

        return count($tabElement);
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * @return Collection<int, UeMutualisable>
     */
    public function getUeMutualisables(): Collection
    {
        return $this->ueMutualisables;
    }

    public function addUeMutualisable(UeMutualisable $ueMutualisable): self
    {
        if (!$this->ueMutualisables->contains($ueMutualisable)) {
            $this->ueMutualisables->add($ueMutualisable);
            $ueMutualisable->setUe($this);
        }

        return $this;
    }

    public function removeUeMutualisable(UeMutualisable $ueMutualisable): self
    {
        if ($this->ueMutualisables->removeElement($ueMutualisable)) {
            // set the owning side to null (unless already changed)
            if ($ueMutualisable->getUe() === $this) {
                $ueMutualisable->setUe(null);
            }
        }

        return $this;
    }

    public function getUeRaccrochee(): ?UeMutualisable
    {
        return $this->ueRaccrochee;
    }

    public function setUeRaccrochee(?UeMutualisable $ueRaccrochee): self
    {
        $this->ueRaccrochee = $ueRaccrochee;

        return $this;
    }

    public function getUeParent(): ?self
    {
        return $this->ueParent;
    }

    public function setUeParent(?self $ueParent): self
    {
        $this->ueParent = $ueParent;

        return $this;
    }

    /**
     * @return Collection<int, Ue>
     */
    public function getUeEnfants(): Collection
    {
        return $this->ueEnfants;
    }

    public function addUeEnfant(Ue $ueEnfant): self
    {
        if (!$this->ueEnfants->contains($ueEnfant)) {
            $this->ueEnfants->add($ueEnfant);
            $ueEnfant->setUeParent($this);
        }

        return $this;
    }

    public function removeUeEnfant(Ue $ueEnfant): self
    {
        if ($this->ueEnfants->removeElement($ueEnfant)) {
            // set the owning side to null (unless already changed)
            if ($ueEnfant->getUeParent() === $this) {
                $ueEnfant->setUeParent(null);
            }
        }

        return $this;
    }

    public function getDescriptionUeLibre(): ?string
    {
        return $this->descriptionUeLibre;
    }

    public function setDescriptionUeLibre(?string $descriptionUeLibre): self
    {
        $this->descriptionUeLibre = $descriptionUeLibre;

        return $this;
    }

    public function getEcts(): ?float
    {
        return $this->ects;
    }

    public function setEcts(?float $ects): self
    {
        $this->ects = $ects;

        return $this;
    }

    public function getCodeApogee(): ?string
    {
        if ($this->getUeParent() !== null) {
            if ($this->getNatureUeEc()?->isLibre()) {
                return substr($this->getUeParent()->getCodeApogee(), 0, -1).chr($this->getOrdre() + 64).'X';
            }
            return substr($this->getUeParent()->getCodeApogee(), 0, -1).chr($this->getOrdre() + 64);
        }

        return $this->codeApogee;
    }

    public function setCodeApogee(?string $codeApogee): static
    {
        $this->codeApogee = $codeApogee;

        return $this;
    }

    public function getUeOrigineCopie(): ?self
    {
        return $this->ueOrigineCopie;
    }

    public function setUeOrigineCopie(?self $ueOrigineCopie): static
    {
        $this->ueOrigineCopie = $ueOrigineCopie;

        return $this;
    }

    public function getDeserializedId() : ?int {
        return $this->deserializedId;
    }

    public function setDeserializedId(?int $id) : static {
        $this->deserializedId = $id;

        return $this;
    }
}
