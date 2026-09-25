<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UserController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/03/2023 10:15
 */

namespace App\Controller;

use App\Navigation\Breadcrumb\Attribute\Breadcrumb;
use App\Notification\NotificationSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/utilisateur/mes-informations', name: 'app_user_mes_informations')]
    #[Breadcrumb(label: 'menu.mon_compte.mes_informations')]
    public function mesInformations(): Response
    {
        return $this->render('user/mes-informations.html.twig', [
            'profils' => $this->getUser()->getUserProfils(),
        ]);
    }

    #[Route('/utilisateur/mes-notifications', name: 'app_user_mes_notifications')]
    #[Breadcrumb(label: 'menu.mon_compte.mes_notifications')]
    public function mesNotifications(
        NotificationSettingsService $notificationSettingsService
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('user/mes-notifications.html.twig', [
            'global' => $notificationSettingsService->getGlobalPreference($user),
            'workflows' => $notificationSettingsService->getWorkflowsData($user, ['dpeParcours']),
        ]);
    }

    #[Route('/utilisateur/mes-notifications/toggle', name: 'app_user_mes_notifications_toggle', methods: ['POST'])]
    public function mesNotificationsToggle(
        \Symfony\Component\HttpFoundation\Request $request,
        NotificationSettingsService $notificationSettingsService
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?? [];

        $workflow = $payload['workflow'] ?? null;
        $place = $payload['place'] ?? null;
        $transition = $payload['transition'] ?? null;
        $channel = (string)($payload['channel'] ?? 'email');
        $enabled = (bool)($payload['enabled'] ?? true);

        $result = $notificationSettingsService->toggle(
            $user,
            $workflow,
            $place,
            $transition,
            $channel,
            $enabled
        );

        return $this->json($result);
    }

    #[Route('/utilisateur/mes-notifications/reset', name: 'app_user_mes_notifications_reset', methods: ['POST'])]
    public function mesNotificationsReset(
        \Symfony\Component\HttpFoundation\Request $request,
        NotificationSettingsService $notificationSettingsService
    ): \Symfony\Component\HttpFoundation\JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?? [];

        $workflow = (string)($payload['workflow'] ?? 'dpeParcours');
        $place = $payload['place'] ?? null;
        $transition = $payload['transition'] ?? null;

        $result = $notificationSettingsService->reset(
            $user,
            $workflow,
            $place,
            $transition
        );

        return $this->json($result);
    }
}
