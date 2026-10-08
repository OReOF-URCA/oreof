<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\FicheMatiere;
use App\Entity\User;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class FicheWorkflowOperationVoter extends Voter
{
    public const APPLY = 'FICHE_WORKFLOW_TRANSITION';

    public function __construct(
        private readonly Security $security,
    ) {
    }

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
        \assert($subject instanceof OperationAuthorizationSubject);
        \assert($subject->subject instanceof FicheMatiere);

        if (
            in_array('ROLE_ADMIN', $token->getRoleNames(), true)
            || $this->security->isGranted('ROLE_ADMIN')
        ) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $transitionName = $subject->operation->transitionName;
        $ficheMatiere = $subject->subject;

        // Transitions SES (validation centrale et publication)
        // Accessibles uniquement par les gestionnaires centraux (SES / ROLE_ADMIN / droit MANAGE sur etablissement)
        if (in_array($transitionName, ['valider_fiche_ses', 'reserver_fiche_ses', 'publier'], true)) {
            return $this->security->isGranted('MANAGE', [
                'route' => 'app_etablissement',
                'subject' => 'etablissement',
            ]);
        }

        // Transitions de validation composante et de réouverture :
        // valider_fiche_compo, rouvrir_fiche_matiere, rouvrir_fiche_matiere_b
        // Accessibles par :
        // - Le responsable de la fiche
        // - Le responsable ou co-responsable de parcours (RP)
        // - Le responsable ou co-responsable de mention/formation (RF)
        // - Le responsable DPE ou gestionnaire DPE de la composante porteuse
        if ($ficheMatiere->getResponsableFicheMatiere()?->getId() === $user->getId()) {
            return true;
        }

        $parcours = $ficheMatiere->getParcours();
        if ($parcours !== null) {
            // RP
            if (
                $parcours->getRespParcours()?->getId() === $user->getId()
                || $parcours->getCoResponsable()?->getId() === $user->getId()
            ) {
                return true;
            }

            $formation = $parcours->getFormation();
            if ($formation !== null) {
                // RF
                if (
                    $formation->getResponsableMention()?->getId() === $user->getId()
                    || $formation->getCoResponsable()?->getId() === $user->getId()
                ) {
                    return true;
                }

                // Composante
                $composante = $formation->getComposantePorteuse();
                if ($composante !== null && (
                    $composante->getResponsableDpe()?->getId() === $user->getId()
                    || $user->getComposanteResponsableDpe()->contains($composante)
                )) {
                    return true;
                }
            }
        }

        return $this->security->isGranted('MANAGE', [
            'route' => 'app_composante',
            'subject' => $ficheMatiere,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_formation',
            'subject' => $ficheMatiere,
        ]) || $this->security->isGranted('MANAGE', [
            'route' => 'app_parcours',
            'subject' => $ficheMatiere,
        ]) || $this->security->isGranted('EDIT', [
            'route' => 'app_fiche_matiere',
            'subject' => $ficheMatiere,
        ]);
    }
}
