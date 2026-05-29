<?php

namespace App\Controller;

use App\Classes\verif\FormationValide;
use App\Entity\Formation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FormationStateController extends AbstractController
{
    #[Route('/formation/state/{formation}', name: 'app_formation_state')]
    public function index(Formation $formation): Response
    {
        //todo: passer par le slug...

        $typeDiplome = $formation->getTypeDiplome();
        $valideFormation = new FormationValide($formation);


        return $this->render('formation_state/_index.html.twig', [
            'formation' => $formation,
            'valide' => $valideFormation->valideFormation(),
            'typeDiplome' => $typeDiplome,
        ]);
    }
}
