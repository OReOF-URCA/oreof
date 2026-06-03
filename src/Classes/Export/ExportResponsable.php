<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportResponsable.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/03/2025 15:38
 */

namespace App\Classes\Export;

use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportResponsable
{
    private const TEMPLATE = 'exports/responsable.html.twig';

    private string $dir;
    private string $fileName;

    public function __construct(
        protected FormationRepository   $formationRepository,
        protected DpeParcoursRepository $dpeParcoursRepository,
        protected SpreadsheetRenderer   $spreadsheetRenderer,
        ProjectDirProvider              $projectDirProvider,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    public function exportLink(array $formations): string
    {
        $this->fileName = (string) Tools::FileName(
            'EXPORT-Responsable - ' . (new DateTime())->format('d-m-Y-H-i'),
            30
        );

        // HTML (Twig) -> classeur PhpSpreadsheet via le bundle.
        $workbook = $this->spreadsheetRenderer->createFromTemplate(
            self::TEMPLATE,
            ['lignes' => $this->collecteLignes($formations)],
        );

        // Mise en page identique à l'ancien ExcelWriter::pageSetup() (le bundle
        // ne gère pas les en-têtes/pieds de page).
        $this->appliquePageSetup($workbook, $this->fileName);

        $dir = Tools::formatDir($this->dir . 'zip/');
        (new Xlsx($workbook))->save($dir . $this->fileName . '.xlsx');

        return $this->fileName . '.xlsx';
    }

    /**
     * Construit les lignes du tableau à partir des identifiants de DpeParcours.
     *
     * @param array<int|string> $formations
     * @return list<array<string, ?string>>
     */
    private function collecteLignes(array $formations): array
    {
        $lignes = [];
        foreach ($formations as $idFormation) {
            $dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            if ($dpeParcours === null) {
                continue;
            }

            $parcours = $dpeParcours->getParcours();
            $formation = $parcours?->getFormation();
            if ($parcours === null || $formation === null) {
                continue;
            }

            $lignes[] = [
                'composante'      => $formation->getComposantePorteuse()?->getLibelle(),
                'typeDiplome'     => $formation->getTypeDiplome()?->getLibelle(),
                'mention'         => $formation->getMention()?->getLibelle(),
                'parcours'        => $parcours->getLibelle(),
                'respFormation'   => $formation->getResponsableMention()?->getDisplay(),
                'coRespFormation' => $formation->getCoResponsable()?->getDisplay(),
                'respParcours'    => $parcours->getRespParcours()?->getDisplay(),
                // À l'identique de l'ancien code : "Co. Resp. Parcours" reprend respParcours.
                'coRespParcours'  => $parcours->getRespParcours()?->getDisplay(),
            ];
        }

        return $lignes;
    }

    /**
     * Reprend à l'identique l'ancien ExcelWriter::pageSetup() : orientation
     * paysage, grille à l'écran, en-tête/pied de page ORéOF + pagination.
     */
    private function appliquePageSetup(Spreadsheet $workbook, string $name): void
    {
        $workbook->getProperties()->setTitle($name);

        $sheet = $workbook->getActiveSheet();
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->setShowGridlines(true);
        $sheet->setPrintGridlines(false);
        $sheet->getHeaderFooter()->setOddHeader('&C&HDocument généré depuis ORéOF');
        $sheet->getHeaderFooter()->setOddFooter('&L&B' . $name . '&RPage &P sur &N');
        $sheet->getHeaderFooter()->setEvenHeader('&C&HDocument généré depuis ORéOF');
        $sheet->getHeaderFooter()->setEvenFooter('&L&B' . $name . '&RPage &P sur &N');
    }
}
