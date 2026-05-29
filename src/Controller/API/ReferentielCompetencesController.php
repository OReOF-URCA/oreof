<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/API/ReferentielCompetencesController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/05/2025 05:53
 */

declare(strict_types=1);

namespace App\Controller\API;

use App\Classes\Json\ExportReferentielCompetencesBut;
use App\Repository\FormationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReferentielCompetencesController extends AbstractController
{
    #[Route('/api/referentiel-competences/{referentiel}/export-json', name: 'api_referentiel_competences_export_json', methods: ['GET'])]
    public function index(
        ExportReferentielCompetencesBut $exportReferentielCompetencesBut,
        FormationRepository             $formationRepository,
        int                             $referentiel): Response
    {
        $formation = $formationRepository->find($referentiel);

        if (!$formation) {
            return $this->json(['error' => 'Formation not found'], Response::HTTP_NOT_FOUND);
        }

        if ($formation->getTypeDiplome()?->getLibelleCourt() === 'BUT') {
            return $this->json(
                [
                    'referentiel' => $formation?->getDisplay(),
                    'sigle' => $formation?->getSigle(),
                    'type_diplome' => $formation->getTypeDiplome()?->getLibelleCourt(),
                    'competences' => $exportReferentielCompetencesBut->exportToArray($formation),
                ]);
        } else {

        }

        return $this->json([]);
    }
}
