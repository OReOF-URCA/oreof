<?php

namespace App\Controller;

use App\Classes\Export\ExportBcc;
use App\Classes\MyGotenbergPdf;
use App\Entity\Parcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeResolver;
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
        $typeD = $typeDiplomeResolver->fromFormation($formation);
        $bccData = $typeD->getBccCroiseData($parcours);
        $dto = $bccData['dto'] ?? $typeD->calcul($parcours, new OptionsCalculStructure(dataFromFicheMatiere: true));
        $titre = 'Tableau croisé Compétences / EC - ' . $parcours->getDisplay();
        $fileName = 'BCC_croise_' . $parcours->getDisplay() . ($formation ? '_' . $formation->getDisplay() : '');

        return $myGotenbergPdf->render('pdf/bcc_export_croise.html.twig', [
            'formation' => $formation,
            'parcours' => $parcours,
            'dto' => $dto,
            'bccData' => $bccData,
            'typeD' => $typeD,
            'editable' => false,
            'titre' => $titre,
        ], $fileName, [
            'landscape' => true,
            'paperSize' => 'A4',
            'marginTop' => 1.4,
            'marginBottom' => 0.9,
            'marginLeft' => 0.6,
            'marginRight' => 0.6,
        ]);
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
