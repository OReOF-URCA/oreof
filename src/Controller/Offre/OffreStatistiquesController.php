<?php

namespace App\Controller\Offre;

use App\Controller\BaseController;
use App\Entity\Composante;
use App\Service\Offre\OffreStatistiquesService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class OffreStatistiquesController extends BaseController
{
    #[Route('/offrev2/statistiques', name: 'offre_v2_statistiques', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/statistiques', name: 'offre_v2_statistiques_composante', methods: ['GET'])]
    public function index(
        Request $request,
        OffreStatistiquesService $statistiquesService,
        ?Composante $composante = null,
    ): Response {
        if ($composante !== null) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->denyAccessUnlessGranted('MANAGE', [
                    'route' => 'app_composante',
                    'subject' => $composante,
                ]);
            }
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $campagne = $this->getCampagneCollecte();

        $filters = [
            'comp' => $request->query->get('comp'),
            'type' => $request->query->get('type'),
            'plateforme' => $request->query->get('plateforme'),
            'statut' => $request->query->get('statut', 'ouvert'),
            'q' => $request->query->get('q'),
        ];

        $stats = $statistiquesService->getStats($campagne, $composante, $filters);

        return $this->render('offre_v2/statistiques.html.twig', $stats);
    }

    #[Route('/offrev2/statistiques/export', name: 'offre_v2_statistiques_export', methods: ['GET'])]
    #[Route('/offre/composante/{composante}/statistiques/export', name: 'offre_v2_statistiques_composante_export', methods: ['GET'])]
    public function exportCsv(
        Request $request,
        OffreStatistiquesService $statistiquesService,
        ?Composante $composante = null,
    ): Response {
        if ($composante !== null) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $this->denyAccessUnlessGranted('MANAGE', [
                    'route' => 'app_composante',
                    'subject' => $composante,
                ]);
            }
        } else {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

        $campagne = $this->getCampagneCollecte();

        $filters = [
            'comp' => $request->query->get('comp'),
            'type' => $request->query->get('type'),
            'plateforme' => $request->query->get('plateforme'),
            'statut' => $request->query->get('statut', 'ouvert'),
            'q' => $request->query->get('q'),
        ];

        $stats = $statistiquesService->getStats($campagne, $composante, $filters);

        $response = new StreamedResponse(function () use ($stats) {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // BOM UTF-8 pour Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-têtes CSV
            fputcsv($handle, [
                'Composante',
                'Type de diplôme',
                'Formation',
                'Parcours',
                'Statut parcours',
                'Niveau',
                'Année (ordre)',
                'Capacité d\'accueil',
                'Capacité comptabilisée',
                'Tronc commun',
                'Proposé au recrutement',
                'Plateformes actives',
            ], ';');

            foreach ($stats['detailLignes'] as $ligne) {
                fputcsv($handle, [
                    $ligne['composante'],
                    $ligne['typeDiplome'],
                    $ligne['formation'],
                    $ligne['parcours'],
                    $ligne['isOuvert'] ? 'Ouvert' : 'Fermé',
                    $ligne['niveau'],
                    $ligne['ordre'],
                    $ligne['capaciteAccueil'],
                    $ligne['capaciteComptee'],
                    $ligne['isTc'] ? 'Oui' : 'Non',
                    $ligne['isRecrutement'] ? 'Oui' : 'Non',
                    $ligne['plateformes'],
                ], ';');
            }

            fclose($handle);
        });

        $dateStr = (new \DateTimeImmutable())->format('Y-m-d');
        $filename = sprintf('statistiques-places-offre-%s.csv', $dateStr);

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
