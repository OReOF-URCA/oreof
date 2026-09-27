<?php

namespace App\Entity;

use App\Repository\FicheMatiereMutualisableRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: FicheMatiereMutualisableRepository::class)]
class FicheMatiereMutualisable
{
    #[Groups(['DTO_json_versioning', 'fiche_matiere_versioning'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ficheMatiereParcours')]
    private ?FicheMatiere $ficheMatiere = null;

    #[Groups('fiche_matiere_versioning')]
    #[ORM\ManyToOne(inversedBy: 'ficheMatiereParcours')]
    private ?Parcours $parcours = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFicheMatiere(): ?FicheMatiere
    {
        return $this->ficheMatiere;
    }

    public function setFicheMatiere(?FicheMatiere $ficheMatiere): self
    {
        $this->ficheMatiere = $ficheMatiere;

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
}
