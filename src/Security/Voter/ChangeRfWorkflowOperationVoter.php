<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ChangeRf;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ChangeRfWorkflowOperationVoter extends Voter
{
    public const APPLY = 'CHANGE_RF_WORKFLOW_TRANSITION';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::APPLY === $attribute
            && $subject instanceof OperationAuthorizationSubject
            && $subject->subject instanceof ChangeRf
            && 'changeRf' === $subject->operation->workflowName;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        \assert($subject instanceof OperationAuthorizationSubject);
        \assert($subject->subject instanceof ChangeRf);

        if (
            in_array('ROLE_ADMIN', $token->getRoleNames(), true)
            || $this->security->isGranted('ROLE_ADMIN')
        ) {
            return true;
        }

        // These stages are currently handled centrally in ORéOF.
        if ([] !== array_intersect($subject->operation->fromPlaces, ['soumis_ses', 'soumis_cfvu'])) {
            return false;
        }

        $formation = $subject->subject->getFormation();
        if (null === $formation) {
            return false;
        }

        return $this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $formation,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_formation',
            'subject' => $formation,
        ]);
    }
}
