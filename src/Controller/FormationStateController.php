<?php

namespace App\Controller;

use App\Classes\verif\FormationValide;
use App\DTO\TranslatableKey;
use App\Entity\Formation;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FormationStateController extends AbstractController
{
    #[Route('/formation/state/{slug}', name: 'app_formation_state')]
    public function index(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation                  $formation,
        TurboStreamResponseFactory $turboStream
    ): Response
    {

        $typeDiplome = $formation->getTypeDiplome();
        $valideFormation = new FormationValide($formation);

        return $turboStream->streamOpenModalFromTemplates(
            new TranslatableKey('verifier.saisie.formation.titre'),
            new TranslatableKey('verifier.saisie.formation.description'),
            'formation_state/_index.html.twig',
            [
                'formation' => $formation,
                'valide' => $valideFormation->valideFormation(),
                'typeDiplome' => $typeDiplome,
            ]
        );
    }
}
