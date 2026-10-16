<?php

declare(strict_types=1);

namespace App\Controller\Parcours;

use App\Entity\Parcours;
use App\Service\Api\RomeApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/parcours/v2/{id}/rome', name: 'parcours_v2_rome_')]
class ParcoursRomeController extends AbstractController
{
    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('SHOW', subject: 'parcours')]
    public function status(Parcours $parcours, RomeApiService $romeService): JsonResponse
    {
        $isConfigured = $romeService->isConfigured($parcours);
        $config = $romeService->getConfiguration($parcours);

        return new JsonResponse([
            'connected' => $isConfigured,
            'plateforme' => $config['plateforme'] ?? null,
            'url' => $config['url'] ?? null,
        ]);
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    #[IsGranted('SHOW', subject: 'parcours')]
    public function search(Request $request, Parcours $parcours, RomeApiService $romeService): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $results = $romeService->search($parcours, $query);

        return new JsonResponse([
            'connected' => $romeService->isConfigured($parcours),
            'query' => $query,
            'results' => $results,
        ]);
    }

    #[Route('/verify', name: 'verify', methods: ['GET', 'POST'])]
    #[IsGranted('SHOW', subject: 'parcours')]
    public function verify(Request $request, Parcours $parcours, RomeApiService $romeService): JsonResponse
    {
        $codes = [];

        if ($request->isMethod('POST')) {
            $content = $request->getContent();
            $data = json_decode((string) $content, true);
            if (is_array($data) && isset($data['codes']) && is_array($data['codes'])) {
                $codes = $data['codes'];
            }
        }

        if (empty($codes)) {
            $queryCodes = (string) $request->query->get('codes', '');
            if ($queryCodes !== '') {
                $codes = explode(',', $queryCodes);
            }
        }

        $results = $romeService->verifyCodes($parcours, $codes);

        return new JsonResponse([
            'connected' => $romeService->isConfigured($parcours),
            'codes' => $results,
        ]);
    }
}
