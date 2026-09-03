<?php

namespace App\Classes\Excel;

use App\DTO\DiffObject;
use App\Utils\LogValue;
use App\Utils\Tools;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\SheetView;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExcelWriter
{
    protected ?Spreadsheet $spreadsheet;
    protected ?Worksheet $sheet;
    protected string $dir;

    public function __construct(KernelInterface $kernel)
    {
        $this->dir = $kernel->getProjectDir() . '/public/modeles/';
    }

    public function nouveauFichier(string $libelle = ''): void
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->removeSheetByIndex(0);
        if ($libelle !== '') {
            $this->createSheet($libelle);
        }
    }

    public function createSheet(string $libelle): void
    {
        $this->spreadsheet->createSheet()->setTitle($libelle);
        $this->sheet = $this->spreadsheet->getSheetByName($libelle);
        $this->sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
    }

    public function setSheet(?Worksheet $sheet): void
    {
        $this->sheet = $sheet;
    }


    public function writeCellXY(int|string $col, int $row, mixed $value = '', array $options = []): void
    {
        if (is_string($col)) {
            $col = Coordinate::columnIndexFromString($col);
        }

        $this->sheet->setCellValue([$col, $row], $value);
        //
        //traiter les options
        //style n'est pas un tableau
        if ($this->sheet->getCell([$col, $row])) {
            foreach ($options as $key => $valeur) {
                switch ($key) {
                    case 'style':
                        switch ($valeur) {
                            case 'HORIZONTAL_RIGHT':
                                $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                                break;
                            case 'HORIZONTAL_CENTER':
                                $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                break;
                            case 'numerique':
                                $this->sheet->getCell([$col, $row])->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                                break;
                        }
                        break;
                    case 'valign':
                        switch ($valeur) {
                            case 'VERTICAL_TOP':
                                $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                                break;
                            case 'VERTICAL_CENTER':
                                $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                                break;
                            case 'VERTICAL_BOTTOM':
                                $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
                                break;
                        }
                        break;
                    case 'number_format':
                        $this->sheet->getCell([$col, $row])->getStyle()->getNumberFormat()->setFormatCode($valeur);
                        break;
                    case 'color':
                        if (str_starts_with($valeur, '#')) {
                            $valeur = mb_substr($valeur, 1, mb_strlen($valeur));
                        }

                        $this->sheet->getCell([$col, $row])->getStyle()->getFont()->getColor()->setARGB('FF' . $valeur);
                        break;
                    case 'bgcolor':
                        if ($valeur === 'none') {
                            $this->sheet->getCell([$col, $row])->getStyle()->getFill()->setFillType(Fill::FILL_NONE);
                        } else {
                            if (str_starts_with($valeur, '#')) {
                                $valeur = mb_substr($valeur, 1, mb_strlen($valeur));
                            }
                            $this->sheet->getCell([$col, $row])->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($valeur);
                        }
                        break;
                    case 'font-size':
                        $this->sheet->getCell([$col, $row])->getStyle()->getFont()->setSize($valeur);
                        break;
                    case 'font-weight':
                        $this->sheet->getCell([$col, $row])->getStyle()->getFont()->setBold($valeur);
                        break;
                    case 'font-italic':
                        $this->sheet->getCell([$col, $row])->getStyle()->getFont()->setItalic(true);
                        break;
                    case 'wrap':
                        $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setWrapText(true);
                        break;
                    case 'pourcentage':
                        $this->sheet->getCell([$col, $row])->getStyle()->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                        break;
                    case 'bold':
                        if ($valeur === true) {
                            $this->sheet->getCell([$col, $row])->getStyle()->getFont()->setBold(true);
                        }
                }
            }
        }
    }

    /**
     * Ecriture d'une cellule d'en-tete avec un style par defaut.
     * Les options passees en argument surchargent les valeurs par defaut.
     */
    public function writeCellXYHeader(int|string $col, int $row, mixed $value = '', array $options = []): void
    {
        $headerOptions = array_merge([
            'bgcolor' => '#D9E1F2',
            'bold' => true,
            'style' => 'HORIZONTAL_CENTER',
            'valign' => 'VERTICAL_CENTER',
            'wrap' => true,
        ], $options);

        $this->writeCellXY($col, $row, $value, $headerOptions);

        if (($options['border'] ?? true) === true) {
            $cell = (is_int($col) ? Coordinate::stringFromColumnIndex($col) : $col) . $row;
            $this->borderCells($cell);
        }
    }

    public function writeCellName(string $adresse, mixed $value, array $options = []): void
    {
        $this->sheet->setCellValue($adresse, $value);

        if (is_array($options) && array_key_exists('style', $options)) {
            //style n'est pas un tableau
            switch ($options['style']) {
                case 'HORIZONTAL_RIGHT':
                    $this->sheet->getStyle($adresse)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    break;
                case 'HORIZONTAL_CENTER':
                    $this->sheet->getStyle($adresse)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    break;
                case 'numerique':
                    $this->sheet->getStyle($adresse)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                    break;
                case 'numerique3':
                    $this->sheet->getStyle($adresse)->getNumberFormat()->setFormatCode('#,##0.000');
                    break;
            }
        }
    }

    public function colorCellXY(int $col, int $lig, string $couleur): void
    {
        $cell = Coordinate::stringFromColumnIndex($col) . $lig;
        $this->colorCells($cell, $couleur);
    }

    public function colorCells(string $cells, string $couleur): void
    {
        $this->sheet->getStyle($cells)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($couleur);
    }

    public function borderCellsRange(int $col1, int $lig1, int $col2, int $lig2): void
    {
        if ($col1 < $col2) {
            $cell1 = Coordinate::stringFromColumnIndex($col1) . $lig1;
            $cell2 = Coordinate::stringFromColumnIndex($col2) . $lig2;
            $this->borderCells($cell1 . ':' . $cell2);
        }
    }

    public function borderCells(string $cells): void
    {
        $this->sheet->getStyle($cells)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    public function borderOutsiteInside(int $col1, int $lig1, int $col2, int $lig2): void
    {
        if ($col1 <= $col2) {
            $cell1 = Coordinate::stringFromColumnIndex($col1) . $lig1;
            $cell2 = Coordinate::stringFromColumnIndex($col2) . $lig2;


            $this->sheet->getStyle($cell1 . ':' . $cell2)->getBorders()->getInside()->setBorderStyle(Border::BORDER_THIN);
            $this->sheet->getStyle($cell1 . ':' . $cell2)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_MEDIUM);
        }
    }

    public function getRowDimension(int $ligne, int $taille): void
    {
        $this->sheet->getRowDimension($ligne)->setRowHeight($taille);
    }

    public function getColumnDimension(string $col, int $taille): void
    {
        $this->sheet->getColumnDimension($col)->setWidth($taille);
    }

    public function mergeCellsCaR(int $col1, int $lig1, int $col2, int $lig2): void
    {
        if ($col1 <= $col2) {
            $cell1 = Coordinate::stringFromColumnIndex($col1) . $lig1;
            $cell2 = Coordinate::stringFromColumnIndex($col2) . $lig2;
            $this->mergeCells($cell1 . ':' . $cell2);
        }
    }

    public function mergeCells(string $cells, string $behaviour = Worksheet::MERGE_CELL_CONTENT_EMPTY): void
    {
        $this->sheet->mergeCells($cells, $behaviour);
    }

    public function borderBottomCellsRange(int $col1, int $lig1, int $col2, int $lig2, array $array): void
    {
        $color = $array['color'];
        if (str_starts_with($color, '#')) {
            $color = mb_substr($color, 1, mb_strlen($color));
        }

        $cell1 = Coordinate::stringFromColumnIndex($col1) . $lig1;
        $cell2 = Coordinate::stringFromColumnIndex($col2) . $lig2;
        $this->sheet->getStyle($cell1 . ':' . $cell2)->getBorders()->getBottom()->setBorderStyle($array['size'])->getColor()->setARGB('FF' . $color);
    }

    public function getColumnsAutoSize(string $depart, string $fin): void
    {
        foreach (range($depart, $fin) as $columnID) {
            $this->sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
    }

    public function getColumnsAutoSizeInt(int $depart, int $fin): void
    {
        for ($columnID = $depart; $columnID <= $fin; $columnID++) {
            $this->sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnID))->setAutoSize(true);
        }
    }

    public function setSpreadsheet(Spreadsheet $sheet): void
    {
        $this->spreadsheet = $sheet;
        //       if ($mcc === true) {
        //           foreach ($this->spreadsheet->getAllSheets() as $sh) {
        //               $sh->setShowGridlines(false);
        //               //$sh->getProtection()->setSheet(true);
        //           }
        //       }
    }

    public function genereFichier(string $name, bool $grid = false): StreamedResponse
    {
        $this->pageSetup($name);
        foreach ($this->spreadsheet->getAllSheets() as $shh) {
            $sh = $this->spreadsheet->setActiveSheetIndex($this->spreadsheet->getIndex($shh));
            $sh->setShowGridlines($grid);
            $sh->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A3);
            $sh->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
            $sh->getPageSetup()->setFitToPage(true);
            $sh->getPageSetup()->setFitToWidth(1);
            $sh->getPageSetup()->setFitToHeight(1);
            $sh->getPageMargins()->setTop(1);
            $sh->getPageMargins()->setRight(0.75);
            $sh->getPageMargins()->setLeft(0.75);
            $sh->getPageMargins()->setBottom(1);
        }
        $this->setActiveSheetIndex(0);
        $this->setSelectedCells('A1');
        $writer = new Xlsx($this->spreadsheet);

        return new StreamedResponse(
            static function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment;filename="' . $name . '.xlsx"',
            ]
        );
    }

    public function saveFichier(string $name, string $dir): string
    {
        $dir = Tools::formatDir($dir);
        $this->pageSetup($name);
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($dir . $name . '.xlsx');

        return $dir . $name . '.xlsx';
    }

    public function pageSetup(string $name): void
    {
        $this->spreadsheet->getProperties()->setTitle($name);
        $this->spreadsheet->getActiveSheet()->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $this->spreadsheet->getActiveSheet()->setShowGridlines(true); //affichage de la grille
        $this->spreadsheet->getActiveSheet()->setPrintGridlines(false); //affichage de la grille
        $this->spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddHeader('&C&HDocument généré depuis ORéOF');
        $this->spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setOddFooter('&L&B' . $this->spreadsheet->getProperties()->getTitle() . '&RPage &P sur &N');
        $this->spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setEvenHeader('&C&HDocument généré depuis ORéOF');
        $this->spreadsheet->getActiveSheet()->getHeaderFooter()
            ->setEvenFooter('&L&B' . $this->spreadsheet->getProperties()->getTitle() . '&RPage &P sur &N');
    }

    public function setPaperSize(int $size): void
    {
        $this->spreadsheet->getActiveSheet()->getPageSetup()->setPaperSize($size);
    }

    public function createFromTemplate(string $fichier): void
    {
        $inputFileType = 'Xlsx'; // Xlsx - Xml - Ods - Slk - Gnumeric - Csv
        $inputFileName = $this->dir . $fichier;

        /**  Create a new Reader of the type defined in $inputFileType  **/
        try {
            $reader = IOFactory::createReader($inputFileType);
            /**  Load $inputFileName to a Spreadsheet Object  **/
            $this->spreadsheet = $reader->load($inputFileName);
        } catch (Exception $e) {
            throw new \RuntimeException('Le modèle n\'existe pas');
        }
    }

    public function orientationCellXY(int $col, int $ligne, string $orientation): void
    {
        $cell1 = Coordinate::stringFromColumnIndex($col) . $ligne;
        switch ($orientation) {
            case 'vertical':
                $this->sheet->getStyle($cell1)->getAlignment()->setTextRotation(90);
                $this->sheet->getStyle($cell1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                break;
        }
    }

    public function insertNewRowBefore(int $ligne): void
    {
        $this->sheet->insertNewRowBefore($ligne);
    }

    public function removeRow(int $ligne): void
    {
        $this->sheet->removeRow($ligne);
    }

    public function copyFromCellToCell(string $source, string $dest): void
    {
        $this->sheet->setCellValue($dest, $this->sheet->getCell($source)->getValue());
    }

    public function removeColumn(string|int $column, int $nbCol): void
    {
        if (is_int($column)) {
            $column = Coordinate::stringFromColumnIndex($column);
        }
        $this->sheet->removeColumn($column, $nbCol);
    }

    public function getRowAutosize(int $ligne): void
    {
        $this->sheet->getRowDimension($ligne)->setRowHeight(-1);
    }

    public function setOrientationPage(string $orientation = PageSetup::ORIENTATION_LANDSCAPE): void
    {
        $this->sheet->getPageSetup()->setOrientation($orientation);
    }

    public function setPrintArea(string $string): void
    {
        $this->sheet->getPageSetup()->setPrintArea($string);
    }

    public function unMergeCells(string $string): void
    {
        $this->sheet->unmergeCells($string);
    }

    public function setActiveSheetIndex(int $int): void
    {
        $this->spreadsheet->setActiveSheetIndex($int);
        $this->sheet = $this->spreadsheet->getActiveSheet();
    }

    public function setSelectedCells(string $string): void
    {
        $this->sheet->setSelectedCells($string);
    }

    public function getSheetByName(string $page_modele): Worksheet
    {
        return $this->spreadsheet->getSheetByName($page_modele);
    }

    public function addSheet(Worksheet $clonedWorksheet, int $index): void
    {
        $this->spreadsheet->addSheet($clonedWorksheet, $index);
    }

    public function removeSheetByIndex(int $int): void
    {
        $this->spreadsheet->removeSheetByIndex($int);
    }

    public function getSpreadsheet(): ?Spreadsheet
    {
        return $this->spreadsheet;
    }

    public function getSheet(): ?Worksheet
    {
        return $this->sheet;
    }

    public function configSheet(array $array): void
    {
        $sv = new SheetView();
        if (array_key_exists('zoom', $array)) {
            $sv->setZoomScale($array['zoom']);
            $sv->setZoomScaleNormal($array['zoom']);
        }

        $this->sheet->setSheetView(
            $sv
        );

        if (array_key_exists('topLeftCell', $array)) {
            $this->sheet->setTopLeftCell($array['topLeftCell']);
        }
    }

    public function setRangeStyle(string $string, array $array): void
    {
        $this->sheet->getStyle($string)->applyFromArray($array);
    }

    public function cellStyle(string $string, array $array): void
    {
        $this->sheet->getStyle($string)->applyFromArray($array);
    }

    public function insertNewColumnBefore(int|string $colUe): void
    {
        if (is_int($colUe)) {
            $colUe = Coordinate::stringFromColumnIndex($colUe);
        }

        $this->sheet->insertNewColumnBefore($colUe);
    }

    public function setRepeatRows(int $debut, int $fin): void
    {
        $this->sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($debut, $fin);
    }

    /**
     * Mise en forme d'une cellule avec une différence entre deux valeurs
     */
    public function writeCellXYDiff(
        int|string $col,
        int $row,
        ?DiffObject $diffObject,
        array $options = []
    ): void {
        if ($diffObject !== null) {

            if (is_string($col)) {
                $col = (int)Coordinate::columnIndexFromString($col);
            }

            if ($diffObject->isDifferent()) {
                if(($options['withLogs'] ?? false) === true) {
                    if(!in_array($options['parcours_id'], LogValue::$logArray, true)) {
                        LogValue::$logArray[] = $options['parcours_id'];
                    }
                }

                $richText = new RichText();
                if ($diffObject->original !== null && $diffObject->original !== '') {
                    $ancienneValeur = $richText->createTextRun($diffObject->original);
                    $ancienneValeur->getFont()?->setStrikethrough(true);
                    $ancienneValeur->getFont()?->setColor(new Color(Color::COLOR_RED));
                }

                if(($options['withNewLine'] ?? false) === true){
                    $richText->createTextRun("\n");
                    $this->sheet->getCell([$col, $row])->getStyle()->getAlignment()->setWrapText(true);
                    $currentSize = $this->sheet->getRowDimension($row)->getRowHeight();
                    $this->getRowDimension($row, $currentSize * 2);
                }   

                if ($diffObject->new !== null && $diffObject->new !== '') {
                    $colorNew = new Color(Color::COLOR_DARKGREEN);
                    if(($options['withLighterGreen'] ?? false) === true){
                        // vert clair
                        $colorNew = new Color('34EB67');
                    }

                    $nouvelleValeur = $richText->createTextRun($diffObject->new);
                    $nouvelleValeur->getFont()?->setStrikethrough(false);
                    $nouvelleValeur->getFont()?->setBold(true);
                    $nouvelleValeur->getFont()?->setColor($colorNew);
                }

                $this->sheet->setCellValue([$col, $row], $richText);
            } else {
                $this->writeCellXY($col, $row, $diffObject->original, $options);
            }
        }

    }
}
