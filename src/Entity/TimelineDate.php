<?php

namespace App\Entity;

use App\Repository\TimelineDateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enums\TimelineDateFlagEnum;

#[ORM\Entity(repositoryClass: TimelineDateRepository::class)]
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
    private ?bool $inTimeline = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(type: 'string', length: 30, enumType: TimelineDateFlagEnum::class)]
    private TimelineDateFlagEnum $flag = TimelineDateFlagEnum::NONE;

    public function getId(): ?int
    {
        return $this->id;
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

        return $this;
    }

    public function isCfvu(): bool
    {
        return $this->flag === TimelineDateFlagEnum::CFVU;
    }

    public function setIsCfvu(bool $isCfvu): static
    {
        $this->flag = $isCfvu ? TimelineDateFlagEnum::CFVU : TimelineDateFlagEnum::NONE;

        return $this;
    }
}
