<?php

namespace App\Entity;

use App\Repository\UserCategoryNotificationSettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCategoryNotificationSettingRepository::class)]
class UserCategoryNotificationSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: "boolean")]
    private bool $emailEnabled = true;
    #[ORM\Column(type: "boolean")]
    private bool $inAppEnabled = true;
    #[ORM\Column(length: 20)]
    private string $frequency = 'immediate'; // daily_digest, weekly_digest...
    // (optionnel) seuil de priorité min: critical|important|normal|low
    #[ORM\Column(length: 20)]
    private string $minSeverity = 'normal';

    #[ORM\ManyToOne(inversedBy: 'userCategoryNotificationSettings')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCategoryNotificationSettings')]
    private ?NotificationCategory $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isEmailEnabled(): ?bool
    {
        return $this->emailEnabled;
    }

    public function setEmailEnabled(bool $emailEnabled): static
    {
        $this->emailEnabled = $emailEnabled;

        return $this;
    }

    public function isInAppEnabled(): ?bool
    {
        return $this->inAppEnabled;
    }

    public function setInAppEnabled(bool $inAppEnabled): static
    {
        $this->inAppEnabled = $inAppEnabled;

        return $this;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getMinSeverity(): ?string
    {
        return $this->minSeverity;
    }

    public function setMinSeverity(string $minSeverity): static
    {
        $this->minSeverity = $minSeverity;

        return $this;
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

    public function getCategory(): ?NotificationCategory
    {
        return $this->category;
    }

    public function setCategory(?NotificationCategory $category): static
    {
        $this->category = $category;

        return $this;
    }
}
