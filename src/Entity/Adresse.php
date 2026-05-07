<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Entity/Adresse.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 22/02/2023 10:33
 */

namespace App\Entity;

use App\Repository\AdresseRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: AdresseRepository::class)]
class Adresse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse1 = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $codePostal = null;

    #[Groups(['parcours_json_versioning', 'formation_json_versioning'])]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\OneToOne(targetEntity: self::class, cascade: ['persist', 'remove'])]
    private ?self $adresseOrigineCopie = null;

    public function __toString(): string
    {
        return $this->display();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAdresse1(): ?string
    {
        return $this->adresse1;
    }

    public function setAdresse1(?string $adresse1): self
    {
        $this->adresse1 = $adresse1;

        return $this;
    }

    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    public function setAdresse2(?string $adresse2): self
    {
        $this->adresse2 = $adresse2;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;

        return $this;
    }

    public function display(): string
    {
        return $this->adresse1 . '<br> ' . $this->adresse2 . '<br> ' . $this->codePostal . ' ' . $this->ville;
    }

    public function getAdresseOrigineCopie(): ?self
    {
        return $this->adresseOrigineCopie;
    }

    public function setAdresseOrigineCopie(?self $adresseOrigineCopie): static
    {
        $this->adresseOrigineCopie = $adresseOrigineCopie;

        return $this;
    }
}
