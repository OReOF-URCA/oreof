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
use App\Entity\CampagneCollecte;
use App\Enums\RegimeInscriptionEnum;
use App\Referentiel\ReferentielManager;
use App\Repository\DpeParcoursRepository;
use App\Repository\FormationRepository;
use App\Service\ProjectDirProvider;
use App\Utils\Tools;
use DateTime;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ExportSynthese
{
    private string $fileName;
    private string $dir;

    public function __construct(
        protected ExcelWriter         $excelWriter,
        ProjectDirProvider $projectDirProvider,
        protected DpeParcoursRepository $dpeParcoursRepository,
        protected GetHistorique         $getHistorique,
        protected FormationRepository $formationRepository,
        private readonly ReferentielManager $referentiels,
    ) {
        $this->dir = $projectDirProvider->getProjectDir() . '/public/temp/';
    }

    private function prepareExport(
        CampagneCollecte $anneeUniversitaire,
    ): void {
        $formations = $this->formationRepository->findBySearch('', $anneeUniversitaire);
        $this->excelWriter->createFromTemplate('export_offre_formation.xlsx');
        $this->excelWriter->setActiveSheetIndex(0);
        $ligne = 2;
        foreach ($formations as $formation) {
            //Composante	Type de diplôme	mention	parcours	état	remplissage	nom responsable
            $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
            $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
            $this->excelWriter->writeCellXY(3, $ligne, $formation->getDisplay());
            if ($formation->isHasParcours()) {
                $this->excelWriter->writeCellXY(4, $ligne, $formation->getParcours()->count() . ' parcours');
            } else {
                $this->excelWriter->writeCellXY(4, $ligne, 'Pas de parcours');
            }
            $this->excelWriter->writeCellXY(4, $ligne);
            $this->excelWriter->writeCellXY(5, $ligne, array_key_first($formation->getEtatDpe()));
            $this->excelWriter->writeCellXY(6, $ligne, number_format($formation->getRemplissage()->calcul() / 100, 2), [
                'pourcentage' => 'pourcentage',
            ]);
            $this->excelWriter->writeCellXY(7, $ligne, $formation->getResponsableMention()?->getDisplay());
            $ligne++;
            foreach ($formation->getParcours() as $parcours) {
                if ($parcours->isParcoursDefaut() === false) {
                    $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
                    $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
                    $this->excelWriter->writeCellXY(3, $ligne, $formation->getDisplay());
                    $this->excelWriter->writeCellXY(4, $ligne, $parcours->getLibelle());
                    $this->excelWriter->writeCellXY(5, $ligne, array_key_first($parcours->getEtatParcours()));
                    $this->excelWriter->writeCellXY(6, $ligne, number_format($parcours->getRemplissage()->calcul() / 100, 2), [
                        'pourcentage' => 'pourcentage',
                    ]);
                    $this->excelWriter->writeCellXY(7, $ligne, $parcours->getRespParcours()?->getDisplay());
                    $ligne++;
                }
            }
        }
        $this->fileName = Tools::FileName('OF - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }


    private function prepareExportBrut(
        CampagneCollecte $campagneCollecte,
    ): void {
        $dpeParcours = $this->dpeParcoursRepository->findBy(['campagneCollecte' => $campagneCollecte]);
        $this->excelWriter->nouveauFichier('Export Offre de formation');
        $this->excelWriter->setActiveSheetIndex(0);

        $this->excelWriter->writeCellXY(1, 1, 'Composante');
        $this->excelWriter->writeCellXY(2, 1, 'Type Diplôme');
        $this->excelWriter->writeCellXY(3, 1, 'Mention');
        $this->excelWriter->writeCellXY(4, 1, 'Parcours');
        $this->excelWriter->writeCellXY(5, 1, 'Etat');
        $this->excelWriter->writeCellXY(6, 1, 'Remplissage');
        $this->excelWriter->writeCellXY(7, 1, 'Resp. Mention');
        $this->excelWriter->writeCellXY(8, 1, 'sigle');
        $this->excelWriter->writeCellXY(9, 1, 'regimeInscriptionTexte');
        $this->excelWriter->writeCellXY(10, 1, 'modalitesAlternance');
        $this->excelWriter->writeCellXY(11, 1, 'objectifsFormation');
        $this->excelWriter->writeCellXY(12, 1, 'contenuFormation');
        $this->excelWriter->writeCellXY(13, 1, 'resultatsAttendus');
        $this->excelWriter->writeCellXY(14, 1, 'rythmeFormationTexte');
        //parcours
        $this->excelWriter->writeCellXY(15, 1, 'P sigle');
        $this->excelWriter->writeCellXY(16, 1, 'P contenuFormation');
        $this->excelWriter->writeCellXY(17, 1, 'P objectifsParcours');
        $this->excelWriter->writeCellXY(18, 1, 'P resultatsAttendus');
        $this->excelWriter->writeCellXY(19, 1, 'P rythmeFormationTexte');
        $this->excelWriter->writeCellXY(20, 1, 'P hasStage');
        $this->excelWriter->writeCellXY(21, 1, 'P stageText');
        $this->excelWriter->writeCellXY(22, 1, 'P nbHeuresStages');
        $this->excelWriter->writeCellXY(23, 1, 'P hasProjet');
        $this->excelWriter->writeCellXY(24, 1, 'P projetText');
        $this->excelWriter->writeCellXY(25, 1, 'P nbHeuresProjet');
        $this->excelWriter->writeCellXY(26, 1, 'P hasMemoire');
        $this->excelWriter->writeCellXY(27, 1, 'P memoireText');
        $this->excelWriter->writeCellXY(28, 1, 'P hasSituationPro');
        $this->excelWriter->writeCellXY(29, 1, 'P situationProText');
        $this->excelWriter->writeCellXY(30, 1, 'P nbHeuresSituationPro');
        $this->excelWriter->writeCellXY(31, 1, 'P prerequis');
        $this->excelWriter->writeCellXY(32, 1, 'P poursuitesEtudes');
        $this->excelWriter->writeCellXY(33, 1, 'P debouches');
        $this->excelWriter->writeCellXY(34, 1, 'P modalitesAlternance');
        $this->excelWriter->writeCellXY(35, 1, 'P coordSecretariat');
        $this->excelWriter->writeCellXY(36, 1, 'Type de parcours');
        $this->excelWriter->writeCellXY(37, 1, 'Dernier Histo.');

        // Keep the four historical columns at their original positions.
        $regimes = [];
        foreach (RegimeInscriptionEnum::cases() as $case) {
            $regimes[$case->value] = $this->referentiels->label('regime_inscription', $case->value) ?? $case->value;
        }
        foreach ($this->referentiels->choices('regime_inscription') as $key => $label) {
            $regimes[$key] ??= $label;
        }
        $i = 0;
        foreach ($regimes as $label) {
            $this->excelWriter->writeCellXY(38 + $i, 1, $label);
            $i++;
        }


        $ligne = 2;
        foreach ($dpeParcours as $parc) {

            $parcours = $parc->getParcours();
            if ($parcours === null) {
                continue;
            }
            $formation = $parcours->getFormation();
            if ($formation === null) {
                continue;
            }

            //Composante	Type de diplôme	mention	parcours	état	remplissage	nom responsable
            $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
            $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
            $this->excelWriter->writeCellXY(3, $ligne, $formation->getDisplay());
            if ($parcours->isParcoursDefaut()) {
                $this->excelWriter->writeCellXY(4, $ligne);
            } else {
                $this->excelWriter->writeCellXY(4, $ligne, $parcours->getLibelle());
            }
            $this->excelWriter->writeCellXY(5, $ligne, array_key_first($parc->getEtatValidation()));
            $this->excelWriter->writeCellXY(6, $ligne, number_format($formation->getRemplissage()->calcul() / 100, 2), [
                'pourcentage' => 'pourcentage',
            ]);
            $this->excelWriter->writeCellXY(7, $ligne, $formation->getResponsableMention()?->getDisplay());
            $this->excelWriter->writeCellXY(8, $ligne, $formation->getSigle());
            $this->excelWriter->writeCellXY(9, $ligne, $formation->getRegimeInscriptionTexte());
            $this->excelWriter->writeCellXY(10, $ligne, $formation->getModalitesAlternance());
            $this->excelWriter->writeCellXY(11, $ligne, $formation->getObjectifsFormation());
            $this->excelWriter->writeCellXY(12, $ligne, $formation->getContenuFormation());
            $this->excelWriter->writeCellXY(13, $ligne, $formation->getResultatsAttendus());
            $this->excelWriter->writeCellXY(14, $ligne, $formation->getRythmeFormationTexte());
            $i = 0;
            foreach (array_keys($regimes) as $regimeKey) {
                if (in_array($regimeKey, $formation->getRegimeInscriptionValues(), true)) {
                    $this->excelWriter->writeCellXY(38 + $i, $ligne, 'X', [
                        'style' => 'HORIZONTAL_CENTER'
                    ]);
                }
                $i++;
            }

           // $ligne++;
           // foreach ($formation->getParcours() as $parcours) {
               // if ($parcours->isParcoursDefaut() === false) {
//                    $this->excelWriter->writeCellXY(1, $ligne, $formation->getComposantePorteuse()?->getLibelle());
//                    $this->excelWriter->writeCellXY(2, $ligne, $formation->getTypeDiplome()?->getLibelle());
//                    $this->excelWriter->writeCellXY(3, $ligne, $formation->getDisplay());
//                    $this->excelWriter->writeCellXY(4, $ligne, $parcours->getLibelle());
//                    $this->excelWriter->writeCellXY(5, $ligne, array_key_first($parcours->getEtatParcours()));
//                    $this->excelWriter->writeCellXY(6, $ligne, number_format($parcours->getRemplissage()->calcul() / 100, 2), [
//                        'pourcentage' => 'pourcentage',
//                    ]);
//                    $this->excelWriter->writeCellXY(7, $ligne, $parcours->getRespParcours()?->getDisplay());

                    $this->excelWriter->writeCellXY(15, $ligne, $parcours->getSigle());
                    $this->excelWriter->writeCellXY(16, $ligne, $parcours->getContenuFormation());
                    $this->excelWriter->writeCellXY(17, $ligne, $parcours->getObjectifsParcours());
                    $this->excelWriter->writeCellXY(18, $ligne, $parcours->getResultatsAttendus());
                    $this->excelWriter->writeCellXY(19, $ligne, $parcours->getRythmeFormationTexte());
                    $this->excelWriter->writeCellXY(20, $ligne, $parcours->isHasStage() ? 'Oui' : 'Non');
                    $this->excelWriter->writeCellXY(21, $ligne, $parcours->getStageText());
                    $this->excelWriter->writeCellXY(22, $ligne, $parcours->getNbHeuresStages());
                    $this->excelWriter->writeCellXY(23, $ligne, $parcours->isHasProjet() ? 'Oui' : 'Non');
                    $this->excelWriter->writeCellXY(24, $ligne, $parcours->getProjetText());
                    $this->excelWriter->writeCellXY(25, $ligne, $parcours->getNbHeuresProjet());
                    $this->excelWriter->writeCellXY(26, $ligne, $parcours->isHasMemoire() ? 'Oui' : 'Non');
                    $this->excelWriter->writeCellXY(27, $ligne, $parcours->getMemoireText());
                    $this->excelWriter->writeCellXY(28, $ligne, $parcours->isHasSituationPro() ? 'Oui' : 'Non');
                    $this->excelWriter->writeCellXY(29, $ligne, $parcours->getSituationProText());
                    $this->excelWriter->writeCellXY(30, $ligne, $parcours->getNbHeuresSituationPro());
                    $this->excelWriter->writeCellXY(31, $ligne, $parcours->getPrerequis());
                    $this->excelWriter->writeCellXY(32, $ligne, $parcours->getPoursuitesEtudes());
                    $this->excelWriter->writeCellXY(33, $ligne, $parcours->getDebouches());
                    $this->excelWriter->writeCellXY(34, $ligne, $parcours->getModalitesAlternance());
                    $this->excelWriter->writeCellXY(35, $ligne, $parcours->getCoordSecretariat());
                    $this->excelWriter->writeCellXY(36, $ligne, $parcours->getTypeParcours()?->libelle());
            $this->excelWriter->writeCellXY(37, $ligne, $parc->getEtatReconduction()->value);


                    $i = 0;
                    foreach (array_keys($regimes) as $regimeKey) {
                        if (in_array($regimeKey, $parcours->getRegimeInscriptionValues(), true)) {
                            $this->excelWriter->writeCellXY(38 + $i, $ligne, 'X', [
                                'style' => 'HORIZONTAL_CENTER'
                            ]);
                        }
                        $i++;
                    }
                    $ligne++;
               // }
           // }
        }
        $this->fileName = Tools::FileName('OF Brut - ' . (new DateTime())->format('d-m-Y-H-i'), 30);
    }

    public function export(CampagneCollecte $annee): StreamedResponse
    {
        $this->prepareExport($annee);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportBrut(CampagneCollecte $annee): StreamedResponse
    {
        $this->prepareExportBrut($annee);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportLink(CampagneCollecte $campagneCollecte): string
    {
        $this->prepareExport($campagneCollecte);
        $this->excelWriter->saveFichier($this->fileName, $this->dir . 'zip/');
        return $this->fileName . '.xlsx';
    }
}
