<?php

namespace App\Controller;

use App\Classes\Export\ExportCodification;
use App\Entity\Formation;
use App\Entity\Parcours;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CodificationParcoursExportController extends AbstractController
{
    #[Route('/codification/formation/{slug}/export', name: 'app_codification_export_formation')]
    public function exportFormation(
        ExportCodification  $export,
        Formation $formation): Response
    {
        return $export->exportFormation($formation);
    }
    #[Route('/codification/parcours/{parcours}/export', name: 'app_codification_parcours_export')]
    public function exportParcours(
        ExportCodification  $export,
        Parcours $parcours): Response
    {
        return $export->exportParcours($parcours);
    }
}
