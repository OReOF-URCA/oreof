<?php

namespace App\Entity;

use App\Repository\FicheMatiereTabStateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FicheMatiereTabStateRepository::class)]
#[ORM\Table(name: 'fiche_matiere_tab_state', uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_FMTAB', columns: ['fiche_matiere_id', 'tab_key'])])]
class FicheMatiereTabState
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ficheMatiereTabStates')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FicheMatiere $ficheMatiere = null;

    #[ORM\Column(length: 30)]
    private ?string $tabKey = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $done = false;

    #[ORM\Column(length: 10, options: ['default' => 'red'])]
    private ?string $status = 'red';

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?array $issues = null;

    public function __construct(FicheMatiere $ficheMatiere, string $tabKey)
    {
        $this->ficheMatiere = $ficheMatiere;
        $this->tabKey = $tabKey;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFicheMatiere(): ?FicheMatiere
    {
        return $this->ficheMatiere;
    }

    public function setFicheMatiere(?FicheMatiere $ficheMatiere): static
    {
        $this->ficheMatiere = $ficheMatiere;

        return $this;
    }

    public function getTabKey(): ?string
    {
        return $this->tabKey;
    }

    public function setTabKey(string $tabKey): static
    {
        $this->tabKey = $tabKey;

        return $this;
    }

    public function isDone(): ?bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getIssues(): ?array
    {
        return $this->issues ?? [];
    }

    public function setIssues(?array $issues): static
    {
        $this->issues = $issues;

        return $this;
    }
}
