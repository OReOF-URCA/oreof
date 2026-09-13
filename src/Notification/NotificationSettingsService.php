<?php

namespace App\Notification;

use App\DTO\ResolvedNotificationPreference;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Entity\UserNotificationPreference;
use App\Entity\UserWorkflowNotificationSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

final class NotificationSettingsService
{
    public function __construct(
        private readonly Registry $registry,
        private readonly EntityManagerInterface $em,
        private readonly NotificationPreferenceResolver $resolver,
    ) {
    }

    public function getGlobalPreference(User $user): array
    {
        $pref = $user->getNotificationPreference();

        return [
            'email' => $pref?->isEmailEnabled() ?? true,
            'inapp' => $pref?->isInAppEnabled() ?? true,
        ];
    }

    public function getWorkflowsData(User $user, array $workflowNames = ['dpeParcours']): array
    {
        $out = [];
        foreach ($workflowNames as $wfName) {
            $subject = $this->em->getRepository(DpeParcours::class)->findOneBy([]) ?? new DpeParcours();

            $wf = $this->registry->get($subject, $wfName);
            $def = $wf->getDefinition();

            $transitionsByPlace = [];
            foreach ($def->getTransitions() as $t) {
                $meta = $wf->getMetadataStore()->getTransitionMetadata($t) ?? [];
                foreach ((array)$t->getFroms() as $from) {
                    $transitionsByPlace[$from] ??= [];
                    $transitionsByPlace[$from][] = [
                        'name' => $t->getName(),
                        'label' => $meta['label'] ?? $t->getName(),
                        'resolved' => $this->resolver->resolveFor($user, $wfName, $from, $t->getName()),
                    ];
                }
            }

            $places = [];
            foreach ($def->getPlaces() as $p) {
                $meta = $wf->getMetadataStore()->getPlaceMetadata($p) ?? [];
                if (($meta['process'] ?? false) === true) {
                    $places[] = [
                        'name' => $p,
                        'label' => $meta['label'] ?? $p,
                        'resolved' => $this->resolver->resolveFor($user, $wfName, $p),
                        'transitions' => $transitionsByPlace[$p] ?? [],
                    ];
                }
            }

            $out[] = [
                'name' => $wfName,
                'resolved' => $this->resolver->resolveFor($user, $wfName),
                'places' => $places,
            ];
        }

        return $out;
    }

    public function toggle(
        User $user,
        ?string $workflow,
        ?string $place,
        ?string $transition,
        string $channel,
        bool $enabled
    ): array {
        if ($workflow === null || $workflow === '' || $workflow === '_global') {
            $pref = $user->getNotificationPreference();
            if ($pref === null) {
                $pref = (new UserNotificationPreference())->setUser($user);
                $user->setNotificationPreference($pref);
            }

            if ($channel === 'email') {
                $pref->setEmailEnabled($enabled);
            } elseif ($channel === 'inapp') {
                $pref->setInAppEnabled($enabled);
            }

            $this->em->persist($pref);
            $this->em->flush();

            return [
                'success' => true,
                'message' => 'Préférence globale enregistrée',
                'source' => 'global',
                'channels' => [
                    'email' => $pref->isEmailEnabled(),
                    'inapp' => $pref->isInAppEnabled(),
                ],
            ];
        }

        $cleanPlace = ($place === '' || $place === 'null') ? null : $place;
        $cleanTransition = ($transition === '' || $transition === 'null') ? null : $transition;

        $repo = $this->em->getRepository(UserWorkflowNotificationSetting::class);
        $setting = $repo->findOneBy([
            'user' => $user,
            'workflow' => $workflow,
            'step' => $cleanPlace,
            'transitionName' => $cleanTransition,
        ]);

        if ($setting === null) {
            $resolved = $this->resolver->resolveFor($user, $workflow, $cleanPlace, $cleanTransition)->getChannels();
            $setting = (new UserWorkflowNotificationSetting())
                ->setUser($user)
                ->setWorkflow($workflow)
                ->setStep($cleanPlace)
                ->setTransitionName($cleanTransition)
                ->setEmailEnabled((bool)($resolved['email'] ?? true))
                ->setInAppEnabled((bool)($resolved['inapp'] ?? true));
        }

        if ($channel === 'email') {
            $setting->setEmailEnabled($enabled);
        } elseif ($channel === 'inapp') {
            $setting->setInAppEnabled($enabled);
        }

        $this->em->persist($setting);
        $this->em->flush();

        $expectedSource = $cleanTransition !== null ? 'transition' : ($cleanPlace !== null ? 'step' : 'workflow');

        return [
            'success' => true,
            'message' => 'Préférence enregistrée',
            'source' => $expectedSource,
            'channels' => [
                'email' => $setting->isEmailEnabled(),
                'inapp' => $setting->isInAppEnabled(),
            ],
        ];
    }

    public function reset(
        User $user,
        string $workflow,
        ?string $place,
        ?string $transition
    ): array {
        $cleanPlace = ($place === '' || $place === 'null') ? null : $place;
        $cleanTransition = ($transition === '' || $transition === 'null') ? null : $transition;

        $repo = $this->em->getRepository(UserWorkflowNotificationSetting::class);
        $setting = $repo->findOneBy([
            'user' => $user,
            'workflow' => $workflow,
            'step' => $cleanPlace,
            'transitionName' => $cleanTransition,
        ]);

        if ($setting !== null) {
            $this->em->remove($setting);
            $this->em->flush();
        }

        $resolved = $this->resolver->resolveFor($user, $workflow, $cleanPlace, $cleanTransition);

        return [
            'success' => true,
            'message' => 'Héritage rétabli',
            'source' => $resolved->getSource(),
            'channels' => $resolved->getChannels(),
        ];
    }
}
