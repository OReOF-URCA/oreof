<?php

namespace App\Controller;

use App\Classes\verif\ParcoursValide;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursStateController extends BaseController
{
    #[Route('/parcours/state/{parcours}', name: 'app_parcours_state')]
    public function index(Parcours $parcours): Response
    {
        $typeDiplome = $parcours->getFormation()?->getTypeDiplome();
        $valideParcours = new ParcoursValide($parcours, $typeDiplome);

        return $this->render('parcours_state/_index.html.twig', [
            'parcours' => $parcours,
            'valide' => $valideParcours->valideParcours(),
            'typeDiplome' => $typeDiplome,
        ]);
    }

    #[Route('/parcours/update-remplissage/{parcours}', name: 'app_parcours_update_remplissage')]
    public function updateRemplissage(
        Request $request,
        EntityManagerInterface $entityManager,
        Parcours $parcours
    ): Response {
        $remplissage = $parcours->remplissageBrut();
        $parcours->setRemplissage($remplissage);

        $entityManager->flush();

        $this->addFlashBag('success', 'Remplissage mis à jour. ' . $remplissage->calcul().'%');

        // Redirect to the previous page
        $referer = $request->headers->get('referer');
        return $this->redirect($referer);
    }


}
