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
use App\Entity\Parcours;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\CleanTexte;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportSeip implements ExportInterface
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
        $this->excelWriter->nouveauFichier('Export SEIP');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY('A', 1, 'Composante');
        $this->excelWriter->writeCellXY('B', 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY('C', 1, 'Mention');
        $this->excelWriter->writeCellXY('D', 1, 'Parcours');
        $this->excelWriter->writeCellXY('E', 1, 'Modalités');
        $this->excelWriter->writeCellXY('F', 1, 'Stage');
        $this->excelWriter->writeCellXY('G', 1, 'Heures Stage');
        $this->excelWriter->writeCellXY('H', 1, 'Modalités Stage');
        $this->excelWriter->writeCellXY('I', 1, 'Projet');
        $this->excelWriter->writeCellXY('J', 1, 'Heures Projet');
        $this->excelWriter->writeCellXY('K', 1, 'Modalités projet');
        $this->excelWriter->writeCellXY('L', 1, 'TER/mémoire');
        $this->excelWriter->writeCellXY('M', 1, 'Modalités TER');
        $this->excelWriter->writeCellXY('N', 1, 'Identifiant');
        $this->excelWriter->writeCellXY('O', 1, 'Type du Parcours');

        $ligne = 2;
        foreach ($formations as $formation) {
            /** @var Parcours $parcours */
            foreach ($formation->getParcours() as $parcours) {
                //Composante	Type de diplôme	mention	parcours	état	remplissage	nom responsable
                $this->excelWriter->writeCellXY('A', $ligne, $formation->getComposantePorteuse()?->getLibelle());
                $this->excelWriter->writeCellXY('B', $ligne, $formation->getTypeDiplome()?->getLibelle());
                $this->excelWriter->writeCellXY('C', $ligne, $formation->getDisplay());
                if ($formation->isHasParcours()) {
                    $this->excelWriter->writeCellXY('D', $ligne, $parcours->getLibelle());
                }
                $this->excelWriter->writeCellXY('E', $ligne, $parcours->getModalitesEnseignement()?->libelle());
                $this->excelWriter->writeCellXY('F', $ligne, $parcours->isHasStage() ? 'Oui' : 'Non');
                $this->excelWriter->writeCellXY('G', $ligne, $parcours->getNbHeuresStages());
                $this->excelWriter->writeCellXY('H', $ligne, CleanTexte::cleanTextArea($parcours->getStageText()), ['wrap' => true]);
                $this->excelWriter->writeCellXY('I', $ligne, $parcours->isHasProjet() ? 'Oui' : 'Non');
                $this->excelWriter->writeCellXY('J', $ligne, $parcours->getNbHeuresProjet());
                $this->excelWriter->writeCellXY('K', $ligne, CleanTexte::cleanTextArea($parcours->getProjetText()), ['wrap' => true]);
                $this->excelWriter->writeCellXY('L', $ligne, $parcours->isHasMemoire()? 'Oui' : 'Non');
                $this->excelWriter->writeCellXY('M', $ligne, CleanTexte::cleanTextArea($parcours->getMemoireText()), ['wrap' => true]);
                $this->excelWriter->writeCellXY('N', $ligne, $parcours->getId());

                $typeParcoursTxt = "";
                if($parcours->getTypeParcours()->value !== 'classique') {
                    $typeParcoursTxt = $parcours->getTypeParcours()->libelle();
                }
                $this->excelWriter->writeCellXY('O', $ligne, $typeParcoursTxt);
                $ligne++;
            }
        }


        $this->fileName = Tools::FileName('Export SEIP - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
