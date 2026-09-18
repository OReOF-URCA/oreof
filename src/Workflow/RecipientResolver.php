<?php
namespace App\Workflow;

use App\DTO\WorkFlowData;
use App\Enums\CentreGestionEnum;
use App\Repository\NotificationListeRepository;
use App\Repository\UserProfilRepository;

class RecipientResolver
{
    public function __construct(
        private readonly UserProfilRepository        $userProfilRepository,
        private readonly NotificationListeRepository $notificationListeRepository
    )
    {
    }

    public function resolveRecipients(string $workflowName, string $transitionName, WorkFlowData $workFlowData): array
    {
        $recipients = [];
        $copies = [];

        // je récupère les profils associés au workflow
        $profils = $this->notificationListeRepository->findBy(['workflow' => $workflowName, 'step' => $transitionName]);
        // en fonction du centre des profils, on récupère les destinataires sur le bon parcours, formation, composante, ...
        foreach ($profils as $profil) {
            switch ($profil->getProfil()?->getCentre()) {
                case CentreGestionEnum::CENTRE_GESTION_COMPOSANTE:
                    $users = $this->userProfilRepository->findBy(['composante' => $workFlowData->composante, 'profil' => $profil->getProfil()]);
                    foreach ($users as $user) {
                        $recipients[] = $user->getUser();
                    }
                    break;
                case CentreGestionEnum::CENTRE_GESTION_FORMATION:
                    $users = $this->userProfilRepository->findBy(['formation' => $workFlowData->formation, 'profil' => $profil->getProfil()]);
                    foreach ($users as $user) {
                        $recipients[] = $user->getUser();
                    }
                    break;
                case CentreGestionEnum::CENTRE_GESTION_PARCOURS:
                    $users = $this->userProfilRepository->findBy(['parcours' => $workFlowData->parcours, 'profil' => $profil->getProfil()]);
                    foreach ($users as $user) {
                        $recipients[] = $user->getUser();
                    }
                    break;
                case CentreGestionEnum::CENTRE_GESTION_ETABLISSEMENT:
                    $users = $this->userProfilRepository->findBy(['etablissement' => 1, 'profil' => $profil->getProfil()]);
                    foreach ($users as $user) {
                        $recipients[] = $user->getUser();
                    }
                    break;
            }
        }

        //        foreach ($metadata['recipients'] ?? [] as $role) {
        //            switch ($role) {
        //                case 'RF':
        //                    if (($resp = $workFlowData->formation?->getResponsableMention()) !== null) {
        //                        $this->em->initializeObject($resp);
        //                        $recipients[] = $resp;
        //                    }
        //                    break;
        //                case 'CoRF':
        //                    if (($resp = $workFlowData->formation?->getCoResponsable()) !== null) {
        //                        $this->em->initializeObject($resp);
        //                        $recipients[] = $resp;
        //                    }
        //                    break;
        //                case 'RP':
        //                    if (($resp = $workFlowData->parcours?->getRespParcours()) !== null) {
        //                        $this->em->initializeObject($resp);
        //                        $recipients[] = $resp;
        //                    }
        //                    break;
        //                case 'CoRP':
        //                    if (($resp = $workFlowData->parcours?->getCoResponsable()) !== null) {
        //                        $this->em->initializeObject($resp);
        //                        $recipients[] = $resp;
        //                    }
        //                    break;
        //                case 'DPE':
        //                    if (($resp = $workFlowData->composante?->getResponsableDpe()) !== null) {
        //                        $this->em->initializeObject($resp);
        //                        $recipients[] = $resp;
        //                    }
        //                    break;
        //            }
        //        }
        $copies = [];
//        foreach ($metadata['recipientsCopy'] ?? [] as $copy) {
//            if ($copy === 'SES') {
//                $copies[] = WorkFlowData::EMAIL_OREOF;
//            }
//            if ($copy === 'CFVU') {
//                $copies[] = WorkFlowData::EMAIL_CFVU;
//            }
//        }

        $recipients = array_values(array_unique($recipients, SORT_REGULAR));
        $copies = array_values(array_unique($copies, SORT_REGULAR));

        return ['recipients' => $recipients, 'copies' => $copies];
    }
}
