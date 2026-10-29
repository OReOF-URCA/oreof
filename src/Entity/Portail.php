<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PortailRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortailRepository::class)]
#[ORM\Table(name: 'portail')]
class Portail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: CampagneCollecte::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagneCollecte $campagne = null;

    /**
     * @var Collection<int, Annee>
     */
    #[ORM\OneToMany(mappedBy: 'portail', targetEntity: Annee::class)]
    private Collection $annees;

    #[ORM\Column(options: ['default' => 0])]
    private int $capaciteAccueil = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $created = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $updated = null;

    public function __construct()
    {
        $this->annees = new ArrayCollection();
        $this->created = new DateTimeImmutable();
        $this->updated = new DateTimeImmutable();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getFormationsIds(): array
    {
        if ($this->description !== null && str_starts_with($this->description, '{')) {
            $data = json_decode($this->description, true);
            if (is_array($data) && isset($data['formations_ids']) && is_array($data['formations_ids'])) {
                return array_values(array_map('intval', $data['formations_ids']));
            }
        }

        $ids = [];
        foreach ($this->annees as $a) {
            $fId = $a->getParcours()?->getFormation()?->getId();
            if ($fId !== null && !in_array($fId, $ids, true)) {
                $ids[] = $fId;
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     */
    public function setFormationsIds(array $ids): static
    {
        $data = [];
        if ($this->description !== null && str_starts_with($this->description, '{')) {
            $data = json_decode($this->description, true);
            if (!is_array($data)) {
                $data = [];
            }
        }
        $data['formations_ids'] = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $this->description = json_encode($data);

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

    /**
     * @return Collection<int, Annee>
     */
    public function getAnnees(): Collection
    {
        return $this->annees;
    }

    public function addAnnee(Annee $annee): static
    {
        if (!$this->annees->contains($annee)) {
            $this->annees->add($annee);
            $annee->setPortail($this);
        }

        return $this;
    }

    public function removeAnnee(Annee $annee): static
    {
        if ($this->annees->removeElement($annee)) {
            if ($annee->getPortail() === $this) {
                $annee->setPortail(null);
            }
        }

        return $this;
    }

    public function getCapaciteAccueil(): int
    {
        return $this->capaciteAccueil;
    }

    public function setCapaciteAccueil(int $capaciteAccueil): static
    {
        $this->capaciteAccueil = $capaciteAccueil;

        return $this;
    }

    public function getCreated(): ?DateTimeImmutable
    {
        return $this->created;
    }

    public function setCreated(?DateTimeImmutable $created): static
    {
        $this->created = $created;

        return $this;
    }

    public function getUpdated(): ?DateTimeImmutable
    {
        return $this->updated;
    }

    public function setUpdated(?DateTimeImmutable $updated): static
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Retourne la liste unique des formations associées aux années de ce portail.
     *
     * @return array<int, Formation>
     */
    public function getFormations(): array
    {
        $formations = [];
        foreach ($this->annees as $annee) {
            $formation = $annee->getParcours()?->getFormation();
            if ($formation !== null && $formation->getId() !== null) {
                $formations[$formation->getId()] = $formation;
            }
        }

        return array_values($formations);
    }

    /**
     * Retourne la structure des formations associées à ce portail avec pour chacune :
     * - la formation
     * - la liste des ordres d'années sélectionnées
     * - la liste des IDs de parcours sélectionnés
     *
     * @return array<int, array{
     *     formation: Formation,
     *     anneesOrdres: list<int>,
     *     parcoursIds: list<int>,
     *     anneeIds: list<int>
     * }>
     */
    /**
     * @param array<int|string, Formation> $knownFormations Formations de la campagne
     * @return array<int, array{
     *     formation: Formation,
     *     anneesOrdres: list<int>,
     *     parcoursIds: list<int>,
     *     anneeIds: list<int>
     * }>
     */
    public function getFormationsStructure(array $knownFormations = []): array
    {
        $structure = [];
        foreach ($this->annees as $annee) {
            $parcours = $annee->getParcours();
            if ($parcours === null) {
                continue;
            }
            $formation = $parcours->getFormation();
            if ($formation === null || $formation->getId() === null) {
                continue;
            }
            $fId = $formation->getId();
            if (!isset($structure[$fId])) {
                $structure[$fId] = [
                    'formation' => $formation,
                    'anneesOrdres' => [],
                    'parcoursIds' => [],
                    'anneeIds' => [],
                ];
            }
            $ordre = $annee->getOrdre();
            if ($ordre !== null && !in_array($ordre, $structure[$fId]['anneesOrdres'], true)) {
                $structure[$fId]['anneesOrdres'][] = $ordre;
            }
            $pId = $parcours->getId();
            if ($pId !== null && !in_array($pId, $structure[$fId]['parcoursIds'], true)) {
                $structure[$fId]['parcoursIds'][] = $pId;
            }
            $aId = $annee->getId();
            if ($aId !== null && !in_array($aId, $structure[$fId]['anneeIds'], true)) {
                $structure[$fId]['anneeIds'][] = $aId;
            }
        }

        // Compléter avec les formations configurées explicitement qui n'ont pas encore d'années rattachées
        $formationsById = [];
        foreach ($knownFormations as $f) {
            if ($f->getId() !== null) {
                $formationsById[$f->getId()] = $f;
            }
        }

        foreach ($this->getFormationsIds() as $fId) {
            if (!isset($structure[$fId]) && isset($formationsById[$fId])) {
                $structure[$fId] = [
                    'formation' => $formationsById[$fId],
                    'anneesOrdres' => [],
                    'parcoursIds' => [],
                    'anneeIds' => [],
                ];
            }
        }

        foreach ($structure as &$item) {
            sort($item['anneesOrdres']);
        }
        unset($item);

        return array_values($structure);
    }
}

