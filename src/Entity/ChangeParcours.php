<?php

namespace App\Entity;

use App\Enums\ParcoursActionStatusEnum;
use App\Enums\ParcoursActionTypeEnum;
use App\Repository\ChangeParcoursRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChangeParcoursRepository::class)]
class ChangeParcours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'changeParcours')]
    private ?Parcours $parcours = null;

    #[ORM\ManyToOne(inversedBy: 'changeParcours')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(inversedBy: 'changeParcours')]
    private ?User $auteur = null;

    #[ORM\Column]
    private ?\DateTime $dateDemande = null;

    #[ORM\Column]
    private array $payload = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decisionReason = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateApprouved = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, enumType: ParcoursActionTypeEnum::class)]
    private array $actionType = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, enumType: ParcoursActionStatusEnum::class)]
    private array $actionStatus = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getDateDemande(): ?\DateTime
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTime $dateDemande): static
    {
        $this->dateDemande = $dateDemande;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getDecisionReason(): ?string
    {
        return $this->decisionReason;
    }

    public function setDecisionReason(?string $decisionReason): static
    {
        $this->decisionReason = $decisionReason;

        return $this;
    }

    public function getDateApprouved(): ?\DateTime
    {
        return $this->dateApprouved;
    }

    public function setDateApprouved(?\DateTime $dateApprouved): static
    {
        $this->dateApprouved = $dateApprouved;

        return $this;
    }

    /**
     * @return ParcoursActionTypeEnum[]
     */
    public function getActionType(): array
    {
        return $this->actionType;
    }

    public function setActionType(array $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    /**
     * @return ParcoursActionStatusEnum[]
     */
    public function getActionStatus(): array
    {
        return $this->actionStatus;
    }

    public function setActionStatus(array $actionStatus): static
    {
        $this->actionStatus = $actionStatus;

        return $this;
    }
}
