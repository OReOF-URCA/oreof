<?php

namespace App\Entity;

use App\Repository\UserNotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotificationPreferenceRepository::class)]
class UserNotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'notificationPreference')]
    private ?User $user = null;

    // Canaux activés
    #[ORM\Column(type: 'boolean')]
    private bool $emailEnabled = true;
    #[ORM\Column(type: 'boolean')]
    private bool $inAppEnabled = true;

    public function channelAllowed(string $eventKey, string $channel): bool
    {
        $overrides = $this->perEventOverrides[$eventKey] ?? null;
        if (\is_array($overrides) && \array_key_exists($channel, $overrides)) {
            return (bool)$overrides[$channel];
        }
        return match ($channel) {
            'email' => $this->emailEnabled,
            'inapp' => $this->inAppEnabled,
            default => false,
        };
    }

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
