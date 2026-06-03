<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportSynthese.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 11/11/2023 13:07
 */

namespace App\Classes\Export;

use App\Classes\Excel\ExcelWriter;
use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Repository\FicheMatiereRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportListeFicheMatiere implements ExportInterface
{
    private const TEMPLATE = 'exports/liste_fiche_matiere.html.twig';

    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique          $getHistorique,
        protected ExcelWriter            $excelWriter,
        ProjectDirProvider               $projectDirProvider,
        protected FicheMatiereRepository $ficheMatiereRepository,
        protected SpreadsheetRenderer    $spreadsheetRenderer,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $fiches = $this->ficheMatiereRepository->findBy([
            'campagneCollecte' => $anneeUniversitaire,
        ], [
            'libelle' => 'ASC'
        ]);

        $lignes = [];
        foreach ($fiches as $fiche) {
            $lignes[] = [
                'id'              => $fiche->getId(),
                'libelle'         => $fiche->getLibelle(),
                'referent'        => $fiche->getResponsableFicheMatiere() !== null ? $fiche->getResponsableFicheMatiere()->getDisplay() : '',
                'complet'         => $fiche->remplissageBrut()->isFull() ? 'Complet' : 'Incomplet',
                'utilisee'        => $fiche->getElementConstitutifs()->count(),
                'parcoursPorteur' => $fiche->isHorsDiplome() === true ? 'Hors diplôme' : ($fiche->getParcours() !== null ? $fiche->getParcours()->getLibelle() : ''),
                'formation'       => $fiche->isHorsDiplome() === true ? 'Hors diplôme' : ($fiche->getParcours() !== null && $fiche->getParcours()->getFormation() !== null ? $fiche->getParcours()->getFormation()->getDisplayLong() : ''),
            ];
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $lignes])
        );

        $this->fileName = (string) Tools::FileName('Export - Fiches Matières - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function export(CampagneCollecte $anneeUniversitaire): StreamedResponse
    {
        $this->prepareExport($anneeUniversitaire);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportLink(CampagneCollecte $campagneCollecte): string
    {
        $this->prepareExport($campagneCollecte);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }
}
