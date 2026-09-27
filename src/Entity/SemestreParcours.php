<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/SemestreParcours.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 21:21
 */

namespace App\Entity;

use App\Entity\Traits\HasBeenEditedTrait;
use App\Repository\SemestreParcoursRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: SemestreParcoursRepository::class)]
class SemestreParcours
{
    use HasBeenEditedTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\ManyToOne(cascade: ['persist'], fetch: 'EAGER', inversedBy: 'semestreParcours')]
    private ?Semestre $semestre = null;

    #[ORM\ManyToOne(inversedBy: 'semestreParcours')]
    private ?Parcours $parcours = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column]
    private ?int $ordre = 0;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column]
    private ?bool $porteur = false;

//    #[ORM\ManyToOne(inversedBy: 'semestreParcours')]
//    private ?SemestreMutualisable $semestreRaccroche = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogeeEtapeAnnee = null; //sera identique sur deux semestres de la même année

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $codeApogeeEtapeVersion = null; //sera identique sur deux semestres de la même année

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeApogeeDiplome = null; //sera identique sur plusieurs semestres, mais peut changer selon la présence d'un tronc commun ou pas

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $codeApogeeVersionDiplome = null;

    #[ORM\Column]
    #[Groups('parcours_json_versioning')]
    private ?bool $isOuvert = true;

    #[ORM\ManyToOne(inversedBy: 'parcoursSemestre')]
    private ?Annee $annee = null;

    public function __construct(?Semestre $semestre, ?Parcours $parcours)
    {
        $this->setSemestre($semestre);
        $this->setParcours($parcours);
        if ($semestre?->getOrdre()) {
            $this->setOrdre($semestre->getOrdre());
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSemestre(): ?Semestre
    {
        return $this->semestre;
    }

    public function setSemestre(?Semestre $semestre): self
    {
        $this->semestre = $semestre;

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

    public function getOrdre(): ?int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): self
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function isPorteur(): ?bool
    {
        return $this->porteur;
    }

    public function setPorteur(bool $porteur): self
    {
        $this->porteur = $porteur;

        return $this;
    }

//    public function getSemestreRaccroche(): ?SemestreMutualisable
//    {
//        return $this->semestreRaccroche;
//    }
//
//    public function setSemestreRaccroche(?SemestreMutualisable $semestreRaccroche): self
//    {
//        $this->semestreRaccroche = $semestreRaccroche;
//
//        return $this;
//    }

    public function getOrdreAnnee(): int
    {
        //si ordre = 1 ou 2 alors année = 1
        //si ordre = 3 ou 4 alors année = 2
        //si ordre = 5 ou 6 alors année = 3

        return match ($this->ordre) {
            1, 2 => 1,
            3, 4 => 2,
            5, 6 => 3,
            default => 0,
        };
    }

//    public function getOrdreAnnee(): int
//    {
//        if ($this->ordre % 2 === 0) {
//            return 2;
//        }
//        return 1;
//    }

    public function display(): string
    {
        return 'S' . $this->getOrdre();
    }

    public function getCodeApogeeEtapeAnnee(): ?string
    {
        return $this->codeApogeeEtapeAnnee;
    }

    public function setCodeApogeeEtapeAnnee(?string $codeApogeeEtapeAnnee): static
    {
        $this->codeApogeeEtapeAnnee = $codeApogeeEtapeAnnee;

        return $this;
    }

    public function getCodeApogeeEtapeVersion(): ?string
    {
        return $this->codeApogeeEtapeVersion;
    }

    public function setCodeApogeeEtapeVersion(?string $codeApogeeEtapeVersion): static
    {
        $this->codeApogeeEtapeVersion = $codeApogeeEtapeVersion;

        return $this;
    }

    public function getCodeApogeeDiplome(): ?string
    {
        return $this->codeApogeeDiplome;
    }

    public function setCodeApogeeDiplome(?string $codeApogeeDiplome): static
    {
        $this->codeApogeeDiplome = $codeApogeeDiplome;

        return $this;
    }

    public function getCodeApogeeVersionDiplome(): ?string
    {
        return $this->codeApogeeVersionDiplome;
    }

    public function setCodeApogeeVersionDiplome(?string $codeApogeeVersionDiplome): static
    {
        $this->codeApogeeVersionDiplome = $codeApogeeVersionDiplome;

        return $this;
    }

    public function isOuvert(): ?bool
    {
        return $this->isOuvert ?? true;
    }

    public function setOuvert(bool $isOuvert): static
    {
        $this->isOuvert = $isOuvert;

        return $this;
    }

    public function setAnnee(?Annee $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getAnnee(): ?Annee
    {
        return $this->annee;
    }

}
