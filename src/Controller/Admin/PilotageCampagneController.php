<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Entity\CampagneCollecte;
use App\Repository\CampagneCollecteRepository;
use App\Service\Pilotage\CampagnePilotageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/pilotage-campagne')]
#[IsGranted('ROLE_ADMIN')]
class PilotageCampagneController extends BaseController
{
    public function __construct(
        private readonly CampagnePilotageService $campagnePilotageService,
        private readonly CampagneCollecteRepository $campagneCollecteRepository,
    ) {
    }

    #[Route('', name: 'app_admin_pilotage_campagne_index')]
    public function index(Request $request): Response
    {
        $campagneId = $request->query->getInt('campagne_id', 0);
        $thresholdDays = $request->query->getInt('threshold', 15);

        $allCampagnes = $this->campagneCollecteRepository->findBy([], ['id' => 'DESC']);

        $selectedCampagne = null;
        if ($campagneId > 0) {
            $selectedCampagne = $this->campagneCollecteRepository->find($campagneId);
        }

        if ($selectedCampagne === null) {
            $selectedCampagne = $this->dataUserSession->getCampagneCollecte() ?? ($allCampagnes[0] ?? null);
        }

        if ($selectedCampagne === null) {
            $this->addFlash('warning', 'Aucune campagne de collecte disponible.');
            return $this->redirectToRoute('app_homepage');
        }

        $overview = $this->campagnePilotageService->getCampagneOverview($selectedCampagne);
        $matrix = $this->campagnePilotageService->getComposantesMatrix($selectedCampagne);
        $slaData = $this->campagnePilotageService->getSlaAndStagnantDossiers($selectedCampagne, $thresholdDays);

        return $this->render('admin/pilotage/index.html.twig', [
            'allCampagnes' => $allCampagnes,
            'selectedCampagne' => $selectedCampagne,
            'overview' => $overview,
            'matrix' => $matrix,
            'slaData' => $slaData,
            'thresholdDays' => $thresholdDays,
        ]);
    }
}
