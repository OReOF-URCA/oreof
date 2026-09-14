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
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCfvu implements ExportInterface
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected GetHistorique        $getHistorique,
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected FormationRepository $formationRepository,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        /*
         * Nous aurions besoin d'une extraction des formations (mention et parcours) qui passent en CFVU on peut repartir sur la base : compo / type de formation / mention / parcours / lieu de formation / responsable mention / responsable parcours et peut être ajouter une colonne date validation Conseil compo et date de transmission PV conseil (ou si PV déposé ou non)
         */
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);
        $this->excelWriter->nouveauFichier('Export CFVU');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY(3, 1, 'Mention');
        $this->excelWriter->writeCellXY(4, 1, 'Parcours');
        $this->excelWriter->writeCellXY(5, 1, 'Lieu de formation');
        $this->excelWriter->writeCellXY(6, 1, 'Resp. Mention');
        $this->excelWriter->writeCellXY(7, 1, 'Resp. Parcours');
        $this->excelWriter->writeCellXY(8, 1, 'Validation Composante');
        $this->excelWriter->writeCellXY(9, 1, 'Présence PV');
        $this->excelWriter->writeCellXY(10, 1, 'Etat validation');
        $this->excelWriter->writeCellXY(11, 1, 'Identifiant');

        $ligne = 2;
        foreach ($formations as $formation) {
            foreach ($formation->getParcours() as $parcours) {
                    $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
                    $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
                    $this->excelWriter->writeCellXY(3, $ligne, $formation->getDisplay());
                    if ($formation->isHasParcours()) {
                        $this->excelWriter->writeCellXY(4, $ligne, $parcours->getLibelle());
                        $this->excelWriter->writeCellXY(5, $ligne, $parcours->getLocalisation()?->getLibelle());
                    } else {
                        $this->excelWriter->writeCellXY(4, $ligne, 'Pas de parcours');
                        $texte = '';
                        foreach ($formation->getLocalisationMention() as $localisation) {
                            $texte .= $localisation->getLibelle() . ', ';
                        }
                        $this->excelWriter->writeCellXY(5, $ligne, substr($texte, 0, -2));
                    }

                    $this->excelWriter->writeCellXY(6, $ligne, $formation->getResponsableMention()?->getDisplay());
                    $this->excelWriter->writeCellXY(7, $ligne, $parcours->getRespParcours()?->getDisplay());
                    $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                    $this->excelWriter->writeCellXY(8, $ligne, $this->getHistorique->getHistoriqueParcoursLastStep($dpeParcours, 'conseil')?->getDate()?->format('d/m/Y') ?? 'Non validé');
                    $this->excelWriter->writeCellXY(9, $ligne, $this->getHistorique->getHistoriqueFormationHasPv($formation) === true ? 'Oui' : 'Non');
                $dpeParcours = GetDpeParcours::getFromParcours($parcours);
                $etatValidation = array_keys($dpeParcours?->getEtatValidation())[0];
                $this->excelWriter->writeCellXY(10, $ligne, $etatValidation ?? '-erreur état-');
                $this->excelWriter->writeCellXY(11, $ligne, $parcours->getId());

                $this->excelWriter->getColumnsAutoSize('A', 'K');
                    $ligne++;
            }
        }

        $this->fileName = Tools::FileName('EXPORT-CFVU - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
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
