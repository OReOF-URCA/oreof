<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Controller/ParcoursMcccExportController.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 25/05/2023 14:33
 */

namespace App\Controller;

use App\Classes\GetDateConseilComposante;
use App\Classes\GetDpeParcours;
use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

class ParcoursMcccExportController extends BaseController
{

    #[Route('/parcours/mccc/export/{parcours}.{_format}', name: 'app_parcours_mccc_export')]
    public function exportMcccXlsx(
        GetDateConseilComposante $getDateConseilComposante,
        GetHistorique $getHistorique,
        Parcours $parcours,
        EntityManagerInterface $entityManager,
        string                 $_format = 'xlsx'
    ): StreamedResponse|Response
    {

        if($_format === "pdf"){
            return $this->getCfvuMcccExportFromFile($entityManager, $parcours, 'simplifie');
        }

        $formation = $parcours->getFormation();

        if (null === $formation) {
            throw new Exception('Pas de formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->get($formation->getTypeDiplome());

        $dpe = GetDpeParcours::getFromParcours($parcours);

        if ($dpe !== null) {
            $cfvu = $getHistorique->getHistoriqueParcoursLastStep($dpe, 'soumis_cfvu');
            //$conseil = $getHistorique->getHistoriqueParcoursLastStep($dpe, 'soumis_conseil');
            $conseil = $getDateConseilComposante->getDateConseilComposante($dpe);
        }

        return match ($_format) {
            'xlsx' => $typeDiplome->exportExcelMccc(
                $this->getCampagneCollecte(),
                $parcours,
                $cfvu?->getDate() ?? null,
                $conseil
            ),
            'pdf' => $typeDiplome->exportPdfMccc(
                $this->getCampagneCollecte(),
                $parcours,
                $cfvu?->getDate() ?? null,
                $conseil?->getDate() ?? null
            ),
            default => throw new Exception('Format non géré'),
        };
    }

    #[Route('/parcours/mccc/export-version/{parcours}.{_format}', name: 'app_parcours_mccc_export_versionning')]
    public function exportMcccVersionXlsx(
        GetDateConseilComposante $getDateConseilComposante,
        Parcours $parcours,
        string $_format = 'xlsx'
    ): StreamedResponse|Response
    {
        $formation = $parcours->getFormation();

        if (null === $formation) {
            throw new Exception('Pas de formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->get($formation->getTypeDiplome());

        //date conseil
        $dpe = GetDpeParcours::getFromParcours($parcours);
        if ($dpe !== null) {
            //$dateConseil = $getHistorique->getHistoriqueParcoursLastStep($dpe, 'soumis_conseil');
            $dateConseil = $getDateConseilComposante->getDateConseilComposante($dpe);
        }

        return match ($_format) {
            'xlsx' => $typeDiplome->exportExcelVersionMccc(
                $this->getCampagneCollecte(),
                $parcours,
                null,
                $dateConseil
            ),
            'pdf' => $typeDiplome->exportPdfMccc(
                $this->getCampagneCollecte(),
                $parcours,
                null,
                $dateConseil
            ),
            default => throw new Exception('Format non géré'),
        };
    }

    #[Route('/parcours/mccc/export-light/{parcours}.{_format}', name: 'app_parcours_mccc_export_light')]
    public function exportMcccLightXlsx(
        GetDateConseilComposante $getDateConseilComposante,
        GetHistorique $getHistorique,
        Parcours $parcours,
        string $_format = 'xlsx'
    ): StreamedResponse|Response
    {
        $formation = $parcours->getFormation();

        if (null === $formation) {
            throw new Exception('Pas de formation.');
        }

        $typeDiplome = $this->typeDiplomeResolver->get($formation->getTypeDiplome());
        $dpe = GetDpeParcours::getFromParcours($parcours);
        $conseil = null;
        if ($dpe !== null) {
            $cfvu = $getHistorique->getHistoriqueParcoursLastStep($dpe, 'soumis_cfvu');
            $conseil = $getDateConseilComposante->getDateConseilComposante($dpe);
        }

        return match ($_format) {
            'xlsx' => $typeDiplome->exportExcelMccc(
                $this->getCampagneCollecte(),
                $parcours,
                $cfvu?->getDate() ?? null,
                $conseil ?? null,
                false
            ),
            'pdf' => $typeDiplome->exportPdfMccc(
                $this->getCampagneCollecte(),
                $parcours,
                $cfvu?->getDate() ?? null,
                $conseil ?? null,
                false
            ),
            default => throw new Exception('Format non géré'),
        };
    }

    #[Route('/parcours/mccc/export/cfvu_valid/{parcours}/{format}', name: 'app_parcours_mccc_export_cfvu_valid')]
    public function getCfvuMcccExportFromFile(
        EntityManagerInterface $entityManager,
        Parcours $parcours,
        string                 $format = 'complet'
    ): Response
    {

        $dpeArray = $entityManager->getRepository(CampagneCollecte::class)->findBy([], ["id" => "ASC"]);
        $dpeArray = array_map(fn($dpe) => $dpe->getAnnee(), $dpeArray);

        function getFileName($parcours, $campagneId, $format, $dpeArray){
            $fileYear = $dpeArray[$campagneId - 1];

            $fileName = $format === 'simplifie'
            ? "MCCC-Parcours-{$parcours->getId()}-{$fileYear}-simplifie.pdf"
            : "MCCC-Parcours-{$parcours->getId()}-{$fileYear}.pdf";

            $fileName = __DIR__ . "/../../public/mccc-export/{$fileName}";
            return $fileName;
        };

        if(in_array($format, ['complet', 'simplifie']) === false){
            throw $this->createNotFoundException('File Type is invalid');
        }

        // On essaie la première année
        try {
            $pdf = file_get_contents(
                getFileName($parcours, 2, $format, $dpeArray)
            );
        } catch (Exception $e) {
            // Sinon, on essaie avec la deuxième
            try{
                $pdf = file_get_contents(
                    getFileName($parcours, 2, $format, $dpeArray)
                );
            }
            // S'il n'y a pas de correspondance, on émet un message d'erreur
            catch(Exception $error){
                throw $this->createNotFoundException("Le fichier demandé n'a pas été trouvé");
            }
        }

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
