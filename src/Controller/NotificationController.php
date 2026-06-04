<?php

namespace App\Controller;

use App\Classes\JsonReponse;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/notification/lu', name: 'app_notification_lu')]
    public function lu(
        NotificationRepository $notificationRepository,
        Request                $request
    ): Response {
        $notification = $notificationRepository->find($request->query->get('id'));
        if ($notification !== null && $this->canAccess($notification)) {
            $notification->setLu(true);
            $notificationRepository->save($notification, true);

            return $this->json(true);
        }

        return $this->json(false, 500);
    }

    #[Route('/notification/tout-lu', name: 'app_notification_tout_lu')]
    public function toutLu(
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
    ): Response {
        $notifications = $notificationRepository->findBy(['destinataire' => $this->getUser()], ['created' => 'DESC']);

        foreach ($notifications as $notification) {
            $notification->setLu(true);
        }
        $entityManager->flush();

        return JsonReponse::success('Notifications marquées comme lues.');
    }

    #[Route('/notification/tout-supprimer', name: 'app_notification_tout_suppr')]
    public function toutSuppr(
        EntityManagerInterface $entityManager,
        NotificationRepository $notificationRepository,
    ): Response {
        $notifications = $notificationRepository->findBy(['destinataire' => $this->getUser()], ['created' => 'DESC']);
        foreach ($notifications as $notification) {
            if ($notification->requiresAck()) {
                continue;
            }
            $notificationRepository->remove($notification);
        }
        $entityManager->flush();

        return JsonReponse::success('Notifications supprimées.');
    }

    #[Route('/notification/liste', name: 'app_notification_liste_user')]
    public function listeUser(
        NotificationRepository $notificationRepository,
        Request                $request
    ): Response {
        $notifications = $notificationRepository->findBy(['destinataire' => $this->getUser()], ['created' => 'DESC']);

        return $this->render('notification/_liste.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/notification/mutualisation/{id}/valider', name: 'app_notification_mutualisation_valider', methods: ['GET'])]
    public function validerMutualisation(
        Notification           $notification,
        NotificationRepository $notificationRepository,
        Request                $request,
    ): RedirectResponse
    {
        if (!$this->canAccess($notification)) {
            throw $this->createAccessDeniedException();
        }

        $notification->setLu(true);
        $notificationRepository->save($notification, true);

        $referer = $request->headers->get('referer');
        if ($referer !== null && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_homepage');
    }

    private function canAccess(Notification $notification): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $notification->getDestinataire()?->getId() === $user->getId();
    }
}
