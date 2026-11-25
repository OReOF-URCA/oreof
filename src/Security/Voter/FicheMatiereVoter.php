<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\FicheMatiere;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FicheMatiereVoter extends Voter
{
    public const EDIT = 'EDIT_FICHE_MATIERE';
    public const VIEW = 'VIEW_FICHE_MATIERE';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW, 'EDIT', 'SHOW', 'VIEW'], true) && $subject instanceof FicheMatiere;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        if (!$subject instanceof FicheMatiere) {
            return false;
        }

        $isView = in_array($attribute, [self::VIEW, 'SHOW', 'VIEW'], true);

        // Seule la modification nécessite que la fiche soit modifiable
        if (!$isView && !$subject->isModifiable()) {
            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Tout utilisateur connecté peut consulter la fiche matière (lecture seule)
        if ($isView) {
            return true;
        }

        $userId = $user->getId();
        if ($userId !== null && $subject->getResponsableFicheMatiere()?->getId() === $userId) {
            return true;
        }

        $parcours = $subject->getParcours();
        if ($parcours !== null && $userId !== null) {
            if ($parcours->getRespParcours()?->getId() === $userId
                || $parcours->getCoResponsable()?->getId() === $userId
            ) {
                return true;
            }

            $formation = $parcours->getFormation();
            if ($formation !== null) {
                if ($formation->getResponsableMention()?->getId() === $userId
                    || $formation->getCoResponsable()?->getId() === $userId
                ) {
                    return true;
                }
            }
        }

        return false;
    }
}
