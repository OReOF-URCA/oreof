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
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSemestresOuverts implements ExportInterface
{
    private const TEMPLATE = 'exports/semestres_ouverts.html.twig';

    private string $fileName;
    private string $dir;

    public function __construct(
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider            $projectDirProvider,
        protected FormationRepository $formationRepository,
        protected SpreadsheetRenderer $spreadsheetRenderer,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    public function export(CampagneCollecte $anneeUniversitaire): StreamedResponse
    {
        $this->prepareExport($anneeUniversitaire);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);

        $lignes = [];
        foreach ($formations as $formation) {
            /** @var Parcours $parcours */
            foreach ($formation->getParcours() as $parcours) {
                $dpeParcours = GetDpeParcours::getFromParcours($parcours);

                // Colonnes Semestre 1..6 (H..M) : vides par défaut.
                $semestres = array_fill(0, 6, '');
                /** @var SemestreParcours $semestre */
                foreach ($parcours->getSemestreParcours() as $semestre) {
                    $index = $semestre->getOrdre() - 1; // Semestre 1 est à l'index 0
                    if ($index >= 0 && $index < 6) {
                        $semestres[$index] = ($semestre->getSemestre()?->isNonDispense() || !$semestre->isOuvert()) ? 'Fermé' : 'Ouvert';
                    }
                }

                $lignes[] = [
                    'composante'   => $formation->getComposantePorteuse()?->getLibelle(),
                    'typeDiplome'  => $formation->getTypeDiplome()?->getLibelle(),
                    'mention'      => $formation->getDisplay(),
                    'parcours'     => $formation->isHasParcours() ? $parcours->getDisplay() : 'N/A',
                    'respMention'  => $formation->getResponsableMention()?->getDisplay(),
                    'respParcours' => $formation->isHasParcours() ? $parcours->getRespParcours()?->getDisplay() : 'N/A',
                    'etat'         => $dpeParcours?->getEtatReconduction()?->getLibelle(),
                    'semestres'    => $semestres,
                    'idMention'    => $formation->getId(),
                    'idParcours'   => $parcours->getId(),
                ];
            }
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $lignes])
        );

        $this->fileName = (string) Tools::FileName('CAP - Semestre - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function exportLink(CampagneCollecte $campagneCollecte): string
    {
        $this->prepareExport($campagneCollecte);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }
}
