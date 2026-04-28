<?php

namespace App\Entity;

use App\Repository\WorkflowRoleNotificationConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkflowRoleNotificationConfigRepository::class)]
class WorkflowRoleNotificationConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $workflowName = null;

    #[ORM\Column(length: 255)]
    private ?string $placeName = null;

    #[ORM\Column]
    private array $destinataires = [];

    #[ORM\Column]
    private array $copies = [];

    public function getCacheTag(): string
    {
        return sprintf('notif_cfg:%s:%s', $this->workflowName, $this->placeName);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkflowName(): ?string
    {
        return $this->workflowName;
    }

    public function setWorkflowName(string $workflowName): static
    {
        $this->workflowName = $workflowName;

        return $this;
    }

    public function getPlaceName(): ?string
    {
        return $this->placeName;
    }

    public function setPlaceName(string $placeName): static
    {
        $this->placeName = $placeName;

        return $this;
    }

    public function getDestinataires(): array
    {
        return $this->destinataires ?? [];
    }

    public function setDestinataires(array $destinataires): static
    {
        $this->destinataires = $destinataires;

        return $this;
    }

    public function getCopies(): array
    {
        return $this->copies ?? [];
    }

    public function setCopies(array $copies): static
    {
        $this->copies = $copies;

        return $this;
    }
}
