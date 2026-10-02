<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Parcours/ParcoursV2Controller.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 16/01/2026 22:28
 */

namespace App\Controller\Parcours;

use App\Classes\GetDpeParcours;
use App\Classes\GetElementConstitutif;
use App\Controller\BaseController;
use App\Entity\Annee;
use App\Entity\ElementConstitutif;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Form\EcStep4Type;
use App\Form\ParcoursStep1Type;
use App\Form\ParcoursStep2Type;
use App\Form\ParcoursStep5Type;
use App\Form\ParcoursStep6Type;
use App\Form\ParcoursStep7Type;
use App\Repository\ParcoursRepository;
use App\TypeDiplome\TypeDiplomeResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/competences', name: 'parcours_v2_competences_')]
class ParcoursCompetencesController extends BaseController
{
    #[Route('/{parcours}/', name: 'index')]
    public function competences(
        TypeDiplomeResolver $typeDiplomeResolver,
        Parcours            $parcours,
        ParcoursRepository  $parcoursRepository
    ): Response
    {
        $typeD = $typeDiplomeResolver->fromParcours($parcours);
        $listeParcours = $parcoursRepository->findBy(['formation' => $parcours->getFormation()]);
        $competences = $typeD->getStructureCompetences($parcours);

        return $this->render('typeDiplome/' . $typeD->getTemplateFolder() . '/competences/_competence_edit.html.twig', [
            'parcours' => $parcours,
            'listeParcours' => $listeParcours,
            'competences' => $competences,
        ]);
    }

    #[Route('/{parcours}/competences/reset', name: 'reset')]
    public function competencesReset(
        TypeDiplomeResolver $typeDiplomeResolver,
        Parcours            $parcours,
    ): Response
    {
        $typeD = $typeDiplomeResolver->fromParcours($parcours);

        return $this->render('typeDiplome/' . $typeD->getTemplateFolder() . '/competences/_competence_edit.html.twig', [
            'parcours' => $parcours
        ]);
    }


    #[Route('/{parcours}/competences/recopie', name: 'recopie_bcc')]
    public function competencesRecopie(
        TypeDiplomeResolver $typeDiplomeResolver,
        Parcours            $parcours,
    ): Response
    {
        $typeD = $typeDiplomeResolver->fromParcours($parcours);

        return $this->render('typeDiplome/' . $typeD->getTemplateFolder() . '/competences/_competence_edit.html.twig', [
            'parcours' => $parcours
        ]);
    }
}
