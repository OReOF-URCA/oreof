<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Mccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/02/2023 12:31
 */

namespace App\Entity;

use App\Repository\McccRepository;
use App\Utils\Tools;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: McccRepository::class)]
class Mccc
{
    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(length: 150)]
    private ?string $libelle = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?int $numeroSession = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?bool $secondeChance = false;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?float $pourcentage = 0;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?int $nbEpreuves = 0;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $typeEpreuve = [];

    #[ORM\ManyToOne(inversedBy: 'mcccs')]
    private ?ElementConstitutif $ec = null;

    #[ORM\ManyToOne(inversedBy: 'mcccs')]
    private ?FicheMatiere $ficheMatiere = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?bool $controleContinu = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column]
    private ?bool $examenTerminal = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $duree = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(nullable: true)]
    private ?int $numeroEpreuve = null;

    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $justificationText = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getNumeroSession(): ?int
    {
        return $this->numeroSession;
    }

    public function setNumeroSession(int $numeroSession): self
    {
        $this->numeroSession = $numeroSession;

        return $this;
    }

    public function isSecondeChance(): ?bool
    {
        return $this->secondeChance;
    }

    public function setSecondeChance(bool $secondeChance): self
    {
        $this->secondeChance = $secondeChance;

        return $this;
    }

    public function getPourcentage(): ?float
    {
        return $this->pourcentage ?? null;
    }

    public function setPourcentage(?float $pourcentage): self
    {
        $this->pourcentage = $pourcentage;

        return $this;
    }

    public function getNbEpreuves(): ?int
    {
        return $this->nbEpreuves;
    }

    public function setNbEpreuves(int $nbEpreuves): self
    {
        $this->nbEpreuves = $nbEpreuves;

        return $this;
    }

    public function getTypeEpreuve(): array
    {
        return $this->typeEpreuve ?? [];
    }

    public function setTypeEpreuve(?array $typeEpreuve): self
    {
        $this->typeEpreuve = $typeEpreuve;

        return $this;
    }

    public function getEc(): ?ElementConstitutif
    {
        return $this->ec;
    }

    public function setEc(?ElementConstitutif $ec): self
    {
        $this->ec = $ec;

        return $this;
    }

    public function isControleContinu(): ?bool
    {
        return $this->controleContinu;
    }

    public function setControleContinu(bool $controleContinu): self
    {
        $this->controleContinu = $controleContinu;

        return $this;
    }

    public function isExamenTerminal(): ?bool
    {
        return $this->examenTerminal;
    }

    public function setExamenTerminal(bool $examenTerminal): self
    {
        $this->examenTerminal = $examenTerminal;

        return $this;
    }

    public function getDuree(): ?DateTimeInterface
    {
        return $this->duree;
    }

    public function setDuree(?DateTimeInterface $duree): static
    {
        $this->duree = $duree;

        return $this;
    }

    public function getNumeroEpreuve(): ?int
    {
        return $this->numeroEpreuve;
    }

    public function setNumeroEpreuve(?int $numeroEpreuve): static
    {
        $this->numeroEpreuve = $numeroEpreuve;

        return $this;
    }

    public function getOptions(): ?array
    {
        return $this->options;
    }

    public function setOptions(?array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function hasTp(): bool
    {
        if ($this->options === null) {
            return false;
        }
        //regarde si la clé has_tp existe dans options et si c'est à on
        return array_key_exists('cc_has_tp', $this->options) && $this->options['cc_has_tp'] === 'on';
    }

    public function pourcentageTp(): float
    {
        if ($this->options === null) {
            return 0;
        }
        //regarde si la clé has_tp existe dans options et si c'est à on
        return array_key_exists('pourcentage', $this->options) ? $this->options['pourcentage'] : 0;
    }

    public function getFicheMatiere(): ?FicheMatiere
    {
        return $this->ficheMatiere;
    }

    public function setFicheMatiere(?FicheMatiere $ficheMatiere): static
    {
        $this->ficheMatiere = $ficheMatiere;

        return $this;
    }

    public function setId(mixed $id): void
    {
        $this->id = $id;
    }

    public function getMcccTexte(): string
    {
        //fusion des champs textes pour affichage
        $texte = $this->libelle;
        $texte.= ' - ' . $this->numeroSession;
        if ($this->secondeChance) {
            $texte .= ' - Seconde chance';
        }
        if ($this->pourcentage !== null) {
            $texte .= ' - ' . $this->pourcentage . '%';
        }
        if ($this->nbEpreuves !== null) {
            $texte .= ' - ' . $this->nbEpreuves . ' épreuves';
        }
        if ($this->typeEpreuve !== null) {
            $texte .= ' - ' . implode(', ', $this->typeEpreuve);
        }
        if ($this->controleContinu) {
            $texte .= ' - Contrôle continu';
        }
        if ($this->examenTerminal) {
            $texte .= ' - Examen terminal';
        }
        if ($this->duree !== null) {
            $texte .= ' - ' . $this->duree->format('H:i');
        }

        return $texte;
    }

    public function getJustificationText(): ?string
    {
        return $this->justificationText;
    }

    public function setJustificationText(?string $justificationText): static
    {
        $this->justificationText = $justificationText;

        return $this;
    }

    public function getCleUnique(): string
    {
        $slug = Tools::slug($this->libelle);
        $slug .= '-' . $this->numeroSession . '-' . $this->numeroEpreuve;

        return $slug;
    }

    public function getOwner(): FicheMatiere|ElementConstitutif|null
    {
        if ($this->ficheMatiere !== null) {
            return $this->ficheMatiere;
        }

        return $this->ec;
    }
}
