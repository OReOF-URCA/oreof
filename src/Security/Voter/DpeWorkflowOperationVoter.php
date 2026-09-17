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
