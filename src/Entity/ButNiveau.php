<?php

namespace App\Entity;

use App\Repository\ButNiveauxRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ButNiveauxRepository::class)]
class ButNiveau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column]
    private ?int $ordre = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $annee = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToMany(mappedBy: 'niveau', targetEntity: ButApprentissageCritique::class)]
    #[ORM\OrderBy(['code' => 'ASC'])]
    private Collection $butApprentissageCritiques;
 
    #[ORM\ManyToOne(inversedBy: 'butNiveaux')]
    private ?ButCompetence $competence = null;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $butNiveauOrigineCopie = null;

    public function __construct()
    {
        $this->butApprentissageCritiques = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAnnee(): ?string
    {
        return $this->annee;
    }

    public function setAnnee(?string $annee): self
    {
        $this->annee = $annee;

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
     * @return Collection<int, ButApprentissageCritique>
     */
    public function getButApprentissageCritiques(): Collection
    {
        return $this->butApprentissageCritiques;
    }

    public function addButApprentissageCritique(ButApprentissageCritique $butApprentissageCritique): self
    {
        if (!$this->butApprentissageCritiques->contains($butApprentissageCritique)) {
            $this->butApprentissageCritiques->add($butApprentissageCritique);
            $butApprentissageCritique->setNiveau($this);
        }

        return $this;
    }

    public function removeButApprentissageCritique(ButApprentissageCritique $butApprentissageCritique): self
    {
        if ($this->butApprentissageCritiques->removeElement($butApprentissageCritique)) {
            // set the owning side to null (unless already changed)
            if ($butApprentissageCritique->getNiveau() === $this) {
                $butApprentissageCritique->setNiveau(null);
            }
        }

        return $this;
    }

    public function getCompetence(): ?ButCompetence
    {
        return $this->competence;
    }

    public function setCompetence(?ButCompetence $competence): self
    {
        $this->competence = $competence;

        return $this;
    }

    public function getButNiveauOrigineCopie(): ?self
    {
        return $this->butNiveauOrigineCopie;
    }

    public function setButNiveauOrigineCopie(?self $butNiveauOrigineCopie): static
    {
        $this->butNiveauOrigineCopie = $butNiveauOrigineCopie;

        return $this;
    }
}
