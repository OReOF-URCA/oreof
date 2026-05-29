<?php

namespace App\Controller;

use App\Classes\Export\ExportBcc;
use App\Classes\MyGotenbergPdf;
use App\Entity\Parcours;
use App\Service\TypeDiplomeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompetenceExportController extends AbstractController
{
    #[Route('/competence/export/bcc/{parcours}', name: 'app_competence_export_bcc')]
    public function index(
        MyGotenbergPdf $myGotenbergPdf,
        Parcours       $parcours
    ): Response
    {
        $formation = $parcours->getFormation();
        return $myGotenbergPdf->render('pdf/bcc_export.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'titre' => 'BCC du parcours ',
        ], 'BCC du parcours_' . $parcours->getDisplay() . '.pdf');
    }

    #[Route('/competence/export/croise/{parcours}', name: 'app_competence_export_croise')]
    public function croise(
        TypeDiplomeResolver $typeDiplomeResolver,
        MyGotenbergPdf $myGotenbergPdf,
        Parcours       $parcours
    ): Response
    {
        $formation = $parcours->getFormation();
        $typeD = $typeDiplomeResolver->getFromFormation($formation);
        $dto = $typeD->calculStructureParcours($parcours, false, true);
        return $myGotenbergPdf->render('pdf/bcc_export_croise.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'dto' => $dto,
            'titre' => 'BCC croisé du parcours ',
        ], 'BCC Croisé du parcours_' . $parcours->getDisplay() . '<br>' . $formation->getDisplayLong());
    }

    #[Route('/competence/export/croise-global/{parcours}', name: 'app_competence_export_croise_global')]
    public function croiseGlobal(
        ExportBcc       $excelBcc,
        Parcours       $parcours
    ): Response
    {
        return $excelBcc->export($parcours);
    }
}
