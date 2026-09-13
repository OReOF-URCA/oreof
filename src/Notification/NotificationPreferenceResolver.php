<?php

namespace App\Notification;

use App\DTO\ResolvedNotificationPreference;
use App\Entity\User;
use App\Entity\UserWorkflowNotificationSetting;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationPreferenceResolver
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function resolveFor(User $user, string $workflow, ?string $step = null, ?string $transition = null): ResolvedNotificationPreference
    {
        $pref = $user->getNotificationPreference(); // global
        $effective = [
            'email' => $pref?->isEmailEnabled() ?? true,
            'inapp' => $pref?->isInAppEnabled() ?? true,
        ];
        $source = 'global';

        $repo = $this->em->getRepository(UserWorkflowNotificationSetting::class);

        // workflow-level override
        if ($wf = $repo->findOneBy(['user' => $user, 'workflow' => $workflow, 'step' => null, 'transitionName' => null])) {
            $effective = ['email' => $wf->isEmailEnabled(), 'inapp' => $wf->isInAppEnabled()];
            $source = 'workflow';
        }

        // step-level override
        if ($step && $st = $repo->findOneBy(['user' => $user, 'workflow' => $workflow, 'step' => $step, 'transitionName' => null])) {
            $effective = ['email' => $st->isEmailEnabled(), 'inapp' => $st->isInAppEnabled()];
            $source = 'step';
        }

        // transition-level override
        if ($transition) {
            $criteria = ['user' => $user, 'workflow' => $workflow, 'transitionName' => $transition];
            if ($step) {
                $criteria['step'] = $step;
            }
            if ($tr = $repo->findOneBy($criteria)) {
                $effective = ['email' => $tr->isEmailEnabled(), 'inapp' => $tr->isInAppEnabled()];
                $source = 'transition';
            }
        }

        return new ResolvedNotificationPreference(
            channels: $effective,
            source: $source,
            email: (bool)($effective['email'] ?? true),
            inapp: (bool)($effective['inapp'] ?? true),
        );
    }
}
