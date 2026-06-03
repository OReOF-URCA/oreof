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
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSeip implements ExportInterface
{
    private const TEMPLATE = 'exports/seip.html.twig';

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

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);

        $lignes = [];
        foreach ($formations as $formation) {
            /** @var Parcours $parcours */
            foreach ($formation->getParcours() as $parcours) {
                $lignes[] = [
                    'composante'      => $formation->getComposantePorteuse()?->getLibelle(),
                    'typeDiplome'     => $formation->getTypeDiplome()?->getLibelle(),
                    'mention'         => $formation->getDisplay(),
                    'parcours'        => $formation->isHasParcours() ? $parcours->getLibelle() : '',
                    'modalites'       => $parcours->getModalitesEnseignement()?->libelle(),
                    'stage'           => $parcours->isHasStage() ? 'Oui' : 'Non',
                    'heuresStage'     => $parcours->getNbHeuresStages(),
                    'modalitesStage'  => CleanTexte::cleanTextArea($parcours->getStageText()),
                    'projet'          => $parcours->isHasProjet() ? 'Oui' : 'Non',
                    'heuresProjet'    => $parcours->getNbHeuresProjet(),
                    'modalitesProjet' => CleanTexte::cleanTextArea($parcours->getProjetText()),
                    'terMemoire'      => $parcours->isHasMemoire() ? 'Oui' : 'Non',
                    'modalitesTer'    => CleanTexte::cleanTextArea($parcours->getMemoireText()),
                ];
            }
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, ['lignes' => $lignes])
        );

        $this->fileName = (string) Tools::FileName('Export SEIP - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
