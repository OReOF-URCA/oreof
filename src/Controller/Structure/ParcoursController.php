<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/ParcoursController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Classes\GetDpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Repository\ParcoursRepository;
use App\Repository\SemestreParcoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/parcours', name: 'structure_parcours_')]
class ParcoursController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('structure/parcours/index.html.twig');
    }

//    #[
//        Route('/liste', name: 'liste')
//    ]
//    public function liste(): Response
//    {
//        return $this->render('structure/parcours/_liste.html.twig');
//    }

//    #[Route('/detail/formation/{formation}', name: 'detail_formation')]
//    public function detailFormation(
//        ParcoursRepository $parcoursRepository,
//        Formation $formation
//    ): Response
//    {
//        $parcours = $parcoursRepository->findBy(['formation' => $formation]);//todo: filtrer selon droits ? Ajouter les co-portées ? avec une mise en valeur et sans édition ? si resp DPE
//
//
//        return $this->render('structure/parcours/_liste.html.twig', [
//            'parcours' => $parcours
//        ]);
//    }

    #[Route('/detail/parcours/{parcours}', name: 'detail_formation_parcours')]
    public function detailFormationTroncCommun(
        Request $request,
        SemestreParcoursRepository $semestreRepository,
        Parcours $parcours
    ): Response
    {
        $formation = $parcours->getFormation();
        if (null === $formation) {
            throw $this->createNotFoundException('La formation n\'existe pas');
        }

        $dpeParcours = GetDpeParcours::getFromParcours($parcours);

        if (null === $dpeParcours) {
            throw $this->createNotFoundException('Le DPE du parcours n\'existe pas');
        }

        $typeDiplome = $formation->getTypeDiplome();

        if (null === $typeDiplome) {
            throw $this->createNotFoundException('Le type de diplôme n\'existe pas');
        }

        $sems = $semestreRepository->findByParcours($parcours);

        $semestres = [];
        foreach ($sems as $sem) {
            $semestres[$sem->getOrdre()] = $sem;
        }

        $debut = $parcours->getFormation()?->getSemestreDebut();

        return $this->render('structure/semestre/_liste.html.twig', [
            'semestres' => $semestres,
            'parcours' => $parcours,
            'dpeParcours' => $dpeParcours,
            'debut' => $debut,
            'fin' => $typeDiplome->getSemestreFin(),
            'hasParcours' => $parcours->getFormation()?->isHasParcours(),
            'semestreAffiche' => $request->getSession()->get('semestreAffiche'),
            'ueAffichee' => $request->getSession()->get('ueAffichee'),
        ]);
    }
}
