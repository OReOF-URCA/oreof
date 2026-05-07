<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/BlocCompetence.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 05/03/2023 21:57
 */

namespace App\Entity;

use App\Repository\BlocCompetenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BlocCompetenceRepository::class)]
class BlocCompetence
{
    #[Groups('fiche_matiere_versioning')]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Column(type: 'text')]
    private ?string $libelle = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'blocCompetence', targetEntity: Competence::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $competences;

    #[ORM\ManyToOne(inversedBy: 'blocCompetences')]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne(inversedBy: 'blocCompetences')]
    private ?Formation $formation = null;

    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $blocCompetenceOrigineCopie = null;

    #[ORM\ManyToOne(inversedBy: 'blocCompetences')]
    private ?CampagneCollecte $campagneCollecte = null;

    public function __construct()
    {
        $this->competences = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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
     * @return Collection<int, Competence>
     */
    public function getCompetences(): Collection
    {
        return $this->competences;
    }

    public function addCompetence(Competence $competence): self
    {
        if (!$this->competences->contains($competence)) {
            $this->competences->add($competence);
            $competence->setBlocCompetence($this);
        }

        return $this;
    }

    public function removeCompetence(Competence $competence): self
    {
        // set the owning side to null (unless already changed)
        if ($this->competences->removeElement($competence) && $competence->getBlocCompetence() === $this) {
            $competence->setBlocCompetence(null);
        }

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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): self
    {
        $this->formation = $formation;

        return $this;
    }

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function display(): string
    {
        return $this->getCode() . ' - '. $this->getLibelle();
    }

    public function genereCode(): void
    {
        $this->setCode('BC ' . $this->getOrdre());
    }

    public function getBlocCompetenceOrigineCopie(): ?self
    {
        return $this->blocCompetenceOrigineCopie;
    }

    public function setBlocCompetenceOrigineCopie(?self $blocCompetenceOrigineCopie): static
    {
        $this->blocCompetenceOrigineCopie = $blocCompetenceOrigineCopie;

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
}
