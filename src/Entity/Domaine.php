<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Domaine.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/02/2023 23:23
 */

namespace App\Entity;

use App\Repository\DomaineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DomaineRepository::class)]
class Domaine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 20)]
    private ?string $sigle = null;

    #[ORM\Column(length: 1)]
    private ?string $codeApogee = null;

    /**
     * @var Collection<int, Mention>
     */
    #[Groups('formation_json_versioning')]
    #[ORM\ManyToMany(targetEntity: Mention::class, mappedBy: 'domaines')]
    private Collection $mentions;

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }

    public function __construct()
    {
        $this->mentions = new ArrayCollection();
    }

    public function nbMentions(): int
    {
        return $this->mentions->count();
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

    public function getSigle(): ?string
    {
        return $this->sigle;
    }

    public function setSigle(string $sigle): self
    {
        $this->sigle = $sigle;

        return $this;
    }

    public function getCodeApogee(): ?string
    {
        return $this->codeApogee;
    }

    public function setCodeApogee(string $codeApogee): static
    {
        $this->codeApogee = $codeApogee;

        return $this;
    }

    /**
     * @return Collection<int, Mention>
     */
    public function getMentions(): Collection
    {
        return $this->mentions;
    }

    public function addMentions(Mention $mentions): static
    {
        if (!$this->mentions->contains($mentions)) {
            $this->mentions->add($mentions);
            $mentions->addDomaine($this);
        }

        return $this;
    }

    public function removeMentions(Mention $mentions): static
    {
        if ($this->mentions->removeElement($mentions)) {
            $mentions->removeDomaine($this);
        }

        return $this;
    }
}
