<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\DpeParcours;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

/**
 * Bridges workflow operation authorization to ORéOF scoped permissions.
 *
 * Transition-specific rules stay in ORéOF; the generic bundle only invokes
 * the voter declared in workflow metadata.
 */
final class DpeWorkflowOperationVoter extends Voter
{
    public const APPLY = 'DPE_WORKFLOW_TRANSITION';

    /** Transitions réservées au SES (validation centrale, CFVU, publication). */
    private const TRANSITIONS_SES = [
        'valider_central',
        'reserver_central',
        'valider_publication_directe',
        'refuser_revoir_cfvu',
        'valider_reserve_cfvu',
        'valider_reserve_conseil_cfvu',
        'valider_reserve_central_cfvu',
        'valider_cfvu',
        'valider_publication',
        'publier',
        'valider_transmettre_ouverture_sans_cfvu',
        'reserver_transmettre_ouverture_sans_cfvu',
        'valider_transmettre_reserve_cfvu',
        'reserver_transmettre_reserve_cfvu',
        'valider_non_ouverture_ses',
    ];

    /** Transitions réservées à la composante (DPE) : validation composante et conseil. */
    private const TRANSITIONS_COMPOSANTE = [
        'valider_dpe_composante',
        'reserver_dpe_composante',
        'valider_conseil',
        'reserver_conseil',
        'transmetre_reserve_cfvu',
    ];

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::APPLY === $attribute
            && $subject instanceof OperationAuthorizationSubject
            && $subject->subject instanceof DpeParcours
            && 'dpeParcours' === $subject->operation->workflowName;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        \assert($subject instanceof OperationAuthorizationSubject);
        \assert($subject->subject instanceof DpeParcours);

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        $dpeParcours = $subject->subject;
        $transition = $subject->operation->transitionName;

        // Étapes SES / CFVU / publication : gestionnaires de l'établissement uniquement.
        if (in_array($transition, self::TRANSITIONS_SES, true)) {
            return $this->security->isGranted('MANAGE', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ]);
        }

        // Étapes composante (DPE) et conseil : droit MANAGE sur la composante uniquement.
        if (in_array($transition, self::TRANSITIONS_COMPOSANTE, true)) {
            return $this->security->isGranted('MANAGE', [
                'route' => 'app_composante',
                'subject' => $dpeParcours,
            ]);
        }

        return $this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $dpeParcours,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_formation',
            'subject' => $dpeParcours,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_parcours',
            'subject' => $dpeParcours,
        ]);
    }
}
