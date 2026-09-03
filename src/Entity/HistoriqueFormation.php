<?php

namespace App\Entity;

use App\Repository\HistoriqueFormationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueFormationRepository::class)]
class HistoriqueFormation extends Historique
{

    #[ORM\ManyToOne(inversedBy: 'historiqueFormations')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(inversedBy: 'historiqueFormations')]
    private ?ChangeRf $changeRf = null;

    #[ORM\ManyToOne]
    private ?DocumentConseil $documentPv = null;

    #[ORM\ManyToOne]
    private ?DocumentConseil $documentNote = null;

    #[ORM\ManyToOne(inversedBy: 'historiqueFormations')]
    private ?DpeFormation $dpeFormation = null;

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getChangeRf(): ?ChangeRf
    {
        return $this->changeRf;
    }

    public function setChangeRf(?ChangeRf $changeRf): static
    {
        $this->changeRf = $changeRf;

        return $this;
    }

    public function getDocumentPv(): ?DocumentConseil
    {
        return $this->documentPv;
    }

    public function setDocumentPv(?DocumentConseil $documentPv): static
    {
        $this->documentPv = $documentPv;

        return $this;
    }

    public function getDocumentNote(): ?DocumentConseil
    {
        return $this->documentNote;
    }

    public function setDocumentNote(?DocumentConseil $documentNote): static
    {
        $this->documentNote = $documentNote;

        return $this;
    }

    public function getDpeFormation(): ?DpeFormation
    {
        return $this->dpeFormation;
    }

    public function setDpeFormation(?DpeFormation $dpeFormation): static
    {
        $this->dpeFormation = $dpeFormation;

        return $this;
    }
}
