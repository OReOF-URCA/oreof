<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\Parcours;

use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Form\EcStep4Type;
use App\Service\Parcours\GenereStructureParcours;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\TurboStreamResponseFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/structure/', name: 'parcours_structure')]
class ParcoursStructureController extends BaseController
{
    #[Route('/{parcours}/', name: '_genere', methods: ['GET'])]
    public function ec(
        TypeDiplomeResolver        $typeDiplomeResolver,
        TurboStreamResponseFactory $turboStream,
        GenereStructureParcours    $genereStructureParcours,
        Parcours                   $parcours): Response
    {
//        $this->denyAccessUnlessGranted('PARCOURS_EDIT', $parcours);

        // générer les entités Année et Structure pour le parcours
        // mettre à jour le menu
        $genereStructureParcours->genereStructureParcours($parcours);

        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $dto = $typeD->calcul($parcours);

        return $turboStream->stream('parcours_v2/turbo/structure_menu.stream.html.twig', [
            'parcours' => $parcours,
            'dto' => $dto,
            'toastMessage' => 'Structure générée avec succès pour le parcours.'
        ]);
    }
}
