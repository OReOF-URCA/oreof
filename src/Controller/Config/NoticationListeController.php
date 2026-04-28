<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Config/NoticationListeController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/03/2023 12:18
 */

namespace App\Controller\Config;

use App\Controller\GetAvailableWorkflows;
use App\Entity\NotificationListe;
use App\Repository\NotificationListeRepository;
use App\Repository\ProfilRepository;
use App\Utils\JsonRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NoticationListeController extends AbstractController
{
    #[Route('/administration/notification/liste', name: 'app_notication_liste')]
    public function index(
        ProfilRepository      $profilRepository,
        GetAvailableWorkflows $getAvailableWorkflows,
        NotificationListeRepository $notificationListeRepository
    ): Response {

        $notifications = $notificationListeRepository->findAll();
        $tNotifs = [];

        foreach ($notifications as $notif) {
            $cle = $notif->getWorkflow() . '_' . $notif->getStep();
            if (!array_key_exists($cle, $tNotifs)) {
                $tNotifs[$cle] = [];
            }
            $tNotifs[$cle][] = $notif->getProfil()?->getId();
        }

        return $this->render('notication_liste/index.html.twig', [
            'notifications' => $tNotifs,
            'workflows' => $getAvailableWorkflows->availableWorkflows(),
            'profils' => $profilRepository->findBy(['isMailing' => true], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/administration/notication/liste/mise-a-jour', name: 'app_notification_liste_mise_a_jour', methods: ['POST'])]
    public function miseAJour(
        Request $request,
        EntityManagerInterface $entityManager,
        ProfilRepository       $profilRepository,
        NotificationListeRepository $notificationListeRepository
    ): Response {
        $data = JsonRequest::getFromRequest($request);
        $idProfil = $data['idProfil'];
        $workflowKey = $data['workflow'];
        $value = (bool)$data['value'];

        $profil = $profilRepository->find($idProfil);
        if ($profil === null) {
            return $this->json(false);
        }
        $workflow = explode('_', $workflowKey, 2);
        $notif = $notificationListeRepository->findIfExist($profil, $workflow[0], $workflow[1]);

        if ($notif !== null && $value === false) {
            //notif existante et on veut la supprimer
            $entityManager->remove($notif);
        } else {
            $notif = new NotificationListe();
            $notif->setProfil($profil);

            $notif->setWorkflow($workflow[0]);
            $notif->setStep($workflow[1]);
            $entityManager->persist($notif);
        }

        $entityManager->flush();

        return $this->json(true);
    }
}
