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
use App\Classes\GetHistorique;
use App\Entity\CampagneCollecte;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use Davidannebicque\HtmlToSpreadsheetBundle\Spreadsheet\SpreadsheetRenderer;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportRegime implements ExportInterface
{
    private const TEMPLATE = 'exports/regime.html.twig';

    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique       $getHistorique,
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

        $regimeCases = RegimeInscriptionEnum::cases();
        $regimes = array_map(static fn(RegimeInscriptionEnum $r) => $r->value, $regimeCases);

        $lignes = [];
        foreach ($formations as $formation) {
            foreach ($formation->getParcours() as $parcours) {
                if ($formation->isHasParcours()) {
                    $parcoursLibelle = $parcours->getLibelle();
                    $lieu = $parcours->getLocalisation()?->getLibelle();
                } else {
                    $parcoursLibelle = 'Pas de parcours';
                    $texte = '';
                    foreach ($formation->getLocalisationMention() as $localisation) {
                        $texte .= $localisation->getLibelle() . ', ';
                    }
                    $lieu = substr($texte, 0, -2);
                }

                $dpeParcours = GetDpeParcours::getFromParcours($parcours);

                $regimeChecks = [];
                foreach ($regimeCases as $regime) {
                    $regimeChecks[] = in_array($regime, $parcours->getRegimeInscription()) ? 'X' : '';
                }

                $lignes[] = [
                    'composante'          => $formation->getComposantePorteuse()?->getLibelle(),
                    'typeDiplome'         => $formation->getTypeDiplome()?->getLibelle(),
                    'mention'             => $formation->getDisplay(),
                    'parcours'            => $parcoursLibelle,
                    'lieu'                => $lieu,
                    'respMention'         => $formation->getResponsableMention()?->getDisplay(),
                    'respMentionMail'     => $formation->getResponsableMention()?->getEmail(),
                    'respMentionLogin'    => $formation->getResponsableMention()?->getUsername(),
                    'coRespMention'       => $formation->getCoResponsable()?->getDisplay(),
                    'coRespMentionMail'   => $formation->getCoResponsable()?->getEmail(),
                    'coRespMentionLogin'  => $formation->getCoResponsable()?->getUsername(),
                    'respParcours'        => $parcours->getRespParcours()?->getDisplay(),
                    'respParcoursMail'    => $parcours->getRespParcours()?->getEmail(),
                    'respParcoursLogin'   => $parcours->getRespParcours()?->getUsername(),
                    'coRespParcours'      => $parcours->getCoResponsable()?->getDisplay(),
                    'coRespParcoursMail'  => $parcours->getCoResponsable()?->getEmail(),
                    'coRespParcoursLogin' => $parcours->getCoResponsable()?->getUsername(),
                    'rncp'                => $formation->getCodeRNCP(),
                    'validationCfvu'      => $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'cfvu')?->getDate()?->format('d/m/Y') ?? 'Non validé',
                    'regimeChecks'        => $regimeChecks,
                ];
            }
        }

        $this->excelWriter->setSpreadsheet(
            $this->spreadsheetRenderer->createFromTemplate(self::TEMPLATE, [
                'regimes' => $regimes,
                'lignes'  => $lignes,
            ])
        );

        $this->fileName = (string) Tools::FileName('OF - REGIME - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
