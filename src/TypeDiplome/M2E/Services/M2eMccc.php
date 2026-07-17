<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Export/LicenceMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/05/2023 16:14
 */

namespace App\TypeDiplome\M2E\Services;

use App\Classes\Excel\ExcelWriter;
use App\DTO\StructureEc;
use App\DTO\StructureSemestre;
use App\DTO\StructureUe;
use App\DTO\TotalVolumeHeure;
use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Mccc;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\TypeEpreuveRepository;
use App\Service\TypeDiplomeResolver;
use App\Utils\Tools;
use DateTimeInterface;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class M2eMccc extends AbstractM2eMccc
{
    //todo: ajouter un watermark sur le doc ou une mention que la mention est définitive ou pas.


    public function __construct(
        KernelInterface                      $kernel,
        protected ClientInterface            $client,
        protected CalculStructureParcoursM2e $calculStructureParcours,
        protected ExcelWriter                $excelWriter,
        protected TypeEpreuveRepository      $typeEpreuveRepository
    )
    {
        parent::__construct($excelWriter);
        $this->dir = $kernel->getProjectDir() . '/public';

    }

    public function exportExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): StreamedResponse
    {
        $this->genereExcelMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        return $this->excelWriter->genereFichier($this->fileName);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function genereExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): void
    {
        $this->getTypeEpreuves();
        $this->versionFull = $versionFull;
        $formation = $parcours->getFormation();
        $parcours1 = $parcours;
        $dto = $this->calculStructureParcours->calcul($parcours1, dataFromFicheMatiere: true);
        $totalFormation = $dto->heuresEctsFormation;

        if (null === $formation) {
            throw new \Exception('La formation n\'existe pas');
        }
        $this->excelWriter->createFromTemplate(self::FICHIER_MCCC_XSLX);

        // Prépare le modèle avant de dupliquer
        $modele = $this->excelWriter->getSheetByName(self::PAGE_MODELE);
        if ($modele === null) {
            throw new \Exception('Le modèle n\'existe pas');
        }

        //récupération des données
        // récupération des semestres du parcours puis classement par année et par ordre
        $tabSemestresAnnee = $dto->getTabAnnee();

        //en-tête du fichier
        $modele->setCellValue(self::CEL_ANNEE_UNIVERSITAIRE, 'Année Universitaire ' . $anneeUniversitaire->getAnneeUniversitaire()?->getLibelle());
        $modele->setCellValue(self::CEL_TYPE_FORMATION, $formation->getTypeDiplome()?->getLibelle());
        $modele->setCellValue(self::CEL_INTITULE_FORMATION, $formation->getDisplay());
        $modele->setCellValue(self::CEL_INTITULE_PARCOURS, $parcours->isParcoursDefaut() === false ? $parcours->getDisplay() : '');
        $modele->setCellValue(self::CEL_COMPOSANTE, $formation->getComposantePorteuse()?->getLibelle());
        if ($formation->isHasParcours() === false) {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $formation->getLocalisationMention()[0]?->getLibelle());
        } else {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $parcours->getLocalisation()?->getLibelle());
        }
        $modele->setCellValue(self::CEL_RESPONSABLE_MENTION, $formation->getResponsableMention()?->getDisplay());
        $modele->setCellValue(self::CEL_RESPONSABLE_PARCOURS, $parcours->getRespParcours()?->getDisplay());

        // dates
        $modele->setCellValue(self::CEL_DATE_CONSEIL, $dateConseil?->format('d/m/Y'));
        $modele->setCellValue(self::CEL_DATE_CFVU, $dateCfvu?->format('d/m/Y'));

        if ($dateCfvu !== null) {
            //changer le pied de page.
            $modele->getHeaderFooter()
                ->setOddFooter(
                    '&L&B' . 'Document généré depuis ORéOF' .
                    '&C&B' . 'Document validé en CFVU le ' . $dateCfvu->format('d/m/Y')
                    . '&R&B' . 'Université de Reims Champagne-Ardenne'
                );
        }

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
        //ajoute les sigles
        $texte = '';
        foreach ($this->typeEpreuves as $typeEpreuve) {
            $texte .= $typeEpreuve->getSigle() . ' : ' . $typeEpreuve->getLibelle() . '; ';
        }
        $texte = substr($texte, 0, -2);
        $texte .= '. CCr : Note globale de CC de première session conservée. TPr : Note globale de TP de première session conservée.';
        $modele->setCellValue(self::COL_DETAIL_TYPE_EPREUVES, $texte);

        $index = 1;
        foreach ($tabSemestresAnnee as $i => $semestres) {
            $clonedWorksheet = clone $modele;
            $clonedWorksheet->setTitle('Année ' . $i);
            $this->excelWriter->addSheet($clonedWorksheet, $index);
            $index++;
            $anneeSheets[$i] = $clonedWorksheet;


            //remplissage de chaque année
            //ligne départ 18
            $ligne = 19;
            if (array_key_exists($i, $tabSemestresAnnee)) {
                $totalAnnee = new TotalVolumeHeure();
                $totalEcts = 0;
                $this->excelWriter->setSheet($clonedWorksheet);
                $this->excelWriter->writeCellName(self::CEL_ANNEE_ETUDE, Tools::adjNumeral($i) . ' année');
                $this->lignesSemestre = [];
                $this->lignesEcColorees = [];
                $tabColUes = [];
                $colUe = self::COL_FIRST_UE;

                // Duplique la colonne UE du modele pour avoir une colonne par UE.
                $nbUesAnnee = 0;
                foreach ($semestres as $semestre) {
                    $nbUesAnnee += count($semestre->ues);
                }

                if ($nbUesAnnee > 1) {
                    $clonedWorksheet->insertNewColumnBefore(
                        Coordinate::stringFromColumnIndex(self::COL_FIRST_UE + 1),
                        $nbUesAnnee - 1
                    );
                }
                /** @var StructureSemestre $semestre */
                foreach ($semestres as $semestre) {

                    foreach ($semestre->ues as $ue) {
                        //colonnes des BC/UE
                        $tabColUes[$ue->ue->getId()] = $colUe;

                        $this->excelWriter->writeCellXY($colUe, 17, "BC" . $ue->ue->getOrdre(), ['style' => 'HORIZONTAL_CENTER',
                            'valign' => 'VERTICAL_CENTER',
                            'wrap' => true,]);
                        $colUe++;
                    }

                    $this->excelWriter->mergeCellsCaR(self::COL_FIRST_UE, 15, $colUe - 1, 16);
                    $this->excelWriter->writeCellXY($colUe, 15, 'Coefficients sur les BC', [
                        'style' => 'HORIZONTAL_CENTER',
                        'valign' => 'VERTICAL_CENTER',
                        'wrap' => true,
                    ]);



                    $totalAnnee->addSemestre($semestre->heuresEctsSemestre);
                    $totalEcts += $semestre->heuresEctsSemestre->sommeSemestreEcts;
                    foreach ($semestre->ues as $ue) { //todo: changement ici avec modif du DTO ? un impact ?
                        //UE
                        $debut = $ligne;
                        if (count($ue->uesEnfants()) === 0) {
                            if ($ue->ue->getNatureUeEc() !== null && $ue->ue->getNatureUeEc()->isLibre()) {
                                $ligne = $this->afficheUeLibre($ligne, $ue);
                            } else {
                                //Si des UE enfants, on affiche pas les éventuels EC résiduels,
                                foreach ($ue->elementConstitutifs as $ec) {
                                    $ligne = $this->afficheEc($ligne, $ec);
                                    foreach ($ec->elementsConstitutifsEnfants as $ece) {
                                        $ligne = $this->afficheEc($ligne, $ece);
                                    }
                                }

                                if ($debut < $ligne - 1) {
                                    $this->excelWriter->mergeCellsCaR(self::COL_UE, $debut, self::COL_UE, $ligne - 1);
                                    $this->excelWriter->mergeCellsCaR(self::COL_INTITULE_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);

                                    //boucle sur les UE
                                    foreach ($tabColUes as $col => $ueCol) {
                                        $this->excelWriter->mergeCellsCaR($ueCol, $debut, $ueCol, $ligne - 1);
                                    }


                                    // bordure fine

                                    $this->excelWriter->borderOutsiteInside(self::COL_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_NUM_EC, $debut, self::COL_INTITULE_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_INTITULE_EC_EN, $debut, self::COL_INTITULE_EC_EN, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_RESP_EC, $debut, self::COL_RESP_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_LANGUE_EC, $debut, self::COL_LANGUE_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_SUPPORT_ANGLAIS, $debut, self::COL_SUPPORT_ANGLAIS, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_TYPE_EC, $debut, self::COL_TYPE_EC, $ligne - 1);
//                                    $this->excelWriter->borderOutsiteInside(self::COL_COURS_MUTUALISE, $debut, self::COL_COURS_MUTUALISE, $ligne - 1);
//                                    $this->excelWriter->borderOutsiteInside(self::COL_COMPETENCES, $debut, self::COL_COMPETENCES, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_ECTS, $debut, self::COL_ECTS, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_PRES_CM, $debut, self::COL_HEURES_PRES_TP, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_PRES_TOTAL, $debut, self::COL_HEURES_PRES_TOTAL, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_DIST_CM, $debut, self::COL_HEURES_DIST_TP, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_DIST_TOTAL, $debut, self::COL_HEURES_DIST_TOTAL, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_AUTONOMIE, $debut, self::COL_HEURES_AUTONOMIE, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_HEURES_TOTAL, $debut, self::COL_HEURES_TOTAL, $ligne - 1);

                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_CCI, $debut, self::COL_MCCC_CCI, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_CC, $debut, self::COL_MCCC_CC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_CT, $debut, self::COL_MCCC_CT, $ligne - 1);

                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CC_SUP_10, $debut, self::COL_MCCC_SECONDE_CHANCE_CC_SUP_10, $ligne - 1);

                                    //$this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP, $debut, self::COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CT, $debut, self::COL_MCCC_SECONDE_CHANCE_CT, $ligne - 1);

                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CC_SANS_TP, $debut, self::COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP, $ligne - 1);
                                }
                            }
                            $this->excelWriter->writeCellXY(self::COL_UE, $debut, $ue->ue->display(), ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'font-weight' => false]);
                            $this->excelWriter->writeCellXY(self::COL_INTITULE_UE, $debut, $ue->ue->getLibelle(), ['wrap' => true]);
                            $this->excelWriter->writeCellXY($tabColUes[$ue->ue->getId()], $debut, $ue->ue->getEcts(), ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'valign' => 'VERTICAL_CENTER']);
                        }
                        foreach ($ue->uesEnfants() as $uee) {
                            $debut = $ligne;
                            if ($uee->ue->getNatureUeEc() !== null && $uee->ue->getNatureUeEc()->isLibre()) {
                                $ligne = $this->afficheUeLibre($ligne, $uee);
                            } else {
                                foreach ($uee->elementConstitutifs as $ec) {
                                    $ligne = $this->afficheEc($ligne, $ec);
                                    foreach ($ec->elementsConstitutifsEnfants as $ece) {
                                        $ligne = $this->afficheEc($ligne, $ece);
                                    }
                                }

                                if ($debut < $ligne - 1) {
                                    $this->excelWriter->mergeCellsCaR(self::COL_UE, $debut, self::COL_UE, $ligne - 1);
                                    $this->excelWriter->mergeCellsCaR(self::COL_INTITULE_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);
                                }
                            }
                            $this->excelWriter->writeCellXY(self::COL_UE, $debut, $uee->display, ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'font-weight' => false]);
                            $this->excelWriter->writeCellXY(self::COL_INTITULE_UE, $debut, $uee->ue->getLibelle(), ['wrap' => true]);
                        }
                    }

                    $this->excelWriter->writeCellXY(self::COL_ECTS, $ligne, $totalEcts === 0.0 ? '' : $totalEcts, ['style' => 'HORIZONTAL_CENTER']);

                    $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_CM, $ligne, $totalAnnee->totalCmPresentiel === 0.0 ? '' : $totalAnnee->totalCmPresentiel, ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TD, $ligne, $totalAnnee->totalTdPresentiel === 0.0 ? '' : $totalAnnee->totalTdPresentiel, ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TP, $ligne, $totalAnnee->totalTpPresentiel === 0.0 ? '' : $totalAnnee->totalTpPresentiel, ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TOTAL, $ligne, $totalAnnee->getTotalPresentiel() === 0.0 ? '' : $totalAnnee->getTotalPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                }
                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_DIST_CM,
                    $ligne,
                    $totalAnnee->totalCmDistanciel === 0.0 ? '' : $totalAnnee->totalCmDistanciel,
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_DIST_TD,
                    $ligne,
                    $totalAnnee->totalTdDistanciel === 0.0 ? '' : $totalAnnee->totalTdDistanciel,
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_DIST_TP,
                    $ligne,
                    $totalAnnee->totalTpDistanciel === 0.0 ? '' : $totalAnnee->totalTpDistanciel,
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_DIST_TOTAL,
                    $ligne,
                    $totalAnnee->getTotalDistanciel() === 0.0 ? '' : $totalAnnee->getTotalDistanciel(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_TOTAL,
                    $ligne,
                    $totalAnnee->getVolumeTotal() === 0.0 ? '' : $totalAnnee->getVolumeTotal(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 1,
                    $totalAnnee->getVolumeTotal() === 0.0 ? '' : $totalAnnee->getVolumeTotal(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 3,
                    $totalFormation->sommeFormationTotalPresDist() === 0.0 ? '' : $totalFormation->sommeFormationTotalPresDist(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne + 3,
                    $totalAnnee->getTotalVolumeTe() === 0.0 ? '' : $totalAnnee->getTotalVolumeTe(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne,
                    $totalAnnee->getTotalVolumeTe() === 0.0 ? '' : $totalAnnee->getTotalVolumeTe(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne + 1,
                    $totalAnnee->getTotalVolumeTe() === 0.0 ? '' : $totalAnnee->getTotalVolumeTe(),
                    ['style' => 'HORIZONTAL_CENTER']
                );

                $this->excelWriter->writeCellXY(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 2,
                    $totalAnnee->getTotalEtudiant() === 0.0 ? '' : $totalAnnee->getTotalEtudiant(),
                    ['style' => 'HORIZONTAL_CENTER']
                );
            }

            foreach ($this->lignesEcColorees as $lignesEcColoree) {
                $this->excelWriter->setRangeStyle('D' . $lignesEcColoree . ':AD' . $lignesEcColoree, [
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'rotation' => 90,
                        'startColor' => [
                            'argb' => 'FFcecece',
                        ],
                        'endColor' => [
                            'argb' => 'FFcecece',
                        ],
                    ],]);
            }


            //suppression de la ligne modèle 18
            $this->excelWriter->removeRow(18);
            $this->updateIfNotFull();
            $this->excelWriter->setPrintArea('A1:AD' . $ligne + 7);
            $this->excelWriter->configSheet(
                ['zoom' => 60,
                    'topLeftCell' => 'A1']
            );
        }
        $this->genereReferentielCompetences($parcours, $formation);

        //supprimer la feuille de modèle
        $this->excelWriter->removeSheetByIndex(0);

        if ($formation->isHasParcours() === true) {
            $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle() . ' ' . $parcours->getSigle();
        } else {
            $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle();
        }

        $this->fileName = Tools::FileName('MCCC - ' . $anneeUniversitaire->getLibelle() . ' - ' . $texte);
    }

    private function afficheUeLibre(int $ligne, StructureUe $ue): int
    {
        $this->excelWriter->insertNewRowBefore($ligne);
        $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ue->ue->getDescriptionUeLibre(), ['wrap' => true]);
        $this->excelWriter->writeCellXY(self::COL_ECTS, $ligne, $ue->ue->getEcts() === 0.0 ? '' : $ue->ue->getEcts());

        $ligne++;
        return $ligne;
    }

    private function afficheEc(int $ligne, StructureEc $structureEc): int
    {
        $ec = $structureEc->elementConstitutif;
        $this->excelWriter->insertNewRowBefore($ligne);
        $this->excelWriter->writeCellXY(self::COL_NUM_EC, $ligne, $ec->getCode());//todo: gérer les cas

        if ($ec->getNatureUeEc() !== null && $ec->getNatureUeEc()->isLibre() === true) {
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ec->getLibelle() . ' (EC à choix libre) ' . $ec->getTexteEcLibre(), ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC_EN, $ligne, '', ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_RESP_EC, $ligne, '', ['wrap' => true]);
            $this->lignesEcColorees[] = $ligne;
        } elseif ($ec->getNatureUeEc() !== null && $ec->getNatureUeEc()->isChoix() === true && $ec->getEcParent() === null) {
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ec->getLibelle() . ' (EC à choix restreint, choisir un parmi les choix ci-dessous ***)', ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC_EN, $ligne, '', ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_RESP_EC, $ligne, '', ['wrap' => true]);
            $this->lignesEcColorees[] = $ligne;
        } elseif ($ec->getFicheMatiere() !== null) {
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ec->getFicheMatiere()->getLibelle(), ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC_EN, $ligne, $ec->getFicheMatiere()->getLibelleAnglais(), ['wrap' => true]);
            $this->excelWriter->writeCellXY(self::COL_RESP_EC, $ligne, $ec->getFicheMatiere()->getResponsableFicheMatiere()?->getDisplay(), ['wrap' => true]);

            // langue
            $texte = '';
            foreach ($ec->getFicheMatiere()->getLangueDispense() as $langue) {
                $texte .= $langue->getLibelle() . "; ";
            }
            $texte = substr($texte, 0, -2);
            $this->excelWriter->writeCellXY(self::COL_LANGUE_EC, $ligne, $texte);

            $anglais = false;
            foreach ($ec->getFicheMatiere()->getLangueSupport() as $langue) {
                if (strtolower($langue->getCodeIso()) === 'en') {
                    $anglais = true;
                }
            }
            $this->excelWriter->writeCellXY(self::COL_SUPPORT_ANGLAIS, $ligne, $anglais === true ? 'O' : 'N');

