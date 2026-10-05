<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\DpeFormation;
use App\Entity\DpeParcours;
use App\Service\Parcours\ParcoursDescriptifAutomatiqueGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\TransitionEvent;

final class OffreCfvuValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ParcoursDescriptifAutomatiqueGenerator $descriptifGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.dpeFormation.transition.valider_cfvu_avec_pv' => 'onDpeFormationCfvuTransition',
            'workflow.dpeFormation.transition.valider_cfvu_attente_pv' => 'onDpeFormationCfvuTransition',
            'workflow.dpeFormation.transition.deposer_pv' => 'onDpeFormationCfvuTransition',
            'workflow.dpeParcours.transition.valider_cfvu' => 'onDpeParcoursCfvuTransition',
            'workflow.dpeParcours.transition.valider_reserve_cfvu' => 'onDpeParcoursCfvuTransition',
            'workflow.dpeParcours.transition.valider_reserve_central_cfvu' => 'onDpeParcoursCfvuTransition',
        ];
    }

    public function onDpeFormationCfvuTransition(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DpeFormation) {
            return;
        }

        $formation = $subject->getFormation();
        $campagne = $subject->getCampagneCollecte();

        if ($formation !== null && $campagne !== null) {
            $this->descriptifGenerator->generateForFormation($formation, $campagne);
        }
    }

    public function onDpeParcoursCfvuTransition(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DpeParcours) {
            return;
        }

        $parcours = $subject->getParcours();
        $campagne = $subject->getCampagneCollecte();

        if ($parcours !== null) {
            $this->descriptifGenerator->generateForParcours($parcours, $campagne, $subject);
        }
    }
}
