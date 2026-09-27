<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/oreofv2/src/Controller/Structure/FicheMatiereController.php
 * @author davidannebicque
 * @project oreofv2
 */

namespace App\Controller\Structure;

use App\Controller\BaseController;
use App\DataTable\FicheMatiereDataTable;
use App\DataTable\FicheMatiereHorsDiplomeDataTable;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/structure/fiche-matiere', name: 'structure_fiche_matiere_')]
class FicheMatiereController extends BaseController
{
    #[Route('/', name: 'index', methods: ['GET', 'POST'])]
    public function index(
        FicheMatiereDataTable $table,
    ): Response {
        return $this->render(
            'structure/fiche_matiere/index.html.twig',
            [
                'type' => 'parcours',
                'table' => $table,
            ]
        );
    }

    #[Route('/hors-diplome', name: 'index_hd', methods: ['GET', 'POST'])]
    public function indexHorsDiplome(
        FicheMatiereHorsDiplomeDataTable $table,
    ): Response {
        return $this->render(
            'structure/fiche_matiere/index.html.twig',
            [
                'type' => 'hd',
                'table' => $table,
            ]
        );
    }
}
