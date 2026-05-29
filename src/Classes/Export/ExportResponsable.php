<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/Classes/Export/ExportResponsable.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 20/03/2025 15:38
 */

namespace App\Classes\Export;

use App\Classes\Excel\ExcelWriter;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportResponsable
{

    private string $dir;
    private string $filename;

    public function __construct(
        protected FormationRepository $formationRepository,
        protected DpeParcoursRepository $dpeParcoursRepository,
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    public function prepareExport(array $formations): void
    {
        $this->excelWriter->nouveauFichier('Export CAP');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY(3, 1, 'Mention');
        $this->excelWriter->writeCellXY(4, 1, 'Parcours');
        $this->excelWriter->writeCellXY(5, 1, 'Resp. Formation');
        $this->excelWriter->writeCellXY(6, 1, 'Co. Resp. Formation');
        $this->excelWriter->writeCellXY(7, 1, 'Resp. Parcours');
        $this->excelWriter->writeCellXY(8, 1, 'Co. Resp. Parcours');


        $ligne = 1;
        foreach ($formations as $idFormation) {
            $dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            if ($dpeParcours !== null) {
                $parcours = $dpeParcours->getParcours();
                $formation = $dpeParcours->getParcours()?->getFormation();
                if ($parcours !== null && $formation !== null) {
                    $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
                    $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
                    $this->excelWriter->writeCellXY(3, $ligne, $formation->getMention()?->getLibelle());
                    $this->excelWriter->writeCellXY(4, $ligne, $parcours->getLibelle());
                    $this->excelWriter->writeCellXY(5, $ligne, $formation->getResponsableMention()?->getDisplay());
                    $this->excelWriter->writeCellXY(6, $ligne, $formation->getCoResponsable()?->getDisplay());
                    $this->excelWriter->writeCellXY(7, $ligne, $parcours->getRespParcours()?->getDisplay());
                    $this->excelWriter->writeCellXY(8, $ligne, $parcours->getRespParcours()?->getDisplay());
                    $ligne++;
                }
            }
        }
        $this->excelWriter->getColumnsAutoSize('A', 'P');

        $this->fileName = Tools::FileName('EXPORT-Responsable - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function exportLink(array $formations): string
    {
        $this->prepareExport($formations);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }
}
