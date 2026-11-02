<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'referentiel_valeur')]
#[ORM\UniqueConstraint(name: 'uniq_referentiel_valeur_code', columns: ['referentiel_id', 'code'])]
#[ORM\UniqueConstraint(name: 'uniq_referentiel_valeur_legacy', columns: ['referentiel_id', 'legacy_value'])]
class ReferentielValeur
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Referentiel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?Referentiel $referentiel = null;
    #[ORM\Column(length: 100)]
    private string $code = '';
    #[ORM\Column(length: 255)]
    private string $libelle = '';
    #[ORM\Column(name: 'legacy_value', length: 255, nullable: true)]
    private ?string $legacyValue = null;
    #[ORM\Column]
    private int $ordre = 0;
    #[ORM\Column]
    private bool $actif = true;
    #[ORM\Column]
    private bool $systeme = false;
    #[ORM\Column(type: 'json')]
    private array $config = [];

    public function getId(): ?int { return $this->id; }
    public function getReferentiel(): ?Referentiel { return $this->referentiel; }
    public function setReferentiel(Referentiel $value): static { $this->referentiel = $value; return $this; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $value): static { $this->code = $value; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $value): static { $this->libelle = $value; return $this; }
    public function getLegacyValue(): ?string { return $this->legacyValue; }
    public function setLegacyValue(?string $value): static { $this->legacyValue = $value; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $value): static { $this->ordre = $value; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $value): static { $this->actif = $value; return $this; }
    public function isSysteme(): bool { return $this->systeme; }
    public function setSysteme(bool $value): static { $this->systeme = $value; return $this; }
    public function getConfig(): array { return $this->config; }
    public function setConfig(array $value): static { $this->config = $value; return $this; }
    public function __toString(): string { return $this->libelle; }
}
