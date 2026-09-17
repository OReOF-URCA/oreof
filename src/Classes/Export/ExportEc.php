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
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportEc implements ExportInterface
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique        $getHistorique,
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected ElementConstitutifRepository $elementConstitutifRepository,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $ecs = $this->elementConstitutifRepository->findWithParcours();
        $this->excelWriter->nouveauFichier('Export SEIP');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY(3, 1, 'Mention');
        $this->excelWriter->writeCellXY(4, 1, 'Parcours');
        $this->excelWriter->writeCellXY(5, 1, 'Semestre');
        $this->excelWriter->writeCellXY(6, 1, 'N° UE');
        $this->excelWriter->writeCellXY(7, 1, 'Initulé UE');
        $this->excelWriter->writeCellXY(8, 1, 'N° EC');
        $this->excelWriter->writeCellXY(9, 1, 'Initulé EC');
        $this->excelWriter->writeCellXY(10, 1, 'Fiche EC/matière');
        $this->excelWriter->writeCellXY(11, 1, 'Type EC');
        $this->excelWriter->writeCellXY(12, 1, 'Référent');
        $this->excelWriter->writeCellXY(13, 1, 'Identifiant');
        $this->excelWriter->writeCellXY(14, 1, 'Type du Parcours');

        $ligne = 2;
        /** @var ElementConstitutif $ec */
        foreach ($ecs as $ec) {
            $this->excelWriter->writeCellXY(1, $ligne, $ec->getParcours()?->getFormation()?->getComposantePorteuse()?->getLibelle());
            $this->excelWriter->writeCellXY(2, $ligne, $ec->getParcours()?->getFormation()?->getTypeDiplome()?->getLibelle());
            $this->excelWriter->writeCellXY(3, $ligne, $ec->getParcours()?->getFormation()?->getDisplay());

            if ($ec->getParcours()?->getFormation()?->isHasParcours()) {
                $this->excelWriter->writeCellXY(4, $ligne, $ec->getParcours()?->getLibelle());
            } else {
                $this->excelWriter->writeCellXY(4, $ligne, 'Pas de parcours');
            }
            $this->excelWriter->writeCellXY(5, $ligne, $ec->getUe()?->getSemestre()?->display());
            $this->excelWriter->writeCellXY(6, $ligne, $ec->getUe()?->display($ec->getParcours()));
            $this->excelWriter->writeCellXY(7, $ligne, $ec->getUe()?->getLibelle());
            $this->excelWriter->writeCellXY(8, $ligne, $ec->getCode());
            $this->excelWriter->writeCellXY(9, $ligne, $ec->getLibelle());
            $this->excelWriter->writeCellXY(10, $ligne, $ec->getFicheMatiere()?->getLibelle());
            $this->excelWriter->writeCellXY(11, $ligne, $ec->getTypeEc()?->getType()->value);
            $this->excelWriter->writeCellXY(12, $ligne, $ec->getFicheMatiere()?->getResponsableFicheMatiere() !== null ? $ec->getFicheMatiere()?->getResponsableFicheMatiere()?->getDisplay() : 'Non défini - RP ou RF');
            $this->excelWriter->writeCellXY(13, $ligne, $ec->getParcours()->getId() ?? "");

            $typeParcoursTxt = "";
            if($ec->getParcours() !== null) {
                if($ec->getParcours()->getTypeParcours()->value !== 'classique') {
                    $typeParcoursTxt = $ec->getParcours()->getTypeParcours()->libelle();
                }
            }
            $this->excelWriter->writeCellXY(14, $ligne, $typeParcoursTxt);

            $this->excelWriter->getColumnsAutoSize('A', 'R');
            $ligne++;
        }

        $this->fileName = Tools::FileName('Export - EC - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
