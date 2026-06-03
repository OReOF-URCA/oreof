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
use App\Entity\ElementConstitutif;
use App\Repository\ElementConstitutifRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportEc implements ExportInterface
{
    private const TEMPLATE = 'exports/ec.html.twig';

    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique                $getHistorique,
        protected ExcelWriter                  $excelWriter,
        ProjectDirProvider                     $projectDirProvider,
        protected ElementConstitutifRepository $elementConstitutifRepository,
        protected SpreadsheetRenderer          $spreadsheetRenderer,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $ecs = $this->elementConstitutifRepository->findWithParcours();

        $lignes = [];
        /** @var ElementConstitutif $ec */
        foreach ($ecs as $ec) {
            $lignes[] = [
                'composante'   => $ec->getParcours()?->getFormation()?->getComposantePorteuse()?->getLibelle(),
                'typeDiplome'  => $ec->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelle(),
                'mention'      => $ec->getParcours()?->getFormation()?->getDisplay(),
                'parcours'     => $ec->getParcours()?->getFormation()?->isHasParcours() ? $ec->getParcours()?->getLibelle() : 'Pas de parcours',
                'semestre'     => $ec->getUe()?->getSemestre()?->display(),
                'numUe'        => $ec->getUe()?->display($ec->getParcours()),
                'intituleUe'   => $ec->getUe()?->getLibelle(),
                'numEc'        => $ec->getCode(),
                'intituleEc'   => $ec->getLibelle(),
                'ficheMatiere' => $ec->getFicheMatiere()?->getLibelle(),
                'typeEc'       => $ec->getTypeEc()?->getType()->value,
                'referent'     => $ec->getFicheMatiere()?->getResponsableFicheMatiere() !== null ? $ec->getFicheMatiere()?->getResponsableFicheMatiere()?->getDisplay() : 'Non défini - RP ou RF',
            ];
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $lignes])
        );

        $this->fileName = (string) Tools::FileName('Export - EC - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
