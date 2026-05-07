<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Competence.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/02/2023 23:24
 */

namespace App\Entity;

use App\Repository\CompetenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CompetenceRepository::class)]
class Competence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('fiche_matiere_versioning')]
    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER', inversedBy: 'competences')]
    private ?BlocCompetence $blocCompetence;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'DTO_json_versioning'])]
    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[Groups(['parcours_json_versioning', 'fiche_matiere_versioning', 'DTO_json_versioning'])]
    #[ORM\Column(type: 'text')]
    private ?string $libelle = null;

    #[ORM\ManyToMany(targetEntity: FicheMatiere::class, mappedBy: 'competences')]
    private Collection $ficheMatieres;

    #[Groups('DTO_json_versioning')]
    #[ORM\Column]
    private ?int $ordre = null;

    #[ORM\ManyToMany(targetEntity: ElementConstitutif::class, mappedBy: 'competences')]
    private Collection $elementConstitutifs;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $competenceOrigineCopie = null;


    public function __construct(?BlocCompetence $blocCompetence)
    {
        $this->blocCompetence = $blocCompetence;
        $this->ficheMatieres = new ArrayCollection();
        $this->elementConstitutifs = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlocCompetence(): ?BlocCompetence
    {
        return $this->blocCompetence;
    }

    public function setBlocCompetence(?BlocCompetence $blocCompetence): self
    {
        $this->blocCompetence = $blocCompetence;

        return $this;
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
            $ficheMatiere->addCompetence($this);
        }

        return $this;
    }

    public function removeFicheMatiere(FicheMatiere $ficheMatiere): self
    {
        if ($this->ficheMatieres->removeElement($ficheMatiere)) {
            $ficheMatiere->removeCompetence($this);
        }

        return $this;
    }

    public function display(): string
    {
        return $this->getCode() . ' - '. $this->getLibelle();
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

    public function genereCode(): void
    {
        $this->setCode($this->getBlocCompetence()?->getOrdre()  . chr($this->getOrdre()+64));
    }

    /**
     * @return Collection<int, ElementConstitutif>
     */
    public function getElementConstitutifs(): Collection
    {
        return $this->elementConstitutifs;
    }

    public function addElementConstitutif(ElementConstitutif $elementConstitutif): static
    {
        if (!$this->elementConstitutifs->contains($elementConstitutif)) {
            $this->elementConstitutifs->add($elementConstitutif);
            $elementConstitutif->addCompetence($this);
        }

        return $this;
    }

    public function removeElementConstitutif(ElementConstitutif $elementConstitutif): static
    {
        if ($this->elementConstitutifs->removeElement($elementConstitutif)) {
            $elementConstitutif->removeCompetence($this);
        }

        return $this;
    }

    public function getCompetenceOrigineCopie(): ?self
    {
        return $this->competenceOrigineCopie;
    }

    public function setCompetenceOrigineCopie(?self $competenceOrigineCopie): static
    {
        $this->competenceOrigineCopie = $competenceOrigineCopie;

        return $this;
    }
}
