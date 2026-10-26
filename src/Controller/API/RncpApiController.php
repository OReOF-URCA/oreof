<?php

declare(strict_types=1);

namespace App\Controller\API;

use App\Entity\Etablissement;
use App\Repository\EtablissementRepository;
use App\Repository\FormationRepository;
use App\Repository\ParcoursRepository;
use App\Service\Api\RncpApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/rncp', name: 'api_rncp_')]
class RncpApiController extends AbstractController
{
    #[Route('/verify', name: 'verify', methods: ['GET'])]
    public function verify(
        Request $request,
        RncpApiService $rncpApiService,
        EtablissementRepository $etablissementRepository,
        FormationRepository $formationRepository,
        ParcoursRepository $parcoursRepository,
    ): JsonResponse {
        $code = (string) $request->query->get('code', '');
        $etablissement = $this->resolveEtablissement($request, $etablissementRepository, $formationRepository, $parcoursRepository);

        $result = $rncpApiService->verify($etablissement, $code);

        return new JsonResponse($result);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(
        Request $request,
        RncpApiService $rncpApiService,
        EtablissementRepository $etablissementRepository,
        FormationRepository $formationRepository,
        ParcoursRepository $parcoursRepository,
    ): JsonResponse {
        $query = (string) $request->query->get('q', '');
        $etablissement = $this->resolveEtablissement($request, $etablissementRepository, $formationRepository, $parcoursRepository);

        $results = $rncpApiService->search($etablissement, $query);

        return new JsonResponse([
            'query' => $query,
            'connected' => $rncpApiService->isConfigured($etablissement),
            'results' => $results,
        ]);
    }

    private function resolveEtablissement(
        Request $request,
        EtablissementRepository $etablissementRepository,
        FormationRepository $formationRepository,
        ParcoursRepository $parcoursRepository,
    ): ?Etablissement {
        $etablissementId = $request->query->get('etablissement_id');
        if (!empty($etablissementId)) {
            $etab = $etablissementRepository->find($etablissementId);
            if ($etab !== null) {
                return $etab;
            }
        }

        $parcoursId = $request->query->get('parcours_id');
        if (!empty($parcoursId)) {
            $parcours = $parcoursRepository->find($parcoursId);
            if ($parcours?->getEtablissement() !== null) {
                return $parcours->getEtablissement();
            }
        }

        $formationId = $request->query->get('formation_id');
        if (!empty($formationId)) {
            $formation = $formationRepository->find($formationId);
            if ($formation?->getEtablissement() !== null) {
                return $formation->getEtablissement();
            }
        }

        return $etablissementRepository->findOneBy([]);
    }
}
