<?php

namespace App\Controller\Admin;

use App\Service\VisitorStatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted('ROLE_ADMIN')]
#[Route('/administration/statistiques-visites', name: 'app_admin_statistiques_visites_')]
class StatistiquesVisitesController extends AbstractController
{
    /** Périodes proposées, en jours */
    private const PERIODES = [7, 30, 90, 365];

    private const PERIODE_DEFAUT = 30;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        VisitorStatsService $visitorStats,
        ChartBuilderInterface $chartBuilder,
    ): Response
    {
        $periode = $request->query->getInt('periode', self::PERIODE_DEFAUT);
        if (!in_array($periode, self::PERIODES, true)) {
            $periode = self::PERIODE_DEFAUT;
        }

        $to = new \DateTimeImmutable('today');
        $from = $to->modify(sprintf('-%d days', $periode - 1));
        $stats = $visitorStats->getStats($from, $to);

        return $this->render('admin/statistiques_visites/index.html.twig', [
            'stats' => $stats,
            'periode' => $periode,
            'periodes' => self::PERIODES,
            'from' => $from,
            'to' => $to,
            'chartPagesVues' => $this->chart(
                $chartBuilder,
                Chart::TYPE_BAR,
                'Pages vues',
                $this->dayLabels($stats['days']),
                array_column($stats['days'], 'pageViews'),
            ),
            'chartUtilisateurs' => $this->chart(
                $chartBuilder,
                Chart::TYPE_LINE,
                'Utilisateurs uniques',
                $this->dayLabels($stats['days']),
                array_column($stats['days'], 'uniqueUsers'),
            ),
            'chartHeures' => $this->chart(
                $chartBuilder,
                Chart::TYPE_BAR,
                'Pages vues',
                array_map(static fn (int $hour): string => $hour . 'h', array_keys($stats['hours'])),
                array_values($stats['hours']),
            ),
            'environment' => $this->getParameter('kernel.environment'),
        ]);
    }

    /**
     * Graphique à une seule série, coloré par assets/js/chartTheme.js (token sémantique `primary`).
     *
     * @param list<string> $labels
     * @param list<int>    $data
     */
    private function chart(ChartBuilderInterface $chartBuilder, string $type, string $label, array $labels, array $data): Chart
    {
        $chart = $chartBuilder->createChart($type);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => $label,
                'colorToken' => 'primary',
                'data' => $data,
                // Barres : extrémité arrondie côté valeur, ancrée sur l'axe ; lignes : trait 2px, points >= 8px au survol
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'borderWidth' => Chart::TYPE_LINE === $type ? 2 : 0,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'cubicInterpolationMode' => 'monotone',
            ]],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            // Une seule série : pas de légende, le titre de la section la nomme
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['autoSkip' => true, 'maxRotation' => 0]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ]);

        return $chart;
    }

    /**
     * @param array<string, mixed> $days clés Y-m-d
     *
     * @return list<string>
     */
    private function dayLabels(array $days): array
    {
        return array_map(static fn (string $date): string => (new \DateTimeImmutable($date))->format('d/m'), array_keys($days));
    }
}
