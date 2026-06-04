<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/VolumeHoraireParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 07/04/2026 12:10
 */

namespace App\Entity;

use App\Repository\VolumeHoraireParcoursRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VolumeHoraireParcoursRepository::class)]
#[ORM\Table(
    name: 'volume_horaire_parcours',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_VOLUME_HORAIRE_PARCOURS_CAMPAGNE', columns: ['parcours_id', 'campagne_collecte_id'])]
)]
class VolumeHoraireParcours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, name: 'campagne_collecte_id')]
    private ?CampagneCollecte $campagneCollecte = null;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresCmPres = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresTdPres = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresTpPres = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresTePres = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresCmDist = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresTdDist = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $heuresTpDist = 0.0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $volumesAnnee = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $volumesSemestre = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $dateCalcul = null;

    public function __construct()
    {
        $this->dateCalcul = new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCampagneCollecte(): ?CampagneCollecte
    {
        return $this->campagneCollecte;
    }

    public function setCampagneCollecte(?CampagneCollecte $campagneCollecte): static
    {
        $this->campagneCollecte = $campagneCollecte;

        return $this;
    }

    public function getHeuresCmPres(): float
    {
        return $this->heuresCmPres;
    }

    public function setHeuresCmPres(float $heuresCmPres): static
    {
        $this->heuresCmPres = $heuresCmPres;

        return $this;
    }

    public function getHeuresTdPres(): float
    {
        return $this->heuresTdPres;
    }

    public function setHeuresTdPres(float $heuresTdPres): static
    {
        $this->heuresTdPres = $heuresTdPres;

        return $this;
    }

    public function getHeuresTpPres(): float
    {
        return $this->heuresTpPres;
    }

    public function setHeuresTpPres(float $heuresTpPres): static
    {
        $this->heuresTpPres = $heuresTpPres;

        return $this;
    }

    public function getHeuresTePres(): float
    {
        return $this->heuresTePres;
    }

    public function setHeuresTePres(float $heuresTePres): static
    {
        $this->heuresTePres = $heuresTePres;

        return $this;
    }

    public function getHeuresCmDist(): float
    {
        return $this->heuresCmDist;
    }

    public function setHeuresCmDist(float $heuresCmDist): static
    {
        $this->heuresCmDist = $heuresCmDist;

        return $this;
    }

    public function getHeuresTdDist(): float
    {
        return $this->heuresTdDist;
    }

    public function setHeuresTdDist(float $heuresTdDist): static
    {
        $this->heuresTdDist = $heuresTdDist;

        return $this;
    }

    public function getHeuresTpDist(): float
    {
        return $this->heuresTpDist;
    }

    public function setHeuresTpDist(float $heuresTpDist): static
    {
        $this->heuresTpDist = $heuresTpDist;

        return $this;
    }

    public function getDateCalcul(): ?DateTimeInterface
    {
        return $this->dateCalcul;
    }

    public function setDateCalcul(DateTimeInterface $dateCalcul): static
    {
        $this->dateCalcul = $dateCalcul;

        return $this;
    }

    public function getHeuresAnnee(int $annee): float
    {
        $volumes = $this->getVolumesAnnee();

        return (float)($volumes[$annee]['total'] ?? 0.0);
    }

    public function getVolumesAnnee(): array
    {
        return $this->volumesAnnee ?? [];
    }

    public function setVolumesAnnee(?array $volumesAnnee): static
    {
        $this->volumesAnnee = $volumesAnnee;

        return $this;
    }

    public function getHeuresSemestre(int $semestre): float
    {
        $volumes = $this->getVolumesSemestre();

        return (float)($volumes[$semestre]['total'] ?? 0.0);
    }

    public function getVolumesSemestre(): array
    {
        return $this->volumesSemestre ?? [];
    }

    public function setVolumesSemestre(?array $volumesSemestre): static
    {
        $this->volumesSemestre = $volumesSemestre;

        return $this;
    }

    /**
     * Volume horaire total (présentiel CM+TD+TP + distanciel CM+TD+TP), hors TE.
     */
    public function getHeuresTotal(): float
    {
        return $this->heuresCmPres + $this->heuresTdPres + $this->heuresTpPres
            + $this->heuresCmDist + $this->heuresTdDist + $this->heuresTpDist;
    }

    public function getHeuresTotalMajore(): float
    {
        return $this->heuresCmPres * 1.5 + $this->heuresTdPres + $this->heuresTpPres
            + $this->heuresCmDist * 1.5 + $this->heuresTdDist + $this->heuresTpDist;
    }

    /**
     * Volume horaire total présentiel (CM+TD+TP).
     */
    public function getHeuresTotalPres(): float
    {
        return $this->heuresCmPres + $this->heuresTdPres + $this->heuresTpPres;
    }

    /**
     * Volume horaire total distanciel (CM+TD+TP).
     */
    public function getHeuresTotalDist(): float
    {
        return $this->heuresCmDist + $this->heuresTdDist + $this->heuresTpDist;
    }
}

