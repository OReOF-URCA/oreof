<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Twig/Components/NotificationCenterComponent.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 13:18
 */

namespace App\Twig\Components;

use App\Controller\NotificationSettingController;
use Symfony\Component\Workflow\Registry;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Notification\NotificationPreferenceResolver;
use App\Entity\User;


#[AsLiveComponent('notification_center', template: 'components/notification_center.html.twig')]
class NotificationCenterComponent extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $workflow = 'dpeParcours'; // par défaut, mais on liste aussi tous

    public function __construct(
        private Registry                       $registry,
        private EntityManagerInterface         $em,
        private NotificationPreferenceResolver $resolver
    )
    {
    }

    /** liste { name, places:[{name,label}], transitionsByPlace: {place:[{name,label}]} } */
    public function getWorkflows(): array
    {
        // Ici, liste un ou plusieurs workflows : soit connus en config, soit limites à $this->workflow
        $workflows = [$this->workflow];
        $user = $this->getUser();

        $out = [];
        foreach ($workflows as $wfName) {
            // On a besoin d'un "subject" pour récupérer la définition; tu peux garder un stub via Registry
            // Alternative: parser le YAML; ici on suppose que tu peux instancier un sujet exemple:
            $subjectClass = 'App\Entity\DpeParcours'; // adapte si besoin
            $subject = $this->em->getRepository($subjectClass)->findOneBy([]) ?? new $subjectClass(); // fallback

            $wf = $this->registry->get($subject, $wfName);
            $def = $wf->getDefinition();

            $places = [];
            foreach ($def->getPlaces() as $p) {
                $meta = $wf->getMetadataStore()->getPlaceMetadata($p) ?? [];
                if (($meta['process'] ?? false) === true) {
                    $places[] = ['name' => $p, 'label' => $meta['label'] ?? $p];
                }
            }

            $transitionsByPlace = [];
            foreach ($def->getTransitions() as $t) {
                $meta = $wf->getMetadataStore()->getTransitionMetadata($t) ?? [];
                foreach ((array)$t->getFroms() as $from) {
                    $transitionsByPlace[$from] ??= [];
                    $transitionsByPlace[$from][] = ['name' => $t->getName(), 'label' => $meta['label'] ?? $t->getName()];
                }
            }

            $out[] = ['name' => $wfName, 'places' => $places, 'transitionsByPlace' => $transitionsByPlace];
        }
        return $out;
    }

    // Helpers pour la vue (récupère statut effectif + source)
    public function resolve(string $workflow, ?string $place = null, ?string $transition = null): NotificationPreferenceResolver
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->resolver->resolveFor($user, $workflow, $place, $transition);
    }

    #[LiveAction]
    public function saveGlobal(bool $email, bool $inapp): void
    {
        $this->forward(NotificationSettingController::class . '::patchGlobal', [],
            ['email' => $email, 'inapp' => $inapp]);
        $this->emit('notif:saved');
    }

    #[LiveAction]
    public function save(
        #[LiveArg] string  $workflow,
        #[LiveArg] ?string $place,
        #[LiveArg] ?string $transition,
        #[LiveArg] bool    $email,
        #[LiveArg] bool    $inapp): void
    {
        dump('ok');
        $this->forward(NotificationSettingController::class . '::setSettings', ['workflow' => $workflow, 'step' => $place, 'transition' => $transition], ['email' => $email, 'inapp' => $inapp]);
        // $this->emit('notif:saved');
    }

    #[LiveAction]
    public function remove(string $workflow, ?string $place, ?string $transition): void
    {
        $this->forward(NotificationSettingController::class . '::deleteSettings', ['workflow' => $workflow, 'step' => $place, 'transition' => $transition]);
        $this->emit('notif:saved');
    }
}
