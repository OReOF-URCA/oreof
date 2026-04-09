<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Export/butMcccMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/05/2023 16:14
 */

namespace App\TypeDiplome\Diplomes\But\Services;

use App\Classes\Excel\ExcelWriter;
use App\DTO\DiffObject;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\FicheMatiereRepository;
use App\Service\VersioningParcours;
use App\Service\VersioningStructure;
use App\Utils\Tools;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Filesystem\Filesystem;
use Sensiolabs\GotenbergBundle\GotenbergInterface;
use Sensiolabs\GotenbergBundle\Processor\FileProcessor;

class ButMcccVersion extends AbstractButMccc
{
    private string $fileName;
    private Parcours $parcours;

    private string $dir;


    public function __construct(
        KernelInterface                  $kernel,
        protected GotenbergInterface         $gotenberg,
        protected CalculStructureParcoursBut $calculStructureParcours,
        protected VersioningStructure $versioningStructure,
        protected VersioningParcours      $versioningParcours,
        protected FicheMatiereRepository $ficheMatiereRepository,
        protected ExcelWriter            $excelWriter,
    ) {
        $this->dir = $kernel->getProjectDir() . '/public';
    }


    /**
     * @throws Exception
     * @throws \Exception
     */
    public function genereExcelbutMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): bool
    {
        $tabColonnes = [
            'td_tp_oral' => ['pourcentage' => 'L', 'nombre' => 'M'],
            'td_tp_ecrit' => ['pourcentage' => 'N', 'nombre' => 'O'],
            'td_tp_rapport' => ['pourcentage' => 'P', 'nombre' => 'Q'],
            'td_tp_autre' => ['pourcentage' => 'R', 'nombre' => 'S'],
            'cm_ecrit' => ['pourcentage' => 'T', 'nombre' => 'U'],
            'cm_rapport' => ['pourcentage' => 'V', 'nombre' => 'W'],
            'iut_portfolio' => ['pourcentage' => 'X', 'nombre' => 'Y'],
            'iut_livrable' => ['pourcentage' => 'Z', 'nombre' => 'AA'],
            'iut_rapport' => ['pourcentage' => 'AB', 'nombre' => 'AC'],
            'iut_soutenance' => ['pourcentage' => 'AD', 'nombre' => 'AE'],
            'hors_iut_entreprise' => ['pourcentage' => 'AF', 'nombre' => 'AG'],
            'hors_iut_rapport' => ['pourcentage' => 'AH', 'nombre' => 'AI'],
            'hors_iut_soutenance' => ['pourcentage' => 'AJ', 'nombre' => 'AK'],
        ];

        $formation = $parcours->getFormation();
        $this->parcours = $parcours;

        if (null === $formation) {
            throw new \Exception('La formation n\'existe pas');
        }

        $dto = $this->calculStructureParcours->calcul($parcours);//todo: devrait passer par le typeDiplome?

        $structureDifferencesParcours = $this->versioningParcours->getStructureDifferencesBetweenParcoursAndLastCfvu($parcours);
        if ($structureDifferencesParcours !== null) {
            $diffStructure = (new VersioningStructure($structureDifferencesParcours, $dto))->calculDiff(true);
            $lastCfvuDescriptifs = $this->versioningParcours->loadParcoursFromVersion(
                $this->versioningParcours->getLastVersionOrLastYearCfvu($parcours)
            )['parcours'];
            $diffDescriptifs = VersioningStructure::calculDiffDescriptifs($lastCfvuDescriptifs, $parcours);
            $diffStructure = $this->versioningStructure->setDto($structureDifferencesParcours, $dto)->calculDiff(true);
        } else {
            return false;
        }

        $this->excelWriter->createFromTemplate('Annexe_MCCC_BUT.xlsx');

        // Prépare le modèle avant de dupliquer
        $modele = $this->excelWriter->getSheetByName(self::PAGE_MODELE);

        // Données différences
        $this->excelWriter->setSheet($modele);

        //récupération des données
        // récupération des semestres du parcours puis classement par année et par ordre
        $tabSemestres = [];
        $semestres = $parcours->getSemestreParcours();
        foreach ($semestres as $semParc) {
            if ($semParc->getSemestre()?->isNonDispense() === false && $semParc->isOuvert() === true) {
                if ($semParc->getSemestre()?->getSemestreRaccroche() !== null) {
                    $tabSemestres[$semParc->getOrdre()] = $semParc->getSemestre()?->getSemestreRaccroche();
                } else {
                    $tabSemestres[$semParc->getOrdre()] = $semParc;
                }
            }
        }

        //en-tête du fichier
        $modele->setCellValue(self::CEL_DOMAINE, $formation->getDomaine()?->getLibelle());
        $modele->setCellValue(self::CEL_COMPOSANTE, $formation->getComposantePorteuse()?->getLibelle());
        $modele->setCellValue(self::CEL_ANNEE_UNIVERSITAIRE, $anneeUniversitaire->getAnneeUniversitaire()?->getLibelle());
        // $modele->setCellValue(self::CEL_INTITULE_FORMATION, $formation->getDisplay());
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_INTITULE_FORMATION, 0, 1),
            substr(self::CEL_INTITULE_FORMATION, 1, 1),
            $diffDescriptifs['libelleMention'],
            ['withNewLine' => true]
        );

        if ($formation->isHasParcours() === false) {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $formation->getLocalisationMention()[0]?->getLibelle());
        } else {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $parcours->getLocalisation()?->getLibelle());
            // $modele->setCellValue(self::CEL_INTITULE_PARCOURS, $parcours->getDisplay());
            $this->excelWriter->writeCellXYDiff(
                substr(self::CEL_INTITULE_PARCOURS, 0, 1),
                substr(self::CEL_INTITULE_PARCOURS, 1, 1),
                $diffDescriptifs['libelleParcours'],
                ['withNewLine' => true]
            );

            $modele->setCellValue(self::CEL_PARCOURS_ECTS, $parcours->getDisplay());
            $modele->setCellValue(self::CEL_PARCOURS, $parcours->getDisplay());
        }

        // dates
        $modele->setCellValue(self::CEL_DATE_CONSEIL, $dateConseil?->format('d/m/Y'));
        $modele->setCellValue(self::CEL_DATE_CFVU, $dateCfvu?->format('d/m/Y'));

        if ($dateCfvu !== null) {
            //changer le pied de page.
            $modele->getHeaderFooter()
                ->setOddFooter(
                    '&L&B' . 'Document généré depuis ORéOF'.
                    '&C&B' . 'Document validé en CFVU le '. $dateCfvu->format('d/m/Y')
                    . '&R&B' . 'Université de Reims Champagne-Ardenne'
                );
        }

        /*
        foreach ($parcours->getRegimeInscription() as $regimeInscription) {
            if ($regimeInscription === RegimeInscriptionEnum::FI) {
                $modele->setCellValue(self::CEL_REGIME_FI, 'X');
            }
            if ($regimeInscription === RegimeInscriptionEnum::FC) {
                $modele->setCellValue(self::CEL_REGIME_FC, 'X');
            }
            if ($regimeInscription === RegimeInscriptionEnum::FI_APPRENTISSAGE) {
                $modele->setCellValue(self::CEL_REGIME_FI_APPRENTISSAGE, 'X');
            }
            if ($regimeInscription === RegimeInscriptionEnum::FC_CONTRAT_PRO) {
                $modele->setCellValue(self::CEL_REGIME_FC_CONTRAT_PRO, 'X');
            }
        }
        */

        // Régimes d'inscription avec différences
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FI, 0, 1),
            substr(self::CEL_REGIME_FI, 1, 1),
            $diffDescriptifs['regimeInscription']['FI'],
            ['withLighterGreen' => true]
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FC, 0, 1),
            substr(self::CEL_REGIME_FC, 1, 2),
            $diffDescriptifs['regimeInscription']['FC'],
            ['withLighterGreen' => true]
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FI_APPRENTISSAGE, 0, 1),
            substr(self::CEL_REGIME_FI_APPRENTISSAGE, 1, 2),
            $diffDescriptifs['regimeInscription']['FIA'],
            ['withLighterGreen' => true]
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FC_CONTRAT_PRO, 0, 1),
            substr(self::CEL_REGIME_FC_CONTRAT_PRO, 1, 2),
            $diffDescriptifs['regimeInscription']['FCCP'],
            ['withLighterGreen' => true]
        );

        $index = 1;

        //recopie du modèle sur chaque année, puis remplissage
        foreach ($tabSemestres as $i => $semestres) {
            $totalHeuresAvant = ['CM' => 0, 'TD' => 0, 'TP' => 0, 'TE' => 0];
            $totalHeuresApres = ['CM' => 0, 'TD' => 0, 'TP' => 0, 'TE' => 0];


            $diffSemestre = $diffStructure['semestres'][$i];
            $tabColUes = [];
            $clonedWorksheet = clone $modele;
            $clonedWorksheet->setTitle('Semestre S' . $i);
            $this->excelWriter->addSheet($clonedWorksheet, $index);
            $index++;
            $semestreSheets[$i] = $clonedWorksheet;


            //remplissage de chaque année
            //ligne départ 18
            $ligne = 24;
            if (array_key_exists($i, $tabSemestres)) {
                $this->excelWriter->setSheet($clonedWorksheet);
                $this->excelWriter->writeCellName(self::CEL_SEMESTRE_ETUDE, 'Semestre S' . $i);

                $colUe = self::COL_FIRST_UE;

                if ($semestres->getSemestre()->getSemestreRaccroche() !== null) {
                    $semestre = $semestres->getSemestre()->getSemestreRaccroche()->getSemestre();
                } else {
                    $semestre = $semestres->getSemestre();
                }
                $tabFichesRessources = [];
                $tabFichesSaes = [];
                $tabFichesUes = [];
                // Affichage des UE + gestion des colonnes
                foreach ($semestre->getUes() as $ue) {
                    if ($ue->getUeRaccrochee() !== null) {
                        $ue = $ue->getUeRaccrochee()->getUe();
                    }

                    $diffUe = $diffSemestre['ues'][$ue->getOrdre()];

                    $totalCoeffAvant[$ue->getOrdre()] = ['Ressource' => 0, 'Sae' => 0];
                    $totalCoeffApres[$ue->getOrdre()] = ['Ressource' => 0, 'Sae' => 0];
                    foreach ($ue->getElementConstitutifs() as $keyEc => $ec) {
                        $fiche = $ec->getFicheMatiere();
                        $diffFiche = $diffUe['elementConstitutifs'][$keyEc];
                        if ($fiche !== null) {
                            if ($fiche->getTypeMatiere() === FicheMatiere::TYPE_MATIERE_RESSOURCE) {
                                $tabFichesRessources[$fiche->getSigle()]['ec'] = $ec;
                                $tabFichesRessources[$fiche->getSigle()]['diff'] = $diffFiche;
                                $tabFichesUes[$fiche->getSigle()][$ue->getOrdre()] = new DiffObject($diffFiche['ects']?->getOriginalFloat() ?? 0, $ec->getEcts() ?? 0);
                                $totalCoeffAvant[$ue->getOrdre()]['Ressource'] += $diffFiche['ects']?->getOriginalFloat() ?? 0;
                                $totalCoeffApres[$ue->getOrdre()]['Ressource'] += $ec->getEcts() ?? 0;
                            }

                            if ($fiche->getTypeMatiere() === FicheMatiere::TYPE_MATIERE_SAE) {
                                $tabFichesSaes[$fiche->getSigle()]['ec'] = $ec;
                                $tabFichesSaes[$fiche->getSigle()]['diff'] = $diffFiche;
                                $tabFichesUes[$fiche->getSigle()][$ue->getOrdre()] = new DiffObject($diffFiche['ects']?->getOriginalFloat() ?? 0, $ec->getEcts() ?? 0);
                                $totalCoeffAvant[$ue->getOrdre()]['Sae'] += $diffFiche['ects']?->getOriginalFloat() ?? 0;
                                $totalCoeffApres[$ue->getOrdre()]['Sae'] += $ec->getEcts() ?? 0;
                            }
                        }
                    }

                    $tabColUes[$ue->getOrdre()] = $colUe;
                    $this->excelWriter->writeCellXY($colUe, 18, 'BC' . $ue->getOrdre(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY($colUe, 19, $ue->getLibelle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->mergeCellsCaR($colUe, 19, $colUe, 22);
                    $colUe++;
                    $this->excelWriter->insertNewColumnBefore($colUe);
                }

                //supprimer les cols en trop ?
                $this->excelWriter->removeColumn($colUe, 2);
                $this->excelWriter->mergeCellsCaR(self::COL_FIRST_UE, 17, $colUe - 1, 17);
                $this->excelWriter->cellStyle('AM17', ['alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ]]);

                ksort($tabFichesRessources);
                ksort($tabFichesSaes);

                foreach ($tabFichesRessources as $ec) {
                    $fiche = $ec['ec']->getFicheMatiere();
                    $this->excelWriter->insertNewRowBefore($ligne);
                    $this->excelWriter->writeCellXY(self::COL_CODE_ELEMENT, $ligne, '', ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CODE_EC, $ligne, $fiche->getSigle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_INTITULE, $ligne, $ec['diff']['libelle'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_VOL_ETUDIANT, $ligne, $ec['diff']['heuresEctsEc']['sommeEcTotalPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_CM, $ligne, $ec['diff']['heuresEctsEc']['cmPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_TD, $ligne, $ec['diff']['heuresEctsEc']['tdPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_TP, $ligne, $ec['diff']['heuresEctsEc']['tpPres'], ['style' => 'HORIZONTAL_CENTER']);

                    //MCCC
                    $this->writeMccc($ec, $tabColonnes, $ligne);
                    $this->writeAcUe($fiche, $ligne, $tabColUes, $tabFichesUes);

                    $totalHeuresAvant['CM'] += $ec['diff']['heuresEctsEc']['cmPres']?->getOriginalFloat() ?? 0;
                    $totalHeuresAvant['TD'] += $ec['diff']['heuresEctsEc']['tdPres']?->getOriginalFloat() ?? 0;
                    $totalHeuresAvant['TP'] += $ec['diff']['heuresEctsEc']['tpPres']?->getOriginalFloat() ?? 0;

                    $totalHeuresApres['CM'] += $ec['diff']['heuresEctsEc']['cmPres']?->getNewFloat() ?? 0;
                    $totalHeuresApres['TD'] += $ec['diff']['heuresEctsEc']['tdPres']?->getNewFloat() ?? 0;
                    $totalHeuresApres['TP'] += $ec['diff']['heuresEctsEc']['tpPres']?->getNewFloat() ?? 0;


                    $ligne++;
                }

                $finRessource = $ligne - 1;

                $this->excelWriter->insertNewRowBefore($ligne);
                $ligne++;
                $debutSae = $ligne;

                foreach ($tabFichesSaes as $ec) {
                    $fiche = $ec['ec']->getFicheMatiere();
                    $this->excelWriter->insertNewRowBefore($ligne);
                    $this->excelWriter->writeCellXY(self::COL_CODE_ELEMENT, $ligne, '', ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CODE_EC, $ligne, $fiche->getSigle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_INTITULE, $ligne, $ec['diff']['libelle'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_VOL_ETUDIANT, $ligne, $ec['diff']['heuresEctsEc']['sommeEcTotalPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_CM, $ligne, $ec['diff']['heuresEctsEc']['cmPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_TD, $ligne, $ec['diff']['heuresEctsEc']['tdPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_TP, $ligne, $ec['diff']['heuresEctsEc']['tpPres'], ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff(self::COL_HEURE_AUTONOMIE, $ligne, $ec['diff']['heuresEctsEc']['tePres']);

                    //MCCC
                    $this->writeMccc($ec, $tabColonnes, $ligne);
                    $this->writeAcUe($fiche, $ligne, $tabColUes, $tabFichesUes);

                    $totalHeuresAvant['CM'] += $ec['diff']['heuresEctsEc']['cmPres']?->getOriginalFloat() ?? 0;
                    $totalHeuresAvant['TD'] += $ec['diff']['heuresEctsEc']['tdPres']?->getOriginalFloat() ?? 0;
                    $totalHeuresAvant['TP'] += $ec['diff']['heuresEctsEc']['tpPres']?->getOriginalFloat() ?? 0;
                    $totalHeuresAvant['TE'] += $ec['diff']['heuresEctsEc']['tePres']?->getOriginalFloat() ?? 0;

                    $totalHeuresApres['CM'] += $ec['diff']['heuresEctsEc']['cmPres']?->getNewFloat() ?? 0;
                    $totalHeuresApres['TD'] += $ec['diff']['heuresEctsEc']['tdPres']?->getNewFloat() ?? 0;
                    $totalHeuresApres['TP'] += $ec['diff']['heuresEctsEc']['tpPres']?->getNewFloat() ?? 0;
                    $totalHeuresApres['TE'] += $ec['diff']['heuresEctsEc']['tePres']?->getNewFloat() ?? 0;

                    $ligne++;
                }

                $this->excelWriter->colorCells('L' . $debutSae . ':W' . ($ligne - 1), 'FFCCCCCC');
                $this->excelWriter->colorCells('X23:AK' . $finRessource, 'FFCCCCCC');
                $this->excelWriter->colorCells('H23:H' . $finRessource, 'FFCCCCCC');
                foreach ($tabColUes as $keyUe => $colUe) {
                    $diffUe = $diffSemestre['ues'][$keyUe];

                    //pour chaque colonne d'uE on met à jour la somme des ECTS dans la formule
                    $sommeEctsAvant = $totalCoeffAvant[$keyUe]['Ressource'] + $totalCoeffAvant[$keyUe]['Sae'];
                    $sommeEctsApres = $totalCoeffApres[$keyUe]['Ressource'] + $totalCoeffApres[$keyUe]['Sae'];

                    $this->excelWriter->writeCellXYDiff($colUe, $ligne + 2, $diffUe['heuresEctsUe']['sommeUeEcts'], ['style' => 'HORIZONTAL_CENTER']);
                    //somme des coeffs
                    $this->excelWriter->writeCellXYDiff($colUe, $ligne + 3, new DiffObject($sommeEctsAvant, $sommeEctsApres), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXYDiff($colUe, $ligne + 4, new DiffObject($totalCoeffAvant[$keyUe]['Sae'], $totalCoeffApres[$keyUe]['Sae']), ['style' => 'HORIZONTAL_CENTER']);

                    $pourcentageSaeAvant = $sommeEctsAvant > 0 ? round($totalCoeffAvant[$keyUe]['Sae'] / $sommeEctsAvant * 100, 2) : 0;
                    $pourcentageSaeApres = $sommeEctsApres > 0 ? round($totalCoeffApres[$keyUe]['Sae'] / $sommeEctsApres * 100, 2) : 0;

                    $this->excelWriter->writeCellXYDiff($colUe, $ligne + 5, new DiffObject($pourcentageSaeAvant, $pourcentageSaeApres), ['style' => 'numerique']);
                }
                // affichage des "totaux"
                $this->excelWriter->writeCellXYDiff(self::COL_CM, $ligne + 2, new DiffObject($totalHeuresAvant['CM'], $totalHeuresApres['CM']), ['style' => 'HORIZONTAL_CENTER']);
                $this->excelWriter->writeCellXYDiff(
                    self::COL_TD,
                    $ligne + 2,
                    new DiffObject($totalHeuresAvant['TD'], $totalHeuresApres['TD']),
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXYDiff(self::COL_TP, $ligne + 2, new DiffObject($totalHeuresAvant['TP'], $totalHeuresApres['TP']), ['style' => 'HORIZONTAL_CENTER']);
                $this->excelWriter->writeCellXYDiff(self::COL_HEURE_AUTONOMIE, $ligne + 2, new DiffObject($totalHeuresAvant['TE'], $totalHeuresApres['TE']), ['style' => 'HORIZONTAL_CENTER']);

                $sommeAvant = $totalHeuresAvant['CM'] + $totalHeuresAvant['TD'] + $totalHeuresAvant['TP'];
                $sommeApres = $totalHeuresApres['CM'] + $totalHeuresApres['TD'] + $totalHeuresApres['TP'];

                $this->excelWriter->writeCellXYDiff(self::COL_CM, $ligne + 3, new DiffObject($sommeAvant, $sommeApres), ['style' => 'HORIZONTAL_CENTER']);
            }

            //suppression de la ligne modèle 18
            $this->excelWriter->removeRow(23);
        }

        //supprimer la feuille de modèle
        $this->excelWriter->removeSheetByIndex(0);
        $this->excelWriter->setActiveSheetIndex(0);
        $this->excelWriter->setSelectedCells('A1');

        $this->fileName = Tools::FileName('MCCC - ' . $anneeUniversitaire->getLibelle() . ' - ' . $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle(). ' ' . $parcours->getSigle(), 40);

        return true;
    }

    public function exportExcelbutMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse {
        $this->genereExcelbutMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    public function exportPdfbutMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): Response {
        $this->genereExcelbutMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

        $fichier = $this->excelWriter->saveFichier($this->fileName, $this->dir . '/temp/');

        $response = $this->gotenberg
            ->pdf()
            ->office()
            ->files(new \SplFileInfo($fichier))
            ->generate()
            ->stream($this->fileName . '.pdf');

        unlink($fichier);

        return $response;
    }

    public function exportAndSaveExcelbutMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string {
        $this->genereExcelbutMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        $this->excelWriter->saveFichier($this->fileName, $dir);
        return $this->fileName . '.xlsx';
    }

    public function exportAndSavePdfbutMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
        ?string            $customFileName = null,
    ): string {
        $this->genereExcelbutMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

        if ($customFileName !== null) {
            $this->fileName = $customFileName;
        }

        $fichier = $this->excelWriter->saveFichier($this->fileName, $this->dir . '/temp/');

        $this->gotenberg
            ->pdf()
            ->office()
            ->files(new \SplFileInfo($fichier))
            ->fileName($this->fileName)
            ->generate()
            ->processor(new FileProcessor(new Filesystem(), $dir))
            ->process();

        if (file_exists($fichier)) {
            unlink($fichier);
        }

        return $this->fileName . '.pdf';
    }

    private function writeMccc(mixed $ec, array $tabColonnes, int $ligne): void
    {
        $mcccs = $ec['ec']->getFicheMatiere()->getMcccs();
        $diffMccc = $ec['diff']['mcccs'];
        foreach ($mcccs as $mccc) {
            if ($mccc->getLibelle() !== '' && array_key_exists($mccc->getLibelle(), $tabColonnes)) {
                $this->excelWriter->writeCellXYDiff(
                    //convertir chiffre en lettre excel
                    Coordinate::columnIndexFromString($tabColonnes[$mccc->getLibelle()]['pourcentage']),
                    $ligne,
                    $diffMccc[$mccc->getCleUnique()]['pourcentage'] ?? null,
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXYDiff(
                    Coordinate::columnIndexFromString($tabColonnes[$mccc->getLibelle()]['nombre']),
                    $ligne,
                    $diffMccc[$mccc->getCleUnique()]['nbEpreuves'] ?? null,
                    ['style' => 'HORIZONTAL_CENTER']
                );
            }
        }
    }

    private function writeAcUe(FicheMatiere $fiche, int $ligne, array $tabColUes, array $tabFichesUes): void
    {
        if (array_key_exists($fiche->getSigle(), $tabFichesUes)) {
            foreach ($tabFichesUes[$fiche->getSigle()] as $ueId => $ects) {
                if (array_key_exists($ueId, $tabColUes)) {
                    $this->excelWriter->writeCellXYDiff(
                        $tabColUes[$ueId],
                        $ligne,
                        $ects,
                        ['style' => 'HORIZONTAL_CENTER']
                    );
                }
            }
        }
    }
}
