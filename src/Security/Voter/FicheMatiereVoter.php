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

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof FicheMatiere;
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

        if (!$subject->isModifiable()) {
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

        return $this->security->isGranted('EDIT', ['route' => 'app_fiche_matiere', 'subject' => $subject]);
    }
}
