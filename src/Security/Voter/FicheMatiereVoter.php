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

        if ($subject->getResponsableFicheMatiere()?->getId() === $user->getId()) {
            return true;
        }

        $parcours = $subject->getParcours();
        if ($parcours !== null) {
            if ($parcours->getRespParcours()?->getId() === $user->getId()
                || $parcours->getCoResponsable()?->getId() === $user->getId()
            ) {
                return true;
            }

            $formation = $parcours->getFormation();
            if ($formation !== null) {
                if ($formation->getResponsableMention()?->getId() === $user->getId()
                    || $formation->getCoResponsable()?->getId() === $user->getId()
                ) {
                    return true;
                }

                $composante = $formation->getComposantePorteuse();
                if ($composante !== null && (
                    $composante->getResponsableDpe()?->getId() === $user->getId()
                    || $user->getComposanteResponsableDpe()->contains($composante)
                )) {
                    return true;
                }
            }
        }

        $permissionToCheck = $isView ? 'SHOW' : 'EDIT';
        return $this->security->isGranted($permissionToCheck, ['route' => 'app_fiche_matiere', 'subject' => $subject]);
    }
}
