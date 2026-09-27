<?php

namespace App\Entity;

use App\Repository\UeMutualisableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;

#[ORM\Entity(repositoryClass: UeMutualisableRepository::class)]
class UeMutualisable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[MaxDepth(1)]
    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne(inversedBy: 'ueMutualisables')]
    private ?Ue $ue = null;

    #[ORM\ManyToOne(inversedBy: 'ueMutualisables')]
    private ?Parcours $parcours = null;

    #[MaxDepth(1)]
    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'ueRaccrochee', targetEntity: Ue::class)]
    private Collection $ues;

    public function __construct()
    {
        $this->ues = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUe(): ?Ue
    {
        return $this->ue;
    }

    public function setUe(?Ue $ue): self
    {
        $this->ue = $ue;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): self
    {
        $this->parcours = $parcours;

        return $this;
    }

    /**
     * @return Collection<int, Ue>
     */
    public function getUes(): Collection
    {
        return $this->ues;
    }

    public function addUe(Ue $ue): self
    {
        if (!$this->ues->contains($ue)) {
            $this->ues->add($ue);
            $ue->setUeRaccrochee($this);
        }

        return $this;
    }

    public function removeUe(Ue $ue): self
    {
        if ($this->ues->removeElement($ue)) {
            // set the owning side to null (unless already changed)
            if ($ue->getUeRaccrochee() === $this) {
                $ue->setUeRaccrochee(null);
            }
        }

        return $this;
    }

    public function display(): string
    {
        return $this->getUe()?->display($this->getParcours()) . ' - ' . $this->getParcours()?->getDisplay();
    }
}
