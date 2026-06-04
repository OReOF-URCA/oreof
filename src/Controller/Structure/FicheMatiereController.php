<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/Structure/EcController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 17/03/2023 22:08
 */

namespace App\Controller\Structure;

use App\Controller\BaseController;
use App\Entity\Ue;
use App\Repository\ElementConstitutifRepository;
use App\Repository\FicheMatiereRepository;
use App\Repository\MentionRepository;
use App\Repository\ParcoursRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/fiche-matiere', name: 'structure_fiche_matiere_')]
class FicheMatiereController extends BaseController
{
    #[Route('/', name: 'index')]
    public function index(
        Request $request
    ): Response {
        return $this->render(
            'structure/fiche_matiere/index.html.twig',
            [
                'type' => $request->query->get('type', 'parcours'),
                'page' => $request->query->get('page', 1),
            ]
        );
    }

    #[Route('/liste', name: 'liste')]
    public function liste(
        MentionRepository $mentionRepository,
        ParcoursRepository $parcoursRepository,
        UserRepository $userRepository,
        Request                $request,
        FicheMatiereRepository $ficheMatiereRepository
    ): Response {

        if ($this->isGranted('ROLE_ADMIN')) {
            $results = $ficheMatiereRepository->findByAdmin(
                $this->getCampagneCollecte(),
                $request->query->all()
            );

        } else {
            if ($this->getUser()->getComposanteResponsableDpe()->count() > 0) {
                $results = $ficheMatiereRepository->findByDpe(
                    $this->getUser(),
                    $this->getCampagneCollecte(),
                    $request->query->all()
                );
            } else {
                $results = $ficheMatiereRepository->findByResponsable(
                    $this->getUser(),
                    $this->getCampagneCollecte(),
                    $request->query->all()
                );
            }
        }

        $tMentions = $mentionRepository->findBy([], ['typeDiplome' => 'ASC', 'libelle' => 'ASC']);
        $tParcours = $parcoursRepository->findByCampagneCollecte($this->getCampagneCollecte());
        $tUsers = $userRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']);

        $nbPages = ceil($results['total'] / 50);
        if ($results['page'] > $nbPages) {
            $results['page'] = 1;
        }

        return $this->render('structure/fiche_matiere/_liste.html.twig', [
            'ficheMatieres' => $results['data'],
            'page' => $results['page'],
            'deplacer' => false,
            'mode' => 'liste',
            'mentions' => $tMentions,
            'parcours' => $tParcours,
            'users' => $tUsers,
            'params' => $request->query->all(),
            'totalFiches' => $results['total'],
            'nbPages' =>$nbPages,
        ]);
    }

    #[Route('/liste/hors-diplome', name: 'liste_hd')]
    public function listeHorsDiplome(
        Request                $request,
        FicheMatiereRepository $ficheMatiereRepository
    ): Response {
        $results = $ficheMatiereRepository->findByHd(
            $this->getCampagneCollecte(),
            $request->query->all(),
        );

        $nbPages = ceil($results['total'] / 50);
        if ($results['page'] > $nbPages) {
            $results['page'] = 1;
        }

        return $this->render('structure/fiche_matiere/_listeHd.html.twig', [
            'ficheMatieres' => $results['data'],
            'page' => $results['page'],
            'deplacer' => false,
            'mode' => 'liste',
            'params' => $request->query->all(),
            'totalFiches' => $results['total'],
            'nbPages' =>$nbPages,
        ]);
    }

    #[Route('/detail/ue/{ue}/{parcours}', name: 'detail_ue')]
    public function detailComposante(
        ElementConstitutifRepository $elementConstitutifRepository,
        Ue                           $ue,
    ): Response {
        $ecs = $elementConstitutifRepository->findByUe($ue);

        return $this->render('structure/fiche_matiere/_liste.html.twig', [
            'ecs' => $ecs,
            'mode' => 'detail',
            'sort' => 'libelle',
            'direction' => 'asc'
        ]);
    }
}
