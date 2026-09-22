<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\FicheMatiere;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class FicheWorkflowOperationVoter extends Voter
{
    public const APPLY = 'FICHE_WORKFLOW_TRANSITION';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::APPLY === $attribute
            && $subject instanceof OperationAuthorizationSubject
            && $subject->subject instanceof FicheMatiere
            && 'fiche' === $subject->operation->workflowName;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        return in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}
