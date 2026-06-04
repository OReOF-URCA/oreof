<?php

namespace App\Entity;

use App\Repository\UserWorkflowNotificationSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserWorkflowNotificationSettingRepository::class)]
class UserWorkflowNotificationSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $workflow;
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $step = null;            // place
    #[ORM\Column(length: 150, nullable: true, name: 'transition_name')]
    private ?string $transitionName = null;

    #[ORM\Column(type: 'boolean')]
    private bool $emailEnabled = true;
    #[ORM\Column(type: 'boolean')]
    private bool $inAppEnabled = true;
    #[ORM\ManyToOne(inversedBy: 'userWorkflowNotificationSettings')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getWorkflow(): string
    {
        return $this->workflow;
    }

    public function setWorkflow(string $workflow): void
    {
        $this->workflow = $workflow;
    }

    public function getStep(): ?string
    {
        return $this->step;
    }

    public function setStep(?string $step): void
    {
        $this->step = $step;
    }

    public function getTransitionName(): ?string
    {
        return $this->transitionName;
    }

    public function setTransitionName(?string $transitionName): void
    {
        $this->transitionName = $transitionName;
    }

    public function isEmailEnabled(): bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): void
    {
        $this->emailEnabled = $emailEnabled;
    }

    public function isInAppEnabled(): bool
    {
        return $this->inAppEnabled;
    }

    public function setInAppEnabled(bool $inAppEnabled): void
    {
        $this->inAppEnabled = $inAppEnabled;
    }
}
