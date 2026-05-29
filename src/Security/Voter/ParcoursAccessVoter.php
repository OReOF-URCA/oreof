<?php

namespace App\Security\Voter;

use App\Entity\Formation;
use App\Entity\Parcours;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\User\UserInterface;

final class ParcoursAccessVoter extends Voter
{
    public const RELATED_TO_PARCOURS = 'RELATED_TO_PARCOURS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::RELATED_TO_PARCOURS])
            && ($subject instanceof Parcours || $subject instanceof Formation);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var $user User */
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        // Si c'est un administrateur, on autorise directement
        if(in_array('ROLE_ADMIN', $user->getRoles(), true)){
            return true;
        }

        switch ($attribute) {
            case self::RELATED_TO_PARCOURS:
                return $this->isUserLinkedToParcours($subject, $user->getId());
                break;
        }

        return false;
    }

    private function isUserLinkedToParcours(mixed $subject, int $userId) {
        $responsablesId = [];
        if ($subject instanceof Parcours){
            $responsablesId[] = $subject->getRespParcours()?->getId() ?? null;
            $responsablesId[] = $subject->getCoResponsable()?->getId() ?? null;
            $responsablesId[] = $subject->getFormation()?->getResponsableMention()?->getId() ?? null;
            $responsablesId[] = $subject->getFormation()?->getCoResponsable()?->getId() ?? null;
            $responsablesId[] = $subject->getFormation()?->getComposantePorteuse()?->getResponsableDpe()?->getId() ?? null;
        }
        elseif ($subject instanceof Formation) {
            $responsablesId[] = $subject->getResponsableMention()?->getId() ?? null;
            $responsablesId[] = $subject->getCoResponsable()?->getId() ?? null;
            $responsablesId[] = $subject->getComposantePorteuse()?->getResponsableDpe()?->getId() ?? null;
            foreach($subject->getParcours() as $p) {
                $responsablesId[] = $p->getRespParcours()?->getId() ?? null;
                $responsablesId[] = $p->getCoResponsable()?->getId() ?? null;
            }
        }

        return in_array($userId, $responsablesId, true);
    }
}
