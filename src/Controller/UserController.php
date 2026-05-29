<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/UserController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/03/2023 10:15
 */

namespace App\Controller;

use App\Repository\NotificationListeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/utilisateur/mes-informations', name: 'app_user_mes_informations')]
    public function mesInformations(): Response
    {
        return $this->render('user/mes-informations.html.twig', [
            'profils' => $this->getUser()->getUserProfils(),

        ]);
    }

    #[Route('/utilisateur/mes-notifications', name: 'app_user_mes_notifications')]
    public function mesNotifications(
        NotificationListeRepository $notificationListeRepository
    ): Response
    {
        return $this->render('user/mes-notifications.html.twig', [
            'notifications' => $notificationListeRepository->findAll(),
        ]);
    }


}
