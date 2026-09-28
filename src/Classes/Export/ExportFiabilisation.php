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
use App\DTO\StructureEc;
use App\DTO\StructureUe;
use App\Entity\SemestreParcours;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Service\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportFiabilisation
{
    private string $fileName;
    private string $dir;
    private int $ligne = 2;
    private array $data;

    private array $metadataParcours = [
        'currentParcoursId' => null,
        'currentParcoursType' => null
    ];

    public function __construct(
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

        $this->excelWriter->nouveauFichier('Export Fiabilisation');
        $this->excelWriter->setActiveSheetIndex(0);

        //         //Version diplôme - Cursus LMD (lib.)	Diplôme (lib.)	Version diplôme (lib.)	Diplôme (code)	Version diplôme (code)	Version diplôme - Mention (lib.)	Version diplôme - Mention (code)	ELP (code)	ELP (lib.)	ELP (lib. long)	ELP - Composante (code)

        $this->excelWriter->writeCellXY('A', 1, 'Composante');
        $this->excelWriter->writeCellXY('B', 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY('C', 1, 'Mention');
        $this->excelWriter->writeCellXY('D', 1, 'Parcours');
        $this->excelWriter->writeCellXY('E', 1, 'Code Dip.');
        $this->excelWriter->writeCellXY('F', 1, 'VDI');
        $this->excelWriter->writeCellXY('G', 1, 'Code étape');
        $this->excelWriter->writeCellXY('H', 1, 'VET');
        $this->excelWriter->writeCellXY('I', 1, 'Semestre');
        $this->excelWriter->writeCellXY('J', 1, 'Code Semestre');
        $this->excelWriter->writeCellXY('K', 1, 'UE');
        $this->excelWriter->writeCellXY('L', 1, 'Code UE');
        $this->excelWriter->writeCellXY('M', 1, 'Id Fiche EC/matière');
        $this->excelWriter->writeCellXY('N', 1, 'Fiche EC/matière');
        $this->excelWriter->writeCellXY('O', 1, 'Code élément');
        $this->excelWriter->writeCellXY('P', 1, 'Type EC');
        $this->excelWriter->writeCellXY('Q', 1, 'MATI/MATM');
        $this->excelWriter->writeCellXY('R', 1, 'ECTS');
        $this->excelWriter->writeCellXY('S', 1, 'Identifiant');
        $this->excelWriter->writeCellXY('T', 1, 'Type du Parcours');

        $this->ligne = 2;
        foreach ($formations as $idFormation) {
            $dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            if ($dpeParcours !== null) {
                $parcours = $dpeParcours->getParcours();
                $formation = $dpeParcours->getParcours()?->getFormation();
                if ($formation !== null && $parcours !== null) {
                    $typeD = $this->typeDiplomeResolver->getFromFormation($formation);
                    //                    foreach ($formation->getParcours() as $parcours) {
                    $this->data[1] = $formation->getComposantePorteuse()?->getLibelle();
                    $this->data[2] = $formation->getTypeDiplome()?->getLibelle();
                    $this->data[3] = $formation->getDisplay();
                    if ($parcours->isParcoursDefaut() === false) {
                        $this->data[4] = $parcours->getLibelle();
                    } else {
                        $this->data[4] = 'Pas de parcours';
                    }
                    $typeParcoursTxt = "";
                    if($parcours->getTypeParcours()->value !== 'classique') {
                        $typeParcoursTxt = $parcours->getTypeParcours()->libelle();
                    }
                    //récuération de la structure et des EC
                    $dto = $typeD->calculStructureParcours($parcours);
                    $this->metadataParcours['currentParcoursId'] = $parcours->getId();
                    $this->metadataParcours['currentParcoursType'] = $typeParcoursTxt;
                    foreach ($dto->semestres as $sem) {
                        foreach ($sem->ues as $ue) {
                            if ($ue->ue->getNatureUeEc()?->isChoix()) {
                                foreach ($ue->uesEnfants() as $ueEnfant) {

                                    if ($ueEnfant->ue->getNatureUeEc()?->isLibre() === false) {
                                        $this->getEcFromUe($ueEnfant, $sem->semestreParcours);
                                    }
                                }
                            } elseif ($ue->ue->getNatureUeEc()?->isLibre() === false) {
                                $this->getEcFromUe($ue, $sem->semestreParcours);
                            }
                        }

                        $this->excelWriter->getColumnsAutoSize('A', 'T');
                    }
                    //}
                }
            }
        }

        $this->fileName = Tools::FileName('EXPORT-FIABILISATION - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    private function getEcFromUe(StructureUe $ue, ?SemestreParcours $codeApogeeParcours): void
    {

        foreach ($ue->elementConstitutifs as $ec) {
            if ($ec->elementConstitutif->getNatureUeEc()?->isChoix()) {
                $this->getEc($ue, $ec, $codeApogeeParcours, 'EC Parent');
                foreach ($ec->elementsConstitutifsEnfants as $ecEnfant) {
                    $this->getEc($ue, $ecEnfant, $codeApogeeParcours);
                }
            } else {
                $this->getEc($ue, $ec, $codeApogeeParcours);
            }
        }
    }

    private function getEc(StructureUe $ue, StructureEc $ec, ?SemestreParcours $semestreParcours, string $typeEc = ''): void
    {

        if ($ec->elementConstitutif->getNatureUeEc()?->isLibre() === false) {
            $this->writeDebutLigne($this->ligne, $this->data);
            $this->excelWriter->writeCellXY(5, $this->ligne, $semestreParcours?->getCodeApogeeDiplome());
            $this->excelWriter->writeCellXY(6, $this->ligne, $semestreParcours?->getCodeApogeeVersionDiplome());
            $this->excelWriter->writeCellXY(7, $this->ligne, $semestreParcours?->getCodeApogeeEtapeAnnee());
            $this->excelWriter->writeCellXY(8, $this->ligne, $semestreParcours?->getCodeApogeeEtapeVersion());
            $this->excelWriter->writeCellXY(9, $this->ligne, $semestreParcours?->getSemestre()?->display());
            $this->excelWriter->writeCellXY(10, $this->ligne, $semestreParcours?->getSemestre()?->getCodeApogee());
            $this->excelWriter->writeCellXY(11, $this->ligne, $ue->ue->display());
            $this->excelWriter->writeCellXY(12, $this->ligne, $ue->ue->getCodeApogee());
            $this->excelWriter->writeCellXY(13, $this->ligne, $ec->elementConstitutif->displayId());
            $this->excelWriter->writeCellXY(14, $this->ligne, $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? '-');
            $this->excelWriter->writeCellXY(15, $this->ligne, $ec->elementConstitutif->displayCodeApogee());
            if ($typeEc !== '') {
                $this->excelWriter->writeCellXY(16, $this->ligne, $typeEc);
            } else {
                $this->excelWriter->writeCellXY(16, $this->ligne, $ec->elementConstitutif->getNatureUeEc()?->getLibelle() ?? 'erreur type');
            }

            $this->excelWriter->writeCellXY(17, $this->ligne, $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? '-');
            $this->excelWriter->writeCellXY(18, $this->ligne, $ec->heuresEctsEc->ects);
            $this->excelWriter->writeCellXY(19, $this->ligne, $this->metadataParcours['currentParcoursId'] ?? "");
            $this->excelWriter->writeCellXY(20, $this->ligne, $this->metadataParcours['currentParcoursType'] ?? "");
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