//            $this->excelWriter->writeCellXY(self::COL_COURS_MUTUALISE, $ligne, $ec->getFicheMatiere()->isEnseignementMutualise() === true ? 'O' : 'N');


            // BCC
            $texte = [];
            if (isset($structureEc->bccs)) {
                foreach ($structureEc->bccs as $comp) {
                    $texte[] = $comp->getCode();
                }
            }

            //suppression des doublons
            $texte = array_unique($texte);
            $texte = implode(', ', $texte);
//            $this->excelWriter->writeCellXY(self::COL_COMPETENCES, $ligne, $texte);
        } else {
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ec->getTexteEcLibre(), ['wrap' => true]);
        }

        // MCCC
        $hasQuitus = $ec->isQuitus();
        if ($structureEc->elementConstitutif->isControleAssiduite() === false) {
            if ($structureEc->typeMccc !== null) {
                $mcccs = $this->getMcccs($structureEc, $structureEc->typeMccc);
                $displayMccc = new \App\TypeDiplome\M2E\Dto\Mccc($mcccs, $structureEc->typeMccc, $this->typeEpreuves, $hasQuitus);
                $displayMccc->calculDisplayMccc();
                $mcccArray = $displayMccc->toArray();
                foreach ($mcccArray as $key => $value) {
                    $this->excelWriter->writeCellXY(constant(self::class . '::' . $key), $ligne, $value);
                }
            }
        } else {
            $this->excelWriter->writeCellXY(self::COL_MCCC_CCI, $ligne, 'Contrôle d\'assiduité');
        }

        $this->excelWriter->writeCellXY(self::COL_TYPE_EC, $ligne, $ec->getTypeEc() ? $ec->getTypeEc()->getLibelle() : '');

        // Heures
        $this->excelWriter->writeCellXY(self::COL_ECTS, $ligne, $structureEc->heuresEctsEc->ects === 0.0 ? '' : $structureEc->heuresEctsEc->ects);
        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_CM, $ligne, $structureEc->heuresEctsEc->cmPres === 0.0 ? '' : $structureEc->heuresEctsEc->cmPres);
        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TD, $ligne, $structureEc->heuresEctsEc->tdPres === 0.0 ? '' : $structureEc->heuresEctsEc->tdPres);
        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TP, $ligne, $structureEc->heuresEctsEc->tpPres === 0.0 ? '' : $structureEc->heuresEctsEc->tpPres);
        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TOTAL, $ligne, $structureEc->heuresEctsEc->sommeEcTotalPres() === 0.0 ? '' : $structureEc->heuresEctsEc->sommeEcTotalPres());

        //si pas distanciel, griser...
        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_CM, $ligne, $structureEc->heuresEctsEc->cmDist === 0.0 ? '' : $structureEc->heuresEctsEc->cmDist);
        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TD, $ligne, $structureEc->heuresEctsEc->tdDist === 0.0 ? '' : $structureEc->heuresEctsEc->tdDist);
        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TP, $ligne, $structureEc->heuresEctsEc->tpDist === 0.0 ? '' : $structureEc->heuresEctsEc->tpDist);
        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TOTAL, $ligne, $structureEc->heuresEctsEc->sommeEcTotalDist() === 0.0 ? '' : $structureEc->heuresEctsEc->sommeEcTotalDist());
        $this->excelWriter->writeCellXY(self::COL_HEURES_AUTONOMIE, $ligne, $structureEc->heuresEctsEc->tePres === 0.0 ? '' : $structureEc->heuresEctsEc->tePres);

        $this->excelWriter->writeCellXY(self::COL_HEURES_TOTAL, $ligne, $structureEc->heuresEctsEc->sommeEcTotalPresDist() === 0.0 ? '' : $structureEc->heuresEctsEc->sommeEcTotalPresDist());

        $ligne++;
        return $ligne;
    }

