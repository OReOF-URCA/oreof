<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/CategoryNotificationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 03/10/2025 13:00
 */

namespace App\Controller;

use App\Entity\NotificationCategory;
use App\Entity\UserCategoryNotificationSetting;
use App\Notification\NotificationPreferenceResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notification-categories')]
final class CategoryNotificationController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    )
    {
    }

    #[Route('/{code}', methods: ['GET'])]
    public function getOne(string $code, NotificationPreferenceResolver $resolver): JsonResponse
    {
        $user = $this->getUser();
        return $this->json($resolver->resolveCategory($user, $code)); // {channels, frequency, minSeverity, source}
    }

    #[Route('/{code}', methods: ['POST'])]
    public function save(string $code, Request $r): JsonResponse
    {
        $user = $this->getUser();
        $cat = $this->entityManager->getRepository(NotificationCategory::class)->find($code);
        $s = $this->entityManager->getRepository(UserCategoryNotificationSetting::class)
            ->findOneBy(['user' => $user, 'category' => $cat]) ?? (new UserCategoryNotificationSetting())->setUser($user)->setCategory($cat);

        $p = $r->toArray();

        $s->setEmailEnabled((bool)($p['email'] ?? true));
        $s->setInAppEnabled((bool)($p['inapp'] ?? true));
        $s->setFrequency($p['frequency'] ?? 'immediate');
        $s->setMinSeverity($p['minSeverity'] ?? 'normal');

        $this->entityManager->persist($s);
        $this->entityManager->flush();

        return $this->json(['status' => 'ok']);
    }

    #[Route('/{code}', methods: ['DELETE'])]
    public function remove(string $code): JsonResponse
    {
        $user = $this->getUser();
        $repo = $this->entityManager->getRepository(UserCategoryNotificationSetting::class);

        if ($s = $repo->findOneByUserAndCode($user, $code)) {
            $this->entityManager->remove($s);
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'ok']);
    }
}
