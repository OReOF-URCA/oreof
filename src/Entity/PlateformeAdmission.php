<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Entity/PlateformeAdmission.php
 * @author davidannebicque
 * @project oreofv2
 * @lastUpdate 09/06/2026 22:30
 */

namespace App\Entity;

use App\Repository\PlateformeAdmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlateformeAdmissionRepository::class)]
class PlateformeAdmission
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $libelle;

    #[ORM\Column(length: 30, unique: true)]
    private string $code; // parcoursup, ecandidat, monmaster, eef

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'json')]
    private array $configuration = [];

    #[ORM\Column(type: 'json')]
    private array $definitionChamps = [];

    /**
     * @var Collection<int, PlateformeAdmissionParametre>
     */
    #[ORM\OneToMany(targetEntity: PlateformeAdmissionParametre::class, mappedBy: 'plateforme')]
    private Collection $admissionPlateformeParametres;

    /**
     * @var Collection<int, TypeDiplomePlateformeAdmission>
     */
    #[ORM\OneToMany(targetEntity: TypeDiplomePlateformeAdmission::class, mappedBy: 'plateforme')]
    private Collection $typeDiplomePlateformeAdmissions;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(length: 30, options: ['default' => 'global'])]
    private string $modeExport = 'global';

    public const string MODE_EXPORT_GLOBAL = 'global';
    public const string MODE_EXPORT_PAR_DIPLOME = 'par_diplome';

    public function __construct()
    {
        $this->admissionPlateformeParametres = new ArrayCollection();
        $this->typeDiplomePlateformeAdmissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): static
    {
        $this->configuration = $configuration;
        return $this;
    }

    public function getDefinitionChamps(): array
    {
        return $this->definitionChamps;
    }

    public function hasDefinitionChamps(): bool
    {
        if (empty($this->definitionChamps)) {
            return false;
        }

        $filtered = array_filter($this->definitionChamps, static function ($item) {
            if (is_string($item)) {
                return trim($item) !== '';
            }
            if (is_array($item)) {
                return !empty(array_filter($item));
            }
            return !empty($item);
        });

        return count($filtered) > 0;
    }

    public function setDefinitionChamps(array $definitionChamps): static
    {
        $this->definitionChamps = $definitionChamps;
        return $this;
    }

    public function __toString()
    {
        return $this->libelle;
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
            $admissionPlateformeParametre->setPlateforme($this);
        }

        return $this;
    }

    public function removeAdmissionPlateformeParametre(PlateformeAdmissionParametre $admissionPlateformeParametre): static
    {
        if ($this->admissionPlateformeParametres->removeElement($admissionPlateformeParametre)) {
            // set the owning side to null (unless already changed)
            if ($admissionPlateformeParametre->getPlateforme() === $this) {
                $admissionPlateformeParametre->setPlateforme(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TypeDiplomePlateformeAdmission>
     */
    public function getTypeDiplomePlateformeAdmissions(): Collection
    {
        return $this->typeDiplomePlateformeAdmissions;
    }

    public function addTypeDiplomePlateformeAdmission(TypeDiplomePlateformeAdmission $typeDiplomePlateformeAdmission): static
    {
        if (!$this->typeDiplomePlateformeAdmissions->contains($typeDiplomePlateformeAdmission)) {
            $this->typeDiplomePlateformeAdmissions->add($typeDiplomePlateformeAdmission);
            $typeDiplomePlateformeAdmission->setPlateforme($this);
        }

        return $this;
    }

    public function removeTypeDiplomePlateformeAdmission(TypeDiplomePlateformeAdmission $typeDiplomePlateformeAdmission): static
    {
        if ($this->typeDiplomePlateformeAdmissions->removeElement($typeDiplomePlateformeAdmission)) {
            // set the owning side to null (unless already changed)
            if ($typeDiplomePlateformeAdmission->getPlateforme() === $this) {
                $typeDiplomePlateformeAdmission->setPlateforme(null);
            }
        }

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getModeExport(): string
    {
        return $this->modeExport;
    }

    public function setModeExport(string $modeExport): static
    {
        $this->modeExport = $modeExport;

        return $this;
    }

    public function isExportParDiplome(): bool
    {
        return $this->getModeExport() === self::MODE_EXPORT_PAR_DIPLOME;
    }
}
