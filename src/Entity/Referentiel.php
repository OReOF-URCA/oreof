<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'referentiel')]
#[ORM\UniqueConstraint(name: 'uniq_referentiel_code', columns: ['code'])]
class Referentiel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 100)]
    private string $code = '';
    #[ORM\Column(length: 255)]
    private string $libelle = '';
    #[ORM\Column]
    private bool $administrable = true;
    #[ORM\Column]
    private bool $extensible = false;

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }
    public function isAdministrable(): bool { return $this->administrable; }
    public function setAdministrable(bool $value): static { $this->administrable = $value; return $this; }
    public function isExtensible(): bool { return $this->extensible; }
    public function setExtensible(bool $value): static { $this->extensible = $value; return $this; }
    public function __toString(): string { return $this->libelle; }
}
