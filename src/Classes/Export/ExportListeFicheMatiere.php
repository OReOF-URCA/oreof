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
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportListeFicheMatiere implements ExportInterface
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique        $getHistorique,
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected FicheMatiereRepository $ficheMatiereRepository,
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
        $this->excelWriter->nouveauFichier('Export Fiches Matieres');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Id');
        $this->excelWriter->writeCellXY(2, 1, 'Fiche EC/matière');
        $this->excelWriter->writeCellXY(3, 1, 'Référent');
        $this->excelWriter->writeCellXY(4, 1, 'Complet ?');
        $this->excelWriter->writeCellXY(5, 1, 'Utilisée ?');
        $this->excelWriter->writeCellXY(6, 1, 'Parcours porteur');
        $this->excelWriter->writeCellXY(7, 1, 'Formation');

        $ligne = 2;
        foreach ($fiches as $fiche) {
            $this->excelWriter->writeCellXY(1, $ligne, $fiche->getId());
            $this->excelWriter->writeCellXY(2, $ligne, $fiche->getLibelle());
            $this->excelWriter->writeCellXY(3, $ligne, $fiche->getResponsableFicheMatiere() !== null ? $fiche->getResponsableFicheMatiere()->getDisplay() : '');
            $this->excelWriter->writeCellXY(4, $ligne, $fiche->remplissageBrut()->isFull() ? 'Complet' : 'Incomplet');
            $this->excelWriter->writeCellXY(5, $ligne, $fiche->getElementConstitutifs()->count());
            $this->excelWriter->writeCellXY(6, $ligne,
            $fiche->isHorsDiplome() === true ? 'Hors diplôme' : ($fiche->getParcours() !== null ? $fiche->getParcours()->getLibelle() : ''
            ));
            $this->excelWriter->writeCellXY(7, $ligne,
                $fiche->isHorsDiplome() === true ? 'Hors diplôme' : ($fiche->getParcours() !== null && $fiche->getParcours()->getFormation() !== null ? $fiche->getParcours()->getFormation()->getDisplayLong() : ''
                ));

            $this->excelWriter->getColumnsAutoSize('A', 'M');
            $ligne++;
        }

        $this->fileName = Tools::FileName('Export - Fiches Matières - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
