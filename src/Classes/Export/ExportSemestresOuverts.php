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
use App\Classes\GetDpeParcours;
use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportSemestresOuverts implements ExportInterface
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected FormationRepository $formationRepository,
    )
    {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    public function export(CampagneCollecte $anneeUniversitaire): StreamedResponse
    {
        $this->prepareExport($anneeUniversitaire);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void
    {
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);
        $this->excelWriter->nouveauFichier('Export Semestres ouverts');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY('A', 1, 'Composante');
        $this->excelWriter->writeCellXY('B', 1, 'Type de diplôme');
        $this->excelWriter->writeCellXY('C', 1, 'Mention');
        $this->excelWriter->writeCellXY('D', 1, 'Parcours');
        $this->excelWriter->writeCellXY('E', 1, 'Responsable mention');
        $this->excelWriter->writeCellXY('F', 1, 'Responsable parcours');
        $this->excelWriter->writeCellXY('G', 1, 'Etat parcours/mention');
        $this->excelWriter->writeCellXY('H', 1, 'Semestre 1');
        $this->excelWriter->writeCellXY('I', 1, 'Semestre 2');
        $this->excelWriter->writeCellXY('J', 1, 'Semestre 3');
        $this->excelWriter->writeCellXY('K', 1, 'Semestre 4');
        $this->excelWriter->writeCellXY('L', 1, 'Semestre 5');
        $this->excelWriter->writeCellXY('M', 1, 'Semestre 6');
        $this->excelWriter->writeCellXY('N', 1, '# Mention');
        $this->excelWriter->writeCellXY('O', 1, '# parcours');
        $this->excelWriter->writeCellXY('P', 1, 'Type du Parcours');


        $ligne = 2;
        foreach ($formations as $formation) {
            /** @var Parcours $parcours */
            foreach ($formation->getParcours() as $parcours) {
                //Composante	Type de diplôme	mention	parcours	état	remplissage	nom responsable
                $this->excelWriter->writeCellXY('A', $ligne, $formation->getComposantePorteuse()?->getLibelle());
                $this->excelWriter->writeCellXY('B', $ligne, $formation->getTypeDiplome()?->getLibelle());
                $this->excelWriter->writeCellXY('C', $ligne, $formation->getDisplay());
                $this->excelWriter->writeCellXY('E', $ligne, $formation->getResponsableMention()?->getDisplay());

                if ($formation->isHasParcours()) {
                    $this->excelWriter->writeCellXY('D', $ligne, $parcours->getDisplay());
                    $this->excelWriter->writeCellXY('F', $ligne, $parcours->getRespParcours()?->getDisplay());

                } else {
                    $this->excelWriter->writeCellXY('D', $ligne, 'N/A');
                    $this->excelWriter->writeCellXY('F', $ligne, 'N/A');
                }

                $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                $this->excelWriter->writeCellXY('G', $ligne, $dpeParcours?->getEtatReconduction()?->getLibelle());

                // pour chaque semestre, on récupère les infos
                $semestres = $parcours->getSemestreParcours();
                /** @var SemestreParcours $semestre */
                foreach ($semestres as $semestre) {
                    $semestreIndex = $semestre->getOrdre() - 1; // Semestre 1 est à l'index 0
                    $this->excelWriter->writeCellXY(chr(72 + $semestreIndex), $ligne, $semestre->getSemestre()?->isNonDispense() || !$semestre->isOuvert() ? 'Fermé' : 'Ouvert');
                }

                $this->excelWriter->writeCellXY('N', $ligne, $formation->getId());
                $this->excelWriter->writeCellXY('O', $ligne, $parcours->getId());

                $typeParcoursTxt = "";
                if($parcours->getTypeParcours()->value !== 'classique') {
                    $typeParcoursTxt = $parcours->getTypeParcours()->libelle();
                }
                $this->excelWriter->writeCellXY('P', $ligne, $typeParcoursTxt);
                $this->excelWriter->getColumnsAutoSize('A', 'P');
                $ligne++;

            }
        }


        $this->fileName = Tools::FileName('CAP - Semestre - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function exportLink(CampagneCollecte $campagneCollecte): string
    {
        $this->prepareExport($campagneCollecte);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }
}
