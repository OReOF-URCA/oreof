<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/TypeEnseignement.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 02/02/2023 08:16
 */

namespace App\Entity;

use App\Repository\NatureUeEcRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NatureUeEcRepository::class)]
class NatureUeEc
{
    public const Nature_EC = 'ec';
    public const Nature_UE = 'ue';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning', 'DTO_json_versioning')]
    #[ORM\Column(length: 100)]
    private ?string $libelle = null;

    #[Groups('parcours_json_versioning', 'DTO_json_versioning')]
    #[ORM\Column]
    private ?bool $choix = false;

    #[Groups(['parcours_json_versioning', 'DTO_json_versioning'])]
    #[ORM\Column]
    private ?bool $libre = false;

    #[Groups('parcours_json_versioning', 'DTO_json_versioning')]
    #[ORM\Column(length: 2)]
    private ?string $type = self::Nature_EC;

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

    public function isChoix(): ?bool
    {
        return $this->choix;
    }

    public function setChoix(bool $choix): self
    {
        $this->choix = $choix;

        return $this;
    }

    public function isLibre(): ?bool
    {
        return $this->libre;
    }

    public function setLibre(bool $libre): self
    {
        $this->libre = $libre;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
}
