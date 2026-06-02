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
use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\DpeParcours;
use App\Entity\Historique;
use App\Entity\SemestreParcours;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCap
{
    private string $fileName;
    private string $dir;
    private int $ligne = 2;
    private array $data;
    private DpeParcours $dpeParcours;
    private ?Historique $historique = null;

    public function __construct(
        protected GetHistorique $getHistorique,
        protected TypeDiplomeResolver $typeDiplomeResolver,
        protected ExcelWriter             $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected FormationRepository     $formationRepository,
        protected DpeParcoursRepository   $dpeParcoursRepository,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        array $formations,
    ): void {

        $this->excelWriter->nouveauFichier('Export CAP');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY(3, 1, 'Mention');
        $this->excelWriter->writeCellXY(4, 1, 'Parcours');
        $this->excelWriter->writeCellXY(5, 1, 'Code Dip.');
        $this->excelWriter->writeCellXY(6, 1, 'VDI');
        $this->excelWriter->writeCellXY(7, 1, 'Code étape');
        $this->excelWriter->writeCellXY(8, 1, 'VET');
        $this->excelWriter->writeCellXY(9, 1, 'Fiche EC/matière');
        $this->excelWriter->writeCellXY(10, 1, 'Code élément');
        $this->excelWriter->writeCellXY(11, 1, 'CM');
        $this->excelWriter->writeCellXY(12, 1, 'TD');
        $this->excelWriter->writeCellXY(13, 1, 'TP');
        $this->excelWriter->writeCellXY(14, 1, 'MATI/MATM');
        $this->excelWriter->writeCellXY(15, 1, 'Option');
        $this->excelWriter->writeCellXY(16, 1, 'Semestre');
        $this->excelWriter->writeCellXY(17, 1, 'Etat DPE');
        $this->excelWriter->writeCellXY(18, 1, 'Date CFVU');

        $this->ligne = 2;
        foreach ($formations as $idFormation) {
            $this->dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            $this->historique = $this->getHistorique->getHistoriqueParcoursLastStep($this->dpeParcours, 'soumis_cfvu');
            if ($this->dpeParcours !== null) {
                $parcours = $this->dpeParcours->getParcours();
                $formation = $this->dpeParcours->getParcours()?->getFormation();
                $typeDiplome = $this->typeDiplomeResolver->fromFormation($formation);
                if ($formation !== null && $parcours !== null) {
//                foreach ($formation->getParcours() as $parcours) {
                    $this->data[1] = $formation->getComposantePorteuse()?->getLibelle();
                    $this->data[2] = $formation->getTypeDiplome()?->getLibelle();
                    $this->data[3] = $formation->getDisplay();
                    if ($formation->isHasParcours()) {
                        $this->data[4] = $parcours->getLibelle();
                    } else {
                        $this->data[4] = 'Pas de parcours';
                    }

                    //récuération de la structure et des EC
                    $dto = $typeDiplome->calculStructureParcours($parcours);
                    foreach ($dto->semestres as $ordre => $sem) {
                        $this->data[5] = 'S'.$ordre;
                        foreach ($sem->ues as $ue) {
                            if ($ue->ue->getNatureUeEc()?->isChoix()) {
                                foreach ($ue->uesEnfants() as $ueEnfant) {
                                    if ($ueEnfant->ue->getNatureUeEc()?->isLibre() === false) {
                                        $this->getEcFromUe($ueEnfant, $sem->semestreParcours, true);
                                    }
                                }
                            } elseif ($ue->ue->getNatureUeEc()?->isLibre() === false) {
                                $this->getEcFromUe($ue, $sem->semestreParcours);
                            }
                        }

                        $this->excelWriter->getColumnsAutoSize('A', 'P');
                    }
                }
            }
        }

        $this->fileName = Tools::FileName('EXPORT-CAP - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    private function getEcFromUe(StructureUe $ue, ?SemestreParcours $codeApogeeParcours, bool $option = false): void
    {

        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getNatureUeEc()?->isChoix()) {

                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $this->getEc($ecEnfant, $codeApogeeParcours, true);
                }
            } else {
                $this->getEc($ec, $codeApogeeParcours, $option);
            }
        }
    }

    private function getEc(StructureEc $ec, ?SemestreParcours $semestreParcours, bool $option = false): void
    {

        if ($ec->elementConstitutif->getNatureUeEc()?->isLibre() === false) {
            $this->writeDebutLigne($this->ligne, $this->data);
            $this->excelWriter->writeCellXY(5, $this->ligne, $semestreParcours?->getCodeApogeeDiplome());
            $this->excelWriter->writeCellXY(6, $this->ligne, $semestreParcours?->getCodeApogeeVersionDiplome());
            $this->excelWriter->writeCellXY(7, $this->ligne, $semestreParcours?->getCodeApogeeEtapeAnnee());
            $this->excelWriter->writeCellXY(8, $this->ligne, $semestreParcours?->getCodeApogeeEtapeVersion());
            $this->excelWriter->writeCellXY(9, $this->ligne, $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? '-');
            $this->excelWriter->writeCellXY(10, $this->ligne, $ec->elementConstitutif->displayCodeApogee());
            $this->excelWriter->writeCellXY(11, $this->ligne, $ec->heuresEctsEc->cmPres);
            $this->excelWriter->writeCellXY(12, $this->ligne, $ec->heuresEctsEc->tdPres);
            $this->excelWriter->writeCellXY(13, $this->ligne, $ec->heuresEctsEc->tpPres);
            $this->excelWriter->writeCellXY(14, $this->ligne, $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? '-');
            $this->excelWriter->writeCellXY(15, $this->ligne, $option ? 'Choix/option' : 'Obligatoire');
            $this->excelWriter->writeCellXY(16, $this->ligne, $this->data[5]);
            if (count($this->dpeParcours->getEtatValidation()) > 0) {
                //récupérer le premier item du tableau (key en texte et pas 0)
                $this->excelWriter->writeCellXY(17, $this->ligne, array_key_first($this->dpeParcours->getEtatValidation()));
            } else {
                $this->excelWriter->writeCellXY(17, $this->ligne, '-');
            }
            $this->excelWriter->writeCellXY(18, $this->ligne, $this->historique?->getDate()?->format('d/m/Y') ?? '-');
            $this->ligne++;
        }
    }


    public function export(array $formations): StreamedResponse
    {
        $this->prepareExport($formations);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportLink(array $formations): string
    {
        $this->prepareExport($formations);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }

    private function writeDebutLigne(int $ligne, $data): void
    {
        foreach ($data as $key => $value) {
            $this->excelWriter->writeCellXY($key, $ligne, $value);
        }
    }
}
