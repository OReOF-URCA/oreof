<?php

namespace App\Entity;

use App\Repository\PieceJointeAssistanceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pièce jointe d'un message d'assistance, stockée hors du dossier public (var/assistance/).
 */
#[ORM\Entity(repositoryClass: PieceJointeAssistanceRepository::class)]
class PieceJointeAssistance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'piecesJointes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?MessageAssistance $message = null;

    #[ORM\Column(length: 255)]
    private string $nomOriginal = '';

    #[ORM\Column(length: 120)]
    private string $mimeType = 'application/octet-stream';

    #[ORM\Column]
    private int $taille = 0;

    #[ORM\Column(length: 255)]
    private string $chemin = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?MessageAssistance
    {
        return $this->message;
    }

    public function setMessage(?MessageAssistance $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getNomOriginal(): string
    {
        return $this->nomOriginal;
    }

    public function setNomOriginal(string $nomOriginal): static
    {
        $this->nomOriginal = mb_substr($nomOriginal, 0, 255);

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = mb_substr($mimeType, 0, 120);

        return $this;
    }

    public function getTaille(): int
    {
        return $this->taille;
    }

    public function setTaille(int $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getChemin(): string
    {
        return $this->chemin;
    }

    public function setChemin(string $chemin): static
    {
        $this->chemin = $chemin;

        return $this;
    }
}
