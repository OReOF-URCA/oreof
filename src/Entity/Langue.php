<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Langue.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 10:40
 */

namespace App\Entity;

use App\Repository\LangueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LangueRepository::class)]
class Langue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('fiche_matiere_versioning')]
    #[ORM\Column(length: 50)]
    private ?string $libelle = null;

    #[ORM\ManyToMany(targetEntity: FicheMatiere::class, mappedBy: 'langueDispense')]
    private Collection $ficheMatieres;

    #[ORM\ManyToMany(targetEntity: FicheMatiere::class, mappedBy: 'langueSupport')]
    private Collection $languesSupportsFicheMatieres;

    #[ORM\Column(length: 2)]
    private ?string $codeIso = null;

    public function __construct()
    {
        $this->ficheMatieres = new ArrayCollection();
        $this->languesSupportsFicheMatieres = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, FicheMatiere>
     */
    public function getFicheMatieres(): Collection
    {
        return $this->ficheMatieres;
    }

    public function addFicheMatiere(FicheMatiere $ficheMatiere): self
    {
        if (!$this->ficheMatieres->contains($ficheMatiere)) {
            $this->ficheMatieres->add($ficheMatiere);
            $ficheMatiere->addLangueDispense($this);
        }

        return $this;
    }

    public function removeFicheMatiere(FicheMatiere $ficheMatiere): self
    {
        if ($this->ficheMatieres->removeElement($ficheMatiere)) {
            $ficheMatiere->removeLangueDispense($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, FicheMatiere>
     */
    public function getLanguesSupportsFicheMatieres(): Collection
    {
        return $this->languesSupportsFicheMatieres;
    }

    public function addLanguesSupportsFicheMatiere(FicheMatiere $languesSupportsFicheMatiere): self
    {
        if (!$this->languesSupportsFicheMatieres->contains($languesSupportsFicheMatiere)) {
            $this->languesSupportsFicheMatieres->add($languesSupportsFicheMatiere);
            $languesSupportsFicheMatiere->addLangueSupport($this);
        }

        return $this;
    }

    public function removeLanguesSupportsFicheMatiere(FicheMatiere $languesSupportsEc): self
    {
        if ($this->languesSupportsFicheMatieres->removeElement($languesSupportsEc)) {
            $languesSupportsEc->removeLangueSupport($this);
        }

        return $this;
    }

    public function getCodeIso(): ?string
    {
        return $this->codeIso;
    }

    public function setCodeIso(string $codeIso): self
    {
        $this->codeIso = $codeIso;

        return $this;
    }
}
