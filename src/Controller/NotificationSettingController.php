<?php

namespace App\Controller;

use App\Entity\UserNotificationPreference;
use App\Entity\UserWorkflowNotificationSetting;
use App\Notification\NotificationPreferenceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notification-settings')]
class NotificationSettingController extends BaseController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    // GET effectif + source
    #[Route('/{workflow}/{step?}/{transition?}', name: 'notif_settings_get', methods: ['GET'])]
    public function getSettings(string $workflow, ?string $step, ?string $transition, NotificationPreferenceResolver $resolver): JsonResponse
    {
        $user = $this->getUser();
        $data = $resolver->resolveFor($user, $workflow, $step ?: null, $transition ?: null);
        return $this->json($data);
    }

    // POST override (crée/maj)
    #[Route('/{workflow}/{step?}/{transition?}', name: 'notif_settings_set', methods: ['POST'])]
    public function setSettings(string $workflow, ?string $step, ?string $transition, Request $req): JsonResponse
    {
        $user = $this->getUser();
        $repo = $this->entityManager->getRepository(UserWorkflowNotificationSetting::class);

        $setting = $repo->findOneBy([
            'user' => $user, 'workflow' => $workflow,
            'step' => $step ?: null,
            'transitionName' => $transition ?: null
        ]) ?? (new UserWorkflowNotificationSetting())
            ->setUser($user)->setWorkflow($workflow)->setStep($step ?: null)->setTransitionName($transition ?: null);

        $payload = $req->toArray(); // {"email":true,"inapp":false}
        $setting->setEmailEnabled((bool)($payload['email'] ?? true));
        $setting->setInAppEnabled((bool)($payload['inapp'] ?? true));

        $this->entityManager->persist($setting);
        $this->entityManager->flush();
        return $this->json(['status' => 'ok']);
    }

    // DELETE = enlever l’override => retour à l’héritage
    #[Route('/{workflow}/{step?}/{transition?}', name: 'notif_settings_delete', methods: ['DELETE'])]
    public function deleteSettings(string $workflow, ?string $step, ?string $transition): JsonResponse
    {
        $user = $this->getUser();
        $repo = $this->entityManager->getRepository(UserWorkflowNotificationSetting::class);
        if ($s = $repo->findOneBy(['user' => $user, 'workflow' => $workflow, 'step' => $step ?: null, 'transitionName' => $transition ?: null])) {
            $this->entityManager->remove($s);
            $this->entityManager->flush();
        }
        return $this->json(['status' => 'ok']);
    }

    // PATCH global
    #[Route('/global', name: 'notif_settings_global', methods: ['PATCH'])]
    public function patchGlobal(Request $req): JsonResponse
    {
        $user = $this->getUser();
        $pref = $user->getNotificationPreference() ?? (new UserNotificationPreference())->setUser($user);

        $payload = $req->toArray();
        $pref->setEmailEnabled((bool)($payload['email'] ?? true));
        $pref->setInAppEnabled((bool)($payload['inapp'] ?? true));
        $this->entityManager->persist($pref);
        $this->entityManager->flush();

        return $this->json(['status' => 'ok']);
    }
}
