<?php

namespace App\Controller;

use App\Classes\MyGotenbergPdf;
use App\Entity\Formation;
use App\TypeDiplome\TypeDiplomeResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlaquetteFormationController extends AbstractController
{
    public function __construct(
        private readonly MyGotenbergPdf $myPdf
    ) {
    }

    #[Route('/communication/plaquette/formation/{slug}', name: 'app_plaquette_formation_export')]
    public function index(
        TypeDiplomeResolver $typeDiplomeResolver,
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Formation           $formation
    ): Response {
        $rubriques = $formation->getComposantePorteuse()?->getPlaquetteRubriques();
        $typeDiplome = $formation->getTypeDiplome();
        $tParcours = [];
        $typeD = $typeDiplomeResolver->get($typeDiplome);
        foreach ($formation->getParcours() as $parcours) {

            $tParcours[$parcours->getId()] = $typeD->calculStructureParcours($parcours);
        }

        return $this->myPdf->render('pdf/formation_plaquette.html.twig', [
            'composante' => $formation->getComposantePorteuse(),
            'formation' => $formation,
            'typeDiplome' => $typeDiplome,
            'titre' => 'Plaquette de la formation '.$formation->getDisplay(),
            'tParcours' => $tParcours,
            'rubriques' => $rubriques,
        ], 'Plaquette_formation_'.$formation->getDisplay().'.pdf', [
            'withTemplate' => true,
        ]);
    }
}
