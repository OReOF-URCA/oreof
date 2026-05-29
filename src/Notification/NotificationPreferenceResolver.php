<?php

namespace App\Notification;

use App\Entity\User;
use App\Entity\UserWorkflowNotificationSetting;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationPreferenceResolver
{
    private array $channels = [];
    private string $source = '';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function resolveFor(User $user, string $workflow, ?string $transition = null): self
    {
        $pref = $user->getNotificationPreference(); // global
        $effective = [
            'email' => $pref?->isEmailEnabled() ?? true,
            'inapp' => $pref?->isInAppEnabled() ?? true,
        ];
        $source = 'global';

        $repo = $this->em->getRepository(UserWorkflowNotificationSetting::class);

        // transition-level
        if ($transition && $tr = $repo->findOneBy(['user' => $user, 'workflow' => $workflow, 'transitionName' => $transition])) {
            $effective = ['email' => $tr->isEmailEnabled(), 'inapp' => $tr->isInAppEnabled()];
            $source = 'transition';
        }

        $this->channels = $effective;
        $this->source = $source;

        return $this;
    }

    public function channelAllowed(string $channel): bool
    {
        return $this->channels[$channel] ?? false;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }
}

