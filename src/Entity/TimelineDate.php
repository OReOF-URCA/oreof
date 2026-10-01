<?php

namespace App\Entity;

use App\Repository\TimelineDateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enums\TimelineDateFlagEnum;

#[ORM\Entity(repositoryClass: TimelineDateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TimelineDate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'timelineDates', cascade: ['persist', 'remove'])]
    private ?CampagneCollecte $campagneCollecte = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(length: 50)]
    private ?string $icone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure = null;

    #[ORM\Column]
    private ?bool $inTimeline = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $isCfvu = null;

    #[ORM\Column(type: 'string', length: 30, enumType: TimelineDateFlagEnum::class)]
    private TimelineDateFlagEnum $flag = TimelineDateFlagEnum::NONE;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $ordre = 0;

    /**
     * @var array<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $modulesActifs = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getIcone(): ?string
    {
        return $this->icone;
    }

    public function setIcone(string $icone): static
    {
        $this->icone = $icone;

        return $this;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getHeure(): ?\DateTime
    {
        return $this->heure;
    }

    public function setHeure(?\DateTime $heure): static
    {
        $this->heure = $heure;

        return $this;
    }

    public function isInTimeline(): ?bool
    {
        return $this->inTimeline;
    }

    public function setInTimeline(bool $inTimeline): static
    {
        $this->inTimeline = $inTimeline;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getFlag(): TimelineDateFlagEnum
    {
        return $this->flag;
    }

    public function setFlag(TimelineDateFlagEnum $flag): static
    {
        $this->flag = $flag;
        $this->isCfvu = ($flag === TimelineDateFlagEnum::CFVU);

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function syncIsCfvu(): void
    {
        $this->isCfvu = ($this->flag === TimelineDateFlagEnum::CFVU);
    }

    public function isCfvu(): bool
    {
        return $this->isCfvu ?? ($this->flag === TimelineDateFlagEnum::CFVU);
    }

    public function setIsCfvu(?bool $isCfvu): static
    {
        $this->isCfvu = $isCfvu ?? false;
        if ($this->isCfvu) {
            $this->flag = TimelineDateFlagEnum::CFVU;
        } elseif ($this->flag === TimelineDateFlagEnum::CFVU) {
            $this->flag = TimelineDateFlagEnum::NONE;
        }

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getModulesActifs(): array
    {
        return $this->modulesActifs ?? [];
    }

    /**
     * @param array<string|\App\Enums\CampagneModuleEnum>|null $modulesActifs
     */
    public function setModulesActifs(?array $modulesActifs): static
    {
        if ($modulesActifs === null) {
            $this->modulesActifs = [];

            return $this;
        }

        $this->modulesActifs = array_values(array_unique(array_map(
            static fn(string|\App\Enums\CampagneModuleEnum $m) => $m instanceof \App\Enums\CampagneModuleEnum ? $m->value : $m,
            $modulesActifs
        )));

        return $this;
    }

    public function hasModule(\App\Enums\CampagneModuleEnum|string $module): bool
    {
        $value = $module instanceof \App\Enums\CampagneModuleEnum ? $module->value : $module;

        return in_array($value, $this->getModulesActifs(), true);
    }
}
