<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Service/StatsChartService.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/10/2025 11:21
 */

// src/Service/StatsChartService.php
namespace App\Service;

use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class StatsChartService
{
    private ChartBuilderInterface $chartBuilder;

    public function __construct(ChartBuilderInterface $chartBuilder)
    {
        $this->chartBuilder = $chartBuilder;
    }

    public function getHomepageCharts(): array
    {
        $chart1 = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart1->setData([
            'labels' => ['Formations', 'Parcours'],
            'datasets' => [[
                'label' => 'Offres',
                'backgroundColor' => 'rgb(54, 162, 235)',
                'data' => [12, 8],
            ]],
        ]);

        $chart2 = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $chart2->setData([
            'labels' => ['Complet', 'En cours'],
            'datasets' => [[
                'label' => 'Statut',
                'backgroundColor' => ['rgb(75, 192, 192)', 'rgb(255, 205, 86)'],
                'data' => [7, 5],
            ]],
        ]);

        $chart3 = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart3->setData([
            'labels' => ['Jan', 'Fév', 'Mar'],
            'datasets' => [[
                'label' => 'Nouveaux inscrits',
                'borderColor' => 'rgb(255, 99, 132)',
                'data' => [3, 6, 4],
            ]],
        ]);

        return [$chart1, $chart2, $chart3];
    }
}
