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
use App\TypeDiplome\TypeDiplomeResolver;
use App\Utils\Tools;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportFiabilisation
{
    private const TEMPLATE = 'exports/fiabilisation.html.twig';

    private string $fileName;
    private string $dir;
    /** @var list<array<string, mixed>> */
    private array $lignes = [];
    /** @var array<string, ?string> Données de base de la ligne courante */
    private array $base = [];

    public function __construct(
        protected TypeDiplomeResolver   $typeDiplomeResolver,
        protected ExcelWriter           $excelWriter,
        ProjectDirProvider              $projectDirProvider,
        protected FormationRepository   $formationRepository,
        protected DpeParcoursRepository $dpeParcoursRepository,
        protected SpreadsheetRenderer   $spreadsheetRenderer,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        array $formations,
    ): void {
        $this->lignes = [];

        foreach ($formations as $idFormation) {
            $dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            if ($dpeParcours !== null) {
                $parcours = $dpeParcours->getParcours();
                $formation = $dpeParcours->getParcours()?->getFormation();
                if ($formation !== null && $parcours !== null) {
                    $typeD = $this->typeDiplomeResolver->fromFormation($formation);
                    $this->base = [
                        'composante'  => $formation->getComposantePorteuse()?->getLibelle(),
                        'typeDiplome' => $formation->getTypeDiplome()?->getLibelle(),
                        'mention'     => $formation->getDisplay(),
                        'parcours'    => $parcours->isParcoursDefaut() === false ? $parcours->getLibelle() : 'Pas de parcours',
                    ];

                    //récuération de la structure et des EC
                    $dto = $typeD->calculStructureParcours($parcours);
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
                    }
                }
            }
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $this->lignes])
        );

        $this->fileName = (string) Tools::FileName('EXPORT-FIABILISATION - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
            $this->lignes[] = $this->base + [
                'codeDip'      => $semestreParcours?->getCodeApogeeDiplome(),
                'vdi'          => $semestreParcours?->getCodeApogeeVersionDiplome(),
                'codeEtape'    => $semestreParcours?->getCodeApogeeEtapeAnnee(),
                'vet'          => $semestreParcours?->getCodeApogeeEtapeVersion(),
                'semestre'     => $semestreParcours?->getSemestre()?->display(),
                'codeSemestre' => $semestreParcours?->getSemestre()?->getCodeApogee(),
                'ue'           => $ue->ue->display(),
                'codeUe'       => $ue->ue->getCodeApogee(),
                'idFiche'      => $ec->elementConstitutif->displayId(),
                'ficheMatiere' => $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? '-',
                'codeElement'  => $ec->elementConstitutif->displayCodeApogee(),
                'typeEc'       => $typeEc !== '' ? $typeEc : ($ec->elementConstitutif->getNatureUeEc()?->getLibelle() ?? 'erreur type'),
                'matiMatm'     => $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? '-',
                'ects'         => $ec->heuresEctsEc->ects,
            ];
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
}
