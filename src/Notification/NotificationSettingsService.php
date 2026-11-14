<?php

namespace App\Notification;

use App\DTO\ResolvedNotificationPreference;
use App\Entity\ChangeRf;
use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Entity\FicheMatiere;
use App\Entity\User;
use App\Entity\UserNotificationPreference;
use App\Entity\UserWorkflowNotificationSetting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Transition;
use Symfony\Contracts\Translation\TranslatorInterface;

final class NotificationSettingsService
{
    public const WORKFLOW_CONFIG = [
        'dpeParcours' => [
            'label' => 'Parcours DPE',
            'entity' => DpeParcours::class,
            'icon' => 'ph:tree-structure',
            'description' => 'Validation des maquettes et parcours de formation (DPE).',
        ],
        'fiche' => [
            'label' => 'Fiches matières',
            'entity' => FicheMatiere::class,
            'icon' => 'ph:list-checks-fill',
            'description' => 'Validation des fiches matières et éléments constitutifs.',
        ],
        'changeRf' => [
            'label' => 'Changement de RF',
            'entity' => ChangeRf::class,
            'icon' => 'mdi:people-check',
            'description' => 'Demandes de changement de responsable de formation.',
        ],
        'dpeFormation' => [
            'label' => 'Formation DPE',
            'entity' => DpeFormation::class,
            'icon' => 'flowbite:school-check-alt-outline',
            'description' => 'Validation globale de la formation.',
        ],
    ];

    public function __construct(
        private readonly Registry $registry,
        private readonly EntityManagerInterface $em,
        private readonly NotificationPreferenceResolver $resolver,
        private readonly ?TranslatorInterface $translator = null,
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

    public function getWorkflowsData(User $user, array $workflowNames = ['dpeParcours', 'fiche', 'changeRf', 'dpeFormation']): array
    {
        $out = [];
        foreach ($workflowNames as $wfName) {
            $subject = $this->createSubjectForWorkflow($wfName);

            try {
                $wf = $this->registry->get($subject, $wfName);
            } catch (\Throwable) {
                continue;
            }

            $def = $wf->getDefinition();

            $transitionsByPlace = [];
            foreach ($def->getTransitions() as $t) {
                $meta = $wf->getMetadataStore()->getTransitionMetadata($t);
                $transitionLabel = $this->resolveTransitionLabel($wfName, $t, $meta);

                foreach ((array)$t->getFroms() as $from) {
                    $transitionsByPlace[$from] ??= [];
                    $transitionsByPlace[$from][] = [
                        'name' => $t->getName(),
                        'label' => $transitionLabel,
                        'resolved' => $this->resolver->resolveFor($user, $wfName, $from, $t->getName()),
                    ];
                }
            }

            $places = [];
            foreach ($def->getPlaces() as $p) {
                $meta = $wf->getMetadataStore()->getPlaceMetadata($p);
                if (($meta['process'] ?? false) === true) {
                    $places[] = [
                        'name' => $p,
                        'label' => $this->resolvePlaceLabel($p, $meta),
                        'resolved' => $this->resolver->resolveFor($user, $wfName, $p),
                        'transitions' => $transitionsByPlace[$p] ?? [],
                    ];
                }
            }

            $out[] = [
                'name' => $wfName,
                'label' => self::WORKFLOW_CONFIG[$wfName]['label'] ?? $wfName,
                'icon' => self::WORKFLOW_CONFIG[$wfName]['icon'] ?? 'ph:tree-structure',
                'description' => self::WORKFLOW_CONFIG[$wfName]['description'] ?? '',
                'resolved' => $this->resolver->resolveFor($user, $wfName),
                'places' => $places,
            ];
        }

        return $out;
    }

    private function createSubjectForWorkflow(string $wfName): object
    {
        $class = self::WORKFLOW_CONFIG[$wfName]['entity'] ?? DpeParcours::class;
        try {
            return $this->em->getRepository($class)->findOneBy([]) ?? new $class();
        } catch (\Throwable) {
            return new $class();
        }
    }

    private function resolveTransitionLabel(string $wfName, Transition $t, array $meta): string
    {
        $name = $t->getName();

        // 1) Explicit label in transition metadata (e.g. from workflow.yaml)
        if (!empty($meta['label'])) {
            $label = (string)$meta['label'];
            if ($this->translator !== null) {
                $translated = $this->translator->trans($label, [], 'process');
                if ($translated !== $label) {
                    return $translated;
                }
                $translated = $this->translator->trans($label);
                if ($translated !== $label) {
                    return $translated;
                }
            }
            return $label;
        }

        // 2) Try workflow.<transition>.menu.label in domain 'process'
        if ($this->translator !== null) {
            $key = 'workflow.' . $name . '.menu.label';
            $translated = $this->translator->trans($key, [], 'process');
            if ($translated !== $key) {
                return $translated;
            }

            $btnKey = 'btn.validation.parcours.' . $name;
            $transBtn = $this->translator->trans($btnKey, [], 'process');
            if ($transBtn !== $btnKey) {
                return $transBtn;
            }
        }

        // 3) Try titre in transition metadata
        if (!empty($meta['titre'])) {
            $titre = (string)$meta['titre'];
            $clean = trim(str_replace(['%%s', '%s'], '', $titre));
            if ($clean !== '') {
                return $clean;
            }
        }

        // 4) Try domain 'notif': notif.<wf>.<transition>
        if ($this->translator !== null) {
            $notifKey = 'notif.' . $wfName . '.' . $name;
            $transNotif = $this->translator->trans($notifKey, [], 'notif');
            if ($transNotif !== $notifKey) {
                return $transNotif;
            }
        }

        // 5) Fallback to clean human name from snake_case
        return ucfirst(str_replace('_', ' ', $name));
    }

    private function resolvePlaceLabel(string $p, array $meta): string
    {
        $label = $meta['label'] ?? $p;
        if ($this->translator !== null && !empty($meta['label'])) {
            $translated = $this->translator->trans($meta['label'], [], 'process');
            if ($translated === $meta['label']) {
                $translated = $this->translator->trans($meta['label']);
            }
            return $translated;
        }

        return $label;
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
