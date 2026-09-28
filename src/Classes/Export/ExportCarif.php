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
use App\Entity\CampagneCollecte;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\CleanTexte;
use App\Utils\Tools;
use DateTime;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCarif implements ExportInterface
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected FormationRepository $formationRepository,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);
        $this->excelWriter->createFromTemplate('export_carif.xlsx');
        $this->excelWriter->setActiveSheetIndex(0);
        $ligne = 2;
        foreach ($formations as $formation) {
            foreach ($formation->getParcours() as $parcours) {
                if ($parcours->isAlternance()) {
                    //Composante	Type de diplôme	mention	parcours	état	remplissage	nom responsable
                    $this->excelWriter->writeCellXY('A', $ligne, $formation->getComposantePorteuse()?->getLibelle());
                    $this->excelWriter->writeCellXY('B', $ligne, $formation->getTypeDiplome()?->getLibelle());
                    $this->excelWriter->writeCellXY('C', $ligne, $formation->getDisplay());
                    if ($formation->isHasParcours()) {
                        $this->excelWriter->writeCellXY('D', $ligne, $parcours->getLibelle());
                        $this->excelWriter->writeCellXY('E', $ligne, CleanTexte::cleanTextArea($parcours->getObjectifsParcours()), ['wrap' => true]);
                        $this->excelWriter->writeCellXY('F', $ligne, CleanTexte::cleanTextArea($parcours->getContenuFormation()), ['wrap' => true]);
                        $this->excelWriter->writeCellXY('G', $ligne, $parcours->getRespParcours()?->getDisplay());
                    } else {
                        $this->excelWriter->writeCellXY('E', $ligne, CleanTexte::cleanTextArea($formation->getObjectifsFormation()), ['wrap' => true]);
                        $this->excelWriter->writeCellXY('F', $ligne, CleanTexte::cleanTextArea($formation->getContenuFormation()), ['wrap' => true]);
                        $this->excelWriter->writeCellXY('G', $ligne, $formation->getResponsableMention()?->getDisplay());
                    }

//                    $calcul = new CalculStructureParcours();
//                    $dureeFormation = $calcul->calcul($parcours)->heuresEctsFormation->sommeFormationTotalPres();
//                    $dureeEntreprise = 1607 - $dureeFormation;
//                    unset($calcul);

                    $this->excelWriter->writeCellXY('K', $ligne, $parcours->getModalitesEnseignement()?->value);
                    $this->excelWriter->writeCellXY('I', $ligne, $formation->getNiveauEntree()->libelle());
                    $this->excelWriter->writeCellXY('J', $ligne, $formation->getNiveauSortie()->libelle());
                    $this->excelWriter->writeCellXY('L', $ligne, CleanTexte::cleanTextArea($parcours->getPrerequis()), ['wrap' => true]);
//                    $this->excelWriter->writeCellXY('N', $ligne, $dureeEntreprise);
//                    $this->excelWriter->writeCellXY('O', $ligne, $dureeFormation);
                    $this->excelWriter->writeCellXY('R', $ligne, $parcours->getLocalisation()?->getLibelle());

                    $optionsCellHeader = [
                        'style' => 'HORIZONTAL_CENTER',
                        'valign' => 'VERTICAL_CENTER',
                        'wrap' => true
                    ];
                    $borderHeader = [
                        'borders' => [
                            'outline' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => '000000']
                            ]
                        ],
                        'font' => ['size' => 16]
                    ];
                    $this->excelWriter->writeCellXY('U', 1, 'Identifiant', $optionsCellHeader);
                    $this->excelWriter->writeCellXY('V', 1, 'Type du Parcours', $optionsCellHeader);
                    $this->excelWriter->cellStyle('U1', $borderHeader);
                    $this->excelWriter->cellStyle('V1', $borderHeader);
                    $this->excelWriter->writeCellXY('U', $ligne, $parcours->getId() ?? "");
                    $typeParcoursTxt = "";
                    if($parcours->getTypeParcours()->value !== 'classique') {
                        $typeParcoursTxt = $parcours->getTypeParcours()->libelle();
                    }
                    $this->excelWriter->writeCellXY('V', $ligne, $typeParcoursTxt);
//                    $this->excelWriter->getColumnsAutoSize('A', 'R');
                    $ligne++;
                }
            }
        }


        $this->fileName = Tools::FileName('CARIF - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
