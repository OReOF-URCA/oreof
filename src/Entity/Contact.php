<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;

use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 255)]
    private ?string $denomination = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nom = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $prenom = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[Groups('parcours_json_versioning')]
    #[ORM\OneToOne(cascade: ['persist', 'remove'], fetch: 'EAGER')]
    private ?Adresse $adresse = null;

    #[ORM\ManyToOne(inversedBy: 'contacts')]
    private ?Parcours $parcours = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDenomination(): ?string
    {
        return $this->denomination;
    }

    public function setDenomination(string $denomination): static
    {
        $this->denomination = $denomination;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAdresse(): ?Adresse
    {
        return $this->adresse;
    }

    public function setAdresse(?Adresse $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getParcours(): ?Parcours
    {
        return $this->parcours;
    }

    public function setParcours(?Parcours $parcours): static
    {
        $this->parcours = $parcours;

        return $this;
    }

    public function getDisplay(): string
    {
        $nom = $this->getNom() ?? '';
        $prenom = $this->getPrenom() ?? '';
        return mb_strtoupper($nom) . ' ' . ucwords(mb_strtolower($prenom));
    }
}
