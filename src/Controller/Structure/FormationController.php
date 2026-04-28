<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/FormationController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Entity\Formation;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/formation', name: 'structure_formation_')]
class FormationController extends BaseController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('structure/formation/index.html.twig');
    }

    #[Route('/liste', name: 'liste')]
    public function liste(
        FormationRepository $formationRepository
    ): Response {
        if ($this->isGranted('ROLE_ADMIN') ||
            $this->isGranted('SHOW', ['route' => 'app_composante', 'subject' => 'composante']) ||
            $this->isGranted('SHOW', ['route' => 'app_formation', 'subject' => 'formation'])
        ) {
            $formations = $formationRepository->findBy(['anneeUniversitaire' => $this->getCampagneCollecte()]);
        } else {
            $formations = [];
            $formations[] = $formationRepository->findByComposanteDpe($this->getUser(), $this->getCampagneCollecte());
            $formations[] = $formationRepository->findBy(['responsableMention' => $this->getUser(), 'anneeUniversitaire' => $this->getCampagneCollecte()]);
            $formations = array_merge(...$formations);
        }

        return $this->render('structure/formation/_liste.html.twig', [
            'formations' => $formations
        ]);
    }

    #[Route('/detail/composante/{composante}', name: 'detail_composante')]
    public function detailComposante(
        FormationRepository $formationRepository,
        Composante $composante
    ): Response
    {
        $formations = $formationRepository->findBy(['composantePorteuse' => $composante]);//todo: filtrer selon droits ? Ajouter les co-portées ? avec une mise en valeur et sans édition ? si resp DPE


        return $this->render('structure/formation/_liste.html.twig', [
            'formations' => $formations
        ]);
    }

    #[Route('/validation/{formation}', name: 'modal_validation')]
    public function validation(
        Formation $formation
    ): Response
    {
        //check des différents droits
        //check si la formation est valide
        //check si les champs sont complets
        //affiche la synthèse de la formation
        return $this->render('structure/formation/_modal_validate.html.twig', [
            'formation' => $formation
        ]);
    }

    #[Route('/validate/{formation}', name: 'modal_validate')]
    public function validate(
        FormationRepository $formationRepository,
        Formation $formation
    ): Response
    {
        //avance le workflow
        //écouter l'évent pour envoyer un mail
        //changer l'état de la formation avec le workflow
        //bloquer la modif
    }
}
