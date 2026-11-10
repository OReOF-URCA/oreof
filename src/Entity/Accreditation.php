<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccreditationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AccreditationRepository::class)]
class Accreditation implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['formation:read', 'parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    #[ORM\Column]
    private ?int $anneeDebut = null;

    #[ORM\Column]
    private ?int $anneeFin = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    /**
     * @var Collection<int, Mention>
     */
    #[ORM\OneToMany(mappedBy: 'accreditation', targetEntity: Mention::class)]
    private Collection $mentions;

    public function __construct()
    {
        $this->mentions = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->libelle ?? '';
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

    public function getAnneeDebut(): ?int
    {
        return $this->anneeDebut;
    }

    public function setAnneeDebut(int $anneeDebut): static
    {
        $this->anneeDebut = $anneeDebut;

        return $this;
    }

    public function getAnneeFin(): ?int
    {
        return $this->anneeFin;
    }

    public function setAnneeFin(int $anneeFin): static
    {
        $this->anneeFin = $anneeFin;

        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getNbMentions(): int
    {
        return $this->mentions->count();
    }

    /**
     * @return Collection<int, Mention>
     */
    public function getMentions(): Collection
    {
        return $this->mentions;
    }

    public function addMention(Mention $mention): static
    {
        if (!$this->mentions->contains($mention)) {
            $this->mentions->add($mention);
            $mention->setAccreditation($this);
        }

        return $this;
    }

    public function removeMention(Mention $mention): static
    {
        if ($this->mentions->removeElement($mention)) {
            if ($mention->getAccreditation() === $this) {
                $mention->setAccreditation(null);
            }
        }

        return $this;
    }
}
