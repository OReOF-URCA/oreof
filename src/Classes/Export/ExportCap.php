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
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCap
{
    private const TEMPLATE = 'exports/cap.html.twig';

    private string $fileName;
    private string $dir;
    /** @var list<array<string, mixed>> */
    private array $lignes = [];
    /** @var array<string, ?string> Données de base de la ligne courante (composante, type, mention, parcours) */
    private array $base = [];
    private string $currentSemestre = '';
    private DpeParcours $dpeParcours;
    private ?Historique $historique = null;

    public function __construct(
        protected GetHistorique       $getHistorique,
        protected TypeDiplomeResolver $typeDiplomeResolver,
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider            $projectDirProvider,
        protected FormationRepository $formationRepository,
        protected DpeParcoursRepository $dpeParcoursRepository,
        protected SpreadsheetRenderer $spreadsheetRenderer,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        array $formations,
    ): void {
        $this->lignes = [];

        foreach ($formations as $idFormation) {
            $this->dpeParcours = $this->dpeParcoursRepository->find($idFormation);
            $this->historique = $this->getHistorique->getHistoriqueParcoursLastStep($this->dpeParcours, 'soumis_cfvu');
            if ($this->dpeParcours !== null) {
                $parcours = $this->dpeParcours->getParcours();
                $formation = $this->dpeParcours->getParcours()?->getFormation();
                $typeDiplome = $this->typeDiplomeResolver->fromFormation($formation);
                if ($formation !== null && $parcours !== null) {
                    $this->base = [
                        'composante'  => $formation->getComposantePorteuse()?->getLibelle(),
                        'typeDiplome' => $formation->getTypeDiplome()?->getLibelle(),
                        'mention'     => $formation->getDisplay(),
                        'parcours'    => $formation->isHasParcours() ? $parcours->getLibelle() : 'Pas de parcours',
                    ];

                    //récuération de la structure et des EC
                    $dto = $typeDiplome->calculStructureParcours($parcours);
                    foreach ($dto->semestres as $ordre => $sem) {
                        $this->currentSemestre = 'S' . $ordre;
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
                    }
                }
            }
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $this->lignes])
        );

        $this->fileName = (string) Tools::FileName('EXPORT-CAP - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
            $this->lignes[] = $this->base + [
                'codeDip'      => $semestreParcours?->getCodeApogeeDiplome(),
                'vdi'          => $semestreParcours?->getCodeApogeeVersionDiplome(),
                'codeEtape'    => $semestreParcours?->getCodeApogeeEtapeAnnee(),
                'vet'          => $semestreParcours?->getCodeApogeeEtapeVersion(),
                'ficheMatiere' => $ec->elementConstitutif->getFicheMatiere()?->getLibelle() ?? '-',
                'codeElement'  => $ec->elementConstitutif->displayCodeApogee(),
                'cm'           => $ec->heuresEctsEc->cmPres,
                'td'           => $ec->heuresEctsEc->tdPres,
                'tp'           => $ec->heuresEctsEc->tpPres,
                'matiMatm'     => $ec->elementConstitutif->getFicheMatiere()?->getTypeApogee() ?? '-',
                'option'       => $option ? 'Choix/option' : 'Obligatoire',
                'semestre'     => $this->currentSemestre,
                'etatDpe'      => count($this->dpeParcours->getEtatValidation()) > 0 ? array_key_first($this->dpeParcours->getEtatValidation()) : '-',
                'dateCfvu'     => $this->historique?->getDate()?->format('d/m/Y') ?? '-',
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
