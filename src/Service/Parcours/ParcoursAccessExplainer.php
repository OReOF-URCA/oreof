<?php

declare(strict_types=1);

namespace App\Service\Parcours;

use App\Classes\GetDpeParcours;
use App\Entity\Parcours;
use App\Enums\CampagneModuleEnum;
use App\Service\CampagneAccessibilityService;
use DateTimeImmutable;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class ParcoursAccessExplainer
{
    public function __construct(
        private CampagneAccessibilityService $campagneAccessibilityService,
        private Security $security,
    ) {
    }

    /**
     * Analyse précisément la raison pour laquelle un parcours ne peut pas être modifié.
     */
    public function explainEditDenial(Parcours $parcours): string
    {
        // 1. Calendrier / Campagne de saisie
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            $moduleStatus = $this->campagneAccessibilityService->getModuleStatus(CampagneModuleEnum::OFFRE_FORMATION);
            if (!$moduleStatus['canEdit']) {
                return sprintf('La campagne de modification est fermée : %s', $moduleStatus['message']);
            }
        }

        // 2. Statut d'ouverture / fermeture (Reconduction)
        $dpeParcours = GetDpeParcours::getFromParcours($parcours);
        if ($dpeParcours !== null && $dpeParcours->getEtatReconduction()?->isFerme()) {
            return sprintf(
                'Ce parcours est fermé pour cette campagne (%s).',
                $dpeParcours->getEtatReconduction()->getLibelle()
            );
        }

        $formationEtat = $parcours->getFormation()?->getEtatReconduction();
        if ($formationEtat !== null && $formationEtat->isFerme()) {
            return sprintf(
                'La formation est fermée pour cette campagne (%s).',
                $formationEtat->getLibelle()
            );
        }

        // 4. Étape de validation dans le workflow DPE
        if ($dpeParcours !== null) {
            $places = array_keys($dpeParcours->getEtatValidation());
            $currentPlace = $places[0] ?? null;

            $workflowLabels = [
                'initialisation_dpe' => 'Le DPE est en attente d\'initialisation.',
                'autorisation_saisie' => 'La saisie n\'a pas encore été autorisée.',
                'soumis_parcours' => 'Le parcours a été transmis et est en attente de validation par le responsable de formation.',
                'soumis_dpe_composante' => 'Le parcours est en attente de validation par le DPE de la composante.',
                'soumis_conseil' => 'Le parcours est verrouillé car il a été soumis au conseil de la composante.',
                'soumis_central' => 'Le parcours est verrouillé car il a été soumis à la validation centrale (SES).',
                'soumis_cfvu' => 'Le parcours est verrouillé car il a été transmis à la CFVU.',
                'valide_cfvu' => 'Le parcours a été validé par la CFVU et est en attente de publication.',
                'valide_a_publier' => 'Le parcours a été validé et est en attente de publication.',
                'publie' => 'Le parcours est publié. Pour le modifier, veuillez d\'abord demander une réouverture (via le menu « Gestion du DPE »).',
                'tacite_reconduction' => 'Le parcours est en tacite reconduction. Pour le modifier, veuillez d\'abord le réouvrir (via le menu « Gestion du DPE »).',
                'non_ouverture' => 'Le parcours est en non ouverture.',
                'non_ouverture_ses' => 'Le parcours est en attente de validation de non ouverture par le SES.',
            ];

            if ($currentPlace !== null && isset($workflowLabels[$currentPlace])) {
                return sprintf('Le parcours ne peut pas être modifié dans son état actuel : %s', $workflowLabels[$currentPlace]);
            }
        }

        // 5. Droits d'accès de l'utilisateur
        return 'Le parcours est ouvert à la rédaction, mais vous ne disposez pas des droits d\'édition suffisants sur cette formation ou cette composante.';
    }
}