//    private function afficheSommeSemestre(int $ligne, TotalVolumeHeure $totalAnnee, StructureSemestre $semestre): int
//    {
//        $this->excelWriter->insertNewRowBefore($ligne);
//
////        $this->excelWriter->mergeCellsCaR(self::COL_UE, $ligne, self::COL_COMPETENCES, $ligne);
//        $this->excelWriter->mergeCellsCaR(self::COL_MCCC_CCI, $ligne, self::COL_MCCC_SECONDE_CHANCE_CT, $ligne);
//        $this->excelWriter->writeCellXY(self::COL_UE, $ligne, 'Total semestre S' . $semestre->ordre . ' ', ['style' => 'HORIZONTAL_RIGHT']);
//        //somme ECTS semestre
//        $this->excelWriter->writeCellXY(self::COL_ECTS, $ligne, $semestre->heuresEctsSemestre->sommeSemestreEcts === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreEcts, ['style' => 'HORIZONTAL_CENTER']);
//
//        //ligne de somme du semestre
//        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_CM, $ligne, $semestre->heuresEctsSemestre->sommeSemestreCmPres === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreCmPres, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TD, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTdPres === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTdPres, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TP, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTpPres === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTpPres, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_PRES_TOTAL, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTotalPres() === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTotalPres(), ['style' => 'HORIZONTAL_CENTER']);
//
//        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_CM, $ligne, $semestre->heuresEctsSemestre->sommeSemestreCmDist === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreCmDist, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TD, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTdDist === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTdDist, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TP, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTpDist === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTpDist, ['style' => 'HORIZONTAL_CENTER']);
//        $this->excelWriter->writeCellXY(self::COL_HEURES_DIST_TOTAL, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTotalDist() === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTotalDist(), ['style' => 'HORIZONTAL_CENTER']);
//
//        $this->excelWriter->writeCellXY(self::COL_HEURES_TOTAL, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTotalPresDist() === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTotalPresDist(), ['style' => 'HORIZONTAL_CENTER']);
//
//        $this->excelWriter->writeCellXY(self::COL_HEURES_AUTONOMIE, $ligne, $semestre->heuresEctsSemestre->sommeSemestreTePres === 0.0 ? '' : $semestre->heuresEctsSemestre->sommeSemestreTePres, ['style' => 'HORIZONTAL_CENTER']);
//        $this->lignesSemestre[] = $ligne;
//
//        $ligne++;
//        return $ligne;
//    }

    private function genereReferentielCompetences(Parcours $parcours, Formation $formation): void
    {
        $modele = $this->excelWriter->getSheetByName(self::PAGE_REF_COMPETENCES);
        if ($modele === null) {
            throw new \Exception('Le modèle n\'existe pas');
        }

        //en-tête du fichier
        $modele->setCellValue(self::CEL_ANNEE_UNIVERSITAIRE, 'Année Universitaire ' . $formation->getDpe()?->getLibelle());
        $modele->setCellValue('D5', $formation->getTypeDiplome()?->getLibelle());
        $modele->setCellValue('D6', $formation->getDisplay());
        $modele->setCellValue('D7', $parcours->isParcoursDefaut() === false ? $parcours->getDisplay() : '');
        $modele->setCellValue('D11', $formation->getComposantePorteuse()?->getLibelle());
        $modele->setCellValue('D13', $parcours->getLocalisation()?->getLibelle());

        $bccs = $parcours->getBlocCompetences();

        $ligne = 16;
        $this->excelWriter->setSheet($modele);
        foreach ($bccs as $bcc) {
            $this->excelWriter->writeCellXY(1, $ligne, $bcc->getCode(), ['font-weight' => 'bold', 'style' => 'HORIZONTAL_RIGHT']);
            $this->excelWriter->writeCellXY(2, $ligne, $bcc->getLibelle(), ['wrap' => true, 'font-weight' => 'bold']);
            $this->excelWriter->mergeCellsCaR(2, $ligne, 3, $ligne);
            $ligne++;
            foreach ($bcc->getCompetences() as $competence) {
                $this->excelWriter->writeCellXY(2, $ligne, $competence->getCode(), ['font-weight' => 'bold', 'style' => 'HORIZONTAL_RIGHT', 'valign' => 'VERTICAL_CENTER']);
                $this->excelWriter->mergeCellsCaR(3, $ligne, 4, $ligne);
                $this->excelWriter->writeCellXY(3, $ligne, $competence->getLibelle(), ['wrap' => true]);
                $height = ceil(strlen($competence->getLibelle()) / 140) * 15;
                $this->excelWriter->getRowDimension($ligne, $height);
                $ligne++;
            }
            $ligne++;
        }

        $this->excelWriter->getColumnsAutoSizeInt(4, 4);
        $this->excelWriter->setPrintArea('A1:D' . $ligne);
        $this->excelWriter->setOrientationPage();
        $this->excelWriter->configSheet(
            ['zoom' => 60, 'topLeftCell' => 'A1']
        );
    }

    public function exportPdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): Response
    {
        $this->genereExcelMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

        $fichier = $this->excelWriter->saveFichier($this->fileName, $this->dir . '/temp/');

        $request = Gotenberg::libreOffice('http://localhost:3000')
            ->convert(Stream::path($fichier));

        $reponse = $this->client->sendRequest($request);

        if ($reponse) {
            unlink($this->dir . '/temp/' . $this->fileName . '.xlsx');
        }

        // retourner une réponse avec le contenu du PDF
        return new Response($reponse->getBody()->getContents(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->fileName . '.pdf"',
        ]);
    }

    public function exportAndSaveExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): string
    {
        try {
            $this->genereExcelMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
        $this->excelWriter->saveFichier($this->fileName, $dir);
        return $this->fileName . '.xlsx';
    }

    //    private function displayTypeEpreuveWithDureePourcentage(Mccc $mccc, ?bool $quitus = false): string
    //    {
    //        $texte = '';
    //        foreach ($mccc->getTypeEpreuve() as $type) {
    //            if ($type !== "" && $this->typeEpreuves[$type] !== null) {
    //
    //                    $duree = '';
    //                    if ($this->typeEpreuves[$type]->isHasDuree() === true) {
    //                        $duree = ' ' . $this->displayDuree($mccc->getDuree());
    //                    }
    //
    //                    $texte .= $this->typeEpreuves[$type]->getSigle() . $duree . ' (' . $mccc->getPourcentage() . '%); ';
    //
    //            } else {
    //                $texte .= 'erreur épreuve; ';
    //            }
    //        }
    //
    //        return $texte;
    //    }

    public function exportAndSavePdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): string
    {
        $this->genereExcelMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

        $fichier = $this->excelWriter->saveFichier($this->fileName, $dir);

        $request = Gotenberg::libreOffice('http://localhost:3000')
            ->outputFilename($this->fileName)
            ->convert(Stream::path($fichier));

        return Gotenberg::save($request, $dir);
    }


}
