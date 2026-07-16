<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Export/LicenceMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/05/2023 16:14
 */

namespace App\TypeDiplome\Licence\Services;

use App\Classes\Excel\ExcelWriter;
use App\DTO\DiffObject;
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
use App\Service\VersioningParcours;
use App\Service\VersioningStructure;
use App\Utils\Tools;
use DateTimeInterface;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class LicenceMcccVersion extends AbstractLicenceMccc
{
    //todo: ajouter un watermark sur le doc ou une mention que la mention est définitive ou pas.
    //todo: gérer la date de vote

    public function __construct(
        private LicenceMccc $licenceMccc,
        KernelInterface                   $kernel,
        protected ClientInterface         $client,
        protected CalculStructureParcoursLicence $calculStructureParcours,
        protected VersioningParcours      $versioningParcours,
        protected ExcelWriter             $excelWriter,
        protected TypeEpreuveRepository   $typeEpreuveRepository
    )
    {
        parent::__construct($excelWriter);
        $this->dir = $kernel->getProjectDir() . '/public';

    }

    public function exportExcelLicenceMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): StreamedResponse|false
    {
        $rep = $this->genereExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        if ($rep === false) {
            return false;
        }
        return $this->excelWriter->genereFichier($this->fileName);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function genereExcelLicenceMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): bool
    {
        $this->getTypeEpreuves();
        $this->versionFull = $versionFull;
        $formation = $parcours->getFormation();

        if (null === $formation) {
            throw new \Exception('La formation n\'existe pas');
        }

        $dto = $this->calculStructureParcours->calcul($parcours, dataFromFicheMatiere: true);

        $structureDifferencesParcours = $this->versioningParcours->getStructureDifferencesBetweenParcoursAndLastCfvu($parcours);
        if ($structureDifferencesParcours !== null) {
            $diffStructure = (new VersioningStructure($structureDifferencesParcours, $dto))->calculDiff();

            // Descriptifs Parcours
            $lastParcoursCfvuDesc = $this->versioningParcours
                ->loadParcoursFromVersion(
                    $this->versioningParcours->getLastVersionOrLastYearCfvu($parcours)
                )['parcours'];
            $diffDescriptifs = VersioningStructure::calculDiffDescriptifs($lastParcoursCfvuDesc, $parcours);

        } else {
            return false;
        }

        $this->excelWriter->createFromTemplate('Annexe_MCCC.xlsx');

        // Prépare le modèle avant de dupliquer
        $modele = $this->excelWriter->getSheetByName(self::PAGE_MODELE);
        if ($modele === null) {
            throw new \Exception('Le modèle n\'existe pas');
        }

        $this->excelWriter->setSheet($modele);
        //récupération des données
        // récupération des semestres du parcours puis classement par année et par ordre
        $tabSemestresAnnee = $dto->getTabAnnee();

        //en-tête du fichier
        $modele->setCellValue(self::CEL_ANNEE_UNIVERSITAIRE, 'Année Universitaire ' . $anneeUniversitaire->getAnneeUniversitaire()?->getLibelle());
        $modele->setCellValue(self::CEL_TYPE_FORMATION, $formation->getTypeDiplome()?->getLibelle());
        // $modele->setCellValue(self::CEL_INTITULE_FORMATION, $formation->getDisplay());
        // $modele->setCellValue(self::CEL_INTITULE_PARCOURS, $parcours->isParcoursDefaut() === false ? $parcours->getDisplay() : '');
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_INTITULE_FORMATION, 0, 1),
            substr(self::CEL_INTITULE_FORMATION, 1, 1),
            $diffDescriptifs['libelleMention']
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_INTITULE_PARCOURS, 0, 1),
            substr(self::CEL_INTITULE_PARCOURS, 1, 1),
            $diffDescriptifs['libelleParcours']
        );
        $modele->setCellValue(self::CEL_COMPOSANTE, $formation->getComposantePorteuse()?->getLibelle());
        if ($formation->isHasParcours() === false) {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $formation->getLocalisationMention()[0]?->getLibelle());
        } else {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $parcours->getLocalisation()?->getLibelle());
        }
        // $modele->setCellValue(self::CEL_RESPONSABLE_MENTION, $formation->getResponsableMention()?->getDisplay());
        // $modele->setCellValue(self::CEL_RESPONSABLE_PARCOURS, $parcours->getRespParcours()?->getDisplay());
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_RESPONSABLE_MENTION, 0, 1),
            substr(self::CEL_RESPONSABLE_MENTION, 1, 2),
            $diffDescriptifs['respFormation']
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_RESPONSABLE_PARCOURS, 0, 1),
            substr(self::CEL_RESPONSABLE_PARCOURS, 1, 2),
            $diffDescriptifs['respParcours']
        );

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

        // Différences Régimes d'Inscription
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FI, 0, 1),
            substr(self::CEL_REGIME_FI, 1, 1),
            $diffDescriptifs['regimeInscription']['FI'],
            ['withLighterGreen' => true]
        );
        $this->excelWriter->writeCellXYDiff(
            substr(self::CEL_REGIME_FC, 0, 1),
            substr(self::CEL_REGIME_FC, 1, 1),
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
                $totalAnneeOriginal = new TotalVolumeHeure();
                $this->excelWriter->setSheet($clonedWorksheet);
                $this->excelWriter->writeCellName(self::CEL_ANNEE_ETUDE, Tools::adjNumeral($i) . ' année');
                $this->lignesSemestre = [];
                $this->lignesEcColorees = [];
                /** @var StructureSemestre $semestre */
                foreach ($semestres as $ordre => $semestre) {
                    $diffSemestre = $diffStructure['semestres'][$ordre];
                    $totalAnnee->addSemestre($semestre->heuresEctsSemestre);
                    $totalAnneeOriginal->addSemestreDiff($diffSemestre['heuresEctsSemestre']);
                    $debutSemestre = $ligne;

                    foreach ($semestre->ues as $ordUe => $ue) { //todo: changement ici avec modif du DTO ? un impact ?
                        $diffUe = $diffSemestre['ues'][$ordUe];
                        //UE
                        $debut = $ligne;
                        if (count($ue->uesEnfants()) === 0) {
                            if ($ue->ue->getNatureUeEc() !== null && $ue->ue->getNatureUeEc()->isLibre()) {
                                $ligne = $this->afficheUeLibre($ligne, $ue);
                            } else {
                                $tabEcAffiches = [];
                                //Si des UE enfants, on affiche pas les éventuels EC résiduels,
                                foreach ($ue->elementConstitutifs as $ordEc => $ec) {
                                    $tabEcAffiches[] = $ordEc;
                                    $diffEc = $diffUe['elementConstitutifs'][$ordEc];
                                    $ligne = $this->afficheEc($ligne, $ec, $diffEc);
                                    foreach ($ec->elementsConstitutifsEnfants as $ordEce => $ece) {
                                        if ($diffEc !== null && array_key_exists('ecEnfants', $diffEc) && array_key_exists($ordEce, $diffEc['ecEnfants'])) {
                                            $ligne = $this->afficheEc($ligne, $ece, $diffEc['ecEnfants'][$ordEce]);
                                        }
                                    }
                                }

                                //traitement des EC supprimés
                                foreach ($diffUe['elementConstitutifs'] as $ordEce => $ece) {
                                    if (!in_array($ordEce, $tabEcAffiches)) {
                                        //EC supprimé
                                        $ligne = $this->afficheEcSupprime($ligne, $ece);
                                    }
                                }


                                if ($debut < $ligne - 1) {
                                    $this->excelWriter->mergeCellsCaR(self::COL_UE, $debut, self::COL_UE, $ligne - 1);
                                    $this->excelWriter->mergeCellsCaR(self::COL_INTITULE_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);

                                    // bordure fine

                                    $this->excelWriter->borderOutsiteInside(self::COL_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_NUM_EC, $debut, self::COL_INTITULE_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_INTITULE_EC_EN, $debut, self::COL_INTITULE_EC_EN, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_RESP_EC, $debut, self::COL_RESP_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_LANGUE_EC, $debut, self::COL_LANGUE_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_SUPPORT_ANGLAIS, $debut, self::COL_SUPPORT_ANGLAIS, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_TYPE_EC, $debut, self::COL_TYPE_EC, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_COURS_MUTUALISE, $debut, self::COL_COURS_MUTUALISE, $ligne - 1);
                                    $this->excelWriter->borderOutsiteInside(self::COL_COMPETENCES, $debut, self::COL_COMPETENCES, $ligne - 1);
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

                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CT, $debut, self::COL_MCCC_SECONDE_CHANCE_CT, $ligne - 1);

                                    $this->excelWriter->borderOutsiteInside(self::COL_MCCC_SECONDE_CHANCE_CC_SANS_TP, $debut, self::COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP, $ligne - 1);
                                }
                            }
                            $this->excelWriter->writeCellXYDiff(
                                self::COL_UE,
                                $debut,
                                $diffUe['display'],
                                ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'font-weight' => false]
                            );
                            $this->excelWriter->writeCellXYDiff(self::COL_INTITULE_UE, $debut, $diffUe['libelle'], ['wrap' => true]);
                        }
                        //Affichage des UE enfants
                        foreach ($ue->uesEnfants() as $ordUee => $uee) {

                            if ($diffUe !== null && array_key_exists('uesEnfants', $diffUe) && array_key_exists($ordUee, $diffUe['uesEnfants'])) {
                                $diffUee = $diffUe['uesEnfants'][$ordUee];
                                $debut = $ligne;
                                if ($uee->ue->getNatureUeEc() !== null && $uee->ue->getNatureUeEc()->isLibre()) {
                                    $ligne = $this->afficheUeLibre($ligne, $uee);
                                } else {
                                    foreach ($uee->elementConstitutifs as $ordEc => $ec) {
                                        $diffEc = $diffUee['elementConstitutifs'][$ordEc];
                                        $ligne = $this->afficheEc($ligne, $ec, $diffEc);
                                        foreach ($ec->elementsConstitutifsEnfants as $ordEce => $ece) {
                                            if (array_key_exists('ecEnfants', $diffEc)) {
                                                $ligne = $this->afficheEc($ligne, $ece, $diffEc['ecEnfants'][$ordEce]);
                                            }
                                            //                                            else {
                                            //                                                $ligne = $this->afficheEc($ligne, $ece, []);
                                            //                                            }
                                        }
                                    }

                                    if ($debut < $ligne - 1) {
                                        $this->excelWriter->mergeCellsCaR(self::COL_UE, $debut, self::COL_UE, $ligne - 1);
                                        $this->excelWriter->mergeCellsCaR(self::COL_INTITULE_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);
                                    }
                                }
                                $this->excelWriter->writeCellXY(self::COL_UE, $debut, $uee->display, ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'font-weight' => false]);
                                $this->excelWriter->writeCellXY(self::COL_INTITULE_UE, $debut, $uee->ue->getLibelle(), ['wrap' => true]);

                                // Enfant des UE enfants
                                foreach ($uee->uesEnfants() as $ordUeee => $ueee) {
                                    if (array_key_exists('uesEnfants', $diffUee) && array_key_exists($ordUeee, $diffUee['uesEnfants'])) {
                                        $diffUeee = $diffUee['uesEnfants'][$ordUeee];
                                        $debut = $ligne;
                                        if ($ueee->ue->getNatureUeEc() !== null && $ueee->ue->getNatureUeEc()->isLibre()) {
                                            $ligne = $this->afficheUeLibre($ligne, $ueee);
                                        } else {
                                            foreach ($ueee->elementConstitutifs as $ordEcee => $ecee) {
                                                $diffEcee = $diffUeee['elementConstitutifs'][$ordEcee];
                                                $ligne = $this->afficheEc($ligne, $ecee, $diffEcee);
                                                foreach ($ecee->elementsConstitutifsEnfants as $ordEceee => $eceee) {
                                                    if (array_key_exists('ecEnfants', $diffEcee)) {
                                                        $ligne = $this->afficheEc($ligne, $eceee, $diffEcee['ecEnfants'][$ordEceee]);
                                                    }
                                                    //                                                    else {
                                                    //                                                        $ligne = $this->afficheEc($ligne, $eceee, []);
                                                    //                                                    }
                                                }
                                            }

                                            if ($debut < $ligne - 1) {
                                                $this->excelWriter->mergeCellsCaR(self::COL_UE, $debut, self::COL_UE, $ligne - 1);
                                                $this->excelWriter->mergeCellsCaR(self::COL_INTITULE_UE, $debut, self::COL_INTITULE_UE, $ligne - 1);
                                            }
                                        }
                                        $this->excelWriter->writeCellXY(self::COL_UE, $debut, $ueee->display, ['wrap' => true, 'style' => 'HORIZONTAL_CENTER', 'font-weight' => false]);
                                        $this->excelWriter->writeCellXY(self::COL_INTITULE_UE, $debut, $ueee->ue->getLibelle(), ['wrap' => true]);
                                    }
                                }
                            }
                        }
                    }

                    //                    //traitement des UE supprimés
                    //                    foreach ($diffSemestre['ues'] as $ordreUe => $ue) {
                    //                        if (!in_array($ordreUe, $tabEcAffiches)) {
                    //                            //EC supprimé
                    //                            $ligne = $this->afficheEcSupprime($ligne, $ece);
                    //                        }
                    //                    }


                    $ligne = $this->afficheSommeSemestre($ligne, $semestre, $diffSemestre);

                    $this->excelWriter->mergeCellsCaR(self::COL_SEMESTRE, $debutSemestre, self::COL_SEMESTRE, $ligne - 1);
                    $this->excelWriter->writeCellXY(self::COL_SEMESTRE, $debutSemestre, 'S' . $semestre->ordre);

                    $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_CM, $ligne, new DiffObject($totalAnneeOriginal->totalCmPresentiel, $totalAnnee->totalCmPresentiel));
                    $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TD, $ligne, new DiffObject($totalAnneeOriginal->totalTdPresentiel, $totalAnnee->totalTdPresentiel));
                    $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TP, $ligne, new DiffObject($totalAnneeOriginal->totalTpPresentiel, $totalAnnee->totalTpPresentiel));
                    $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TOTAL, $ligne, new DiffObject($totalAnneeOriginal->getTotalPresentiel(), $totalAnnee->getTotalPresentiel()));
                }
                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_DIST_CM,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->totalCmDistanciel, $totalAnnee->totalCmDistanciel)
                );
                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_DIST_TD,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->totalTdDistanciel, $totalAnnee->totalTdDistanciel)
                );
                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_DIST_TP,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->totalTpDistanciel, $totalAnnee->totalTpDistanciel)
                );
                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_DIST_TOTAL,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->getTotalDistanciel(), $totalAnnee->getTotalDistanciel())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_TOTAL,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->getVolumeTotal(), $totalAnnee->getVolumeTotal())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 1,
                    new DiffObject($totalAnneeOriginal->getVolumeTotal(), $totalAnnee->getVolumeTotal())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 3,
                    $diffStructure['heuresEctsFormation']['sommeFormationTotalPresDist']
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne + 3,
                    new DiffObject($totalAnneeOriginal->getTotalVolumeTe(), $totalAnnee->getTotalVolumeTe())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne,
                    new DiffObject($totalAnneeOriginal->getTotalVolumeTe(), $totalAnnee->getTotalVolumeTe())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_AUTONOMIE,
                    $ligne + 1,
                    new DiffObject($totalAnneeOriginal->getTotalVolumeTe(), $totalAnnee->getTotalVolumeTe())
                );

                $this->excelWriter->writeCellXYDiff(
                    self::COL_HEURES_PRES_CM,
                    $ligne + 2,
                    new DiffObject($totalAnneeOriginal->getTotalEtudiant(), $totalAnnee->getTotalEtudiant())
                );
            }

            // couleur des lignes semestres
            foreach ($this->lignesSemestre as $ligneSemestre) {
                $this->excelWriter->setRangeStyle('B' . $ligneSemestre . ':AD' . $ligneSemestre, [
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'rotation' => 90,
                        'startColor' => [
                            'argb' => 'FFafafaf',
                        ],
                        'endColor' => [
                            'argb' => 'FFafafaf',
                        ],
                    ],]);
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
            $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $parcours->getSigle() . ' ' . $parcours->getSigle();
        } else {
            $texte = $formation->gettypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle();
        }

        $this->fileName = Tools::FileName('MCCC-version-' . $anneeUniversitaire->getLibelle() . ' - ' . $texte);
        return true;
    }

    private function afficheUeLibre(int $ligne, StructureUe $ue): int
    {
        $this->excelWriter->insertNewRowBefore($ligne);
        $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ue->ue->getDescriptionUeLibre(), ['wrap' => true]);
        $this->excelWriter->writeCellXY(self::COL_ECTS, $ligne, $ue->ue->getEcts() === 0.0 ? '' : $ue->ue->getEcts());

        $ligne++;
        return $ligne;
    }

    private function afficheEc(int $ligne, StructureEc $structureEc, ?array $diffEc): int
    {
        if ($diffEc === null || $diffEc === []) {
            $diffEc['libelle'] = new DiffObject('', '');
            $diffEc['typeMccc'] = new DiffObject('', '');
            $diffEc['mcccs'] = [];

        }

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
            $this->excelWriter->writeCellXYDiff(self::COL_INTITULE_EC, $ligne, $diffEc['libelle'], ['wrap' => true]);
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

            $this->excelWriter->writeCellXY(self::COL_COURS_MUTUALISE, $ligne, $ec->getFicheMatiere()->isEnseignementMutualise() === true ? 'O' : 'N');


            // BCC
            $texte = [];
            foreach ($structureEc->bccs as $comp) {
                $texte[] = $comp->getCode();
            }

            //suppression des doublons
            $texte = array_unique($texte);
            $texte = implode(', ', $texte);
            $this->excelWriter->writeCellXY(self::COL_COMPETENCES, $ligne, $texte);
        } else {
            $this->excelWriter->writeCellXY(self::COL_INTITULE_EC, $ligne, $ec->getTexteEcLibre(), ['wrap' => true]);
        }

        // MCCC
        if ($structureEc->elementConstitutif->isControleAssiduite() === true) {
            $this->excelWriter->writeCellXY(self::COL_MCCC_CCI, $ligne, 'Contrôle d\'assiduité');
        } else {
            if (array_key_exists('mcccs', $diffEc) && array_key_exists('original', $diffEc['mcccs']) && array_key_exists('new', $diffEc['mcccs'])) {
                $mcccsOriginal = $this->getMcccs($diffEc['mcccs']['original'], $diffEc['typeMccc']->original ?? '');
                $mcccsNew = $this->getMcccs($diffEc['mcccs']['new'], $diffEc['typeMccc']->new);

                //cas Original sans écrire dans les cellules
                $displayMcccOriginal = new \App\TypeDiplome\Licence\Dto\Mccc($mcccsOriginal, $diffEc['typeMccc']->original ?? '', $this->typeEpreuves, $diffEc['quitus']->original ?? false);
                $displayMcccOriginal->calculDisplayMccc();
                $displayMcccNew = new \App\TypeDiplome\Licence\Dto\Mccc($mcccsNew, $diffEc['typeMccc']->new, $this->typeEpreuves, $diffEc['quitus']->new ?? false);
                $displayMcccNew->calculDisplayMccc();

                // utiliser le nouveau DTO via toArray()
                $diffMccc = [];
                $origArray = $displayMcccOriginal->toArray();
                $newArray = $displayMcccNew->toArray();

                $keys = array_unique(array_merge(array_keys($origArray), array_keys($newArray)));
                foreach ($keys as $key) {
                    $orig = array_key_exists($key, $origArray) ? $origArray[$key] : '';
                    $new = array_key_exists($key, $newArray) ? $newArray[$key] : '';
                    $diffMccc[$key] = new DiffObject($orig, $new);
                }

                foreach ($diffMccc as $key => $value) {
                    $this->excelWriter->writeCellXYDiff(constant(self::class . '::' . $key), $ligne, $value);
                }
            } elseif (array_key_exists('mcccs', $diffEc) && array_key_exists('new', $diffEc['mcccs'])) {
                $mcccsNew = $this->getMcccs($diffEc['mcccs']['new'], $diffEc['typeMccc']->new);

                //$displayMcccNew = $this->calculDisplayMccc($mcccsNew, $diffEc['typeMccc']->new, $diffEc['quitus']->new ?? false);
                $displayMcccNew = new \App\TypeDiplome\Licence\Dto\Mccc($mcccsNew, $diffEc['typeMccc']->new, $this->typeEpreuves, $diffEc['quitus']->new ?? false);
                $displayMcccNew->calculDisplayMccc();

                foreach ($displayMcccNew->toArray() as $key => $value) {
                    $diffMccc[$key] = new DiffObject('', $value);
                }

                foreach ($diffMccc as $key => $value) {
                    $this->excelWriter->writeCellXYDiff(constant(self::class . '::' . $key), $ligne, $value);
                }
            }

            $this->excelWriter->writeCellXY(self::COL_TYPE_EC, $ligne, $ec->getTypeEc() ? $ec->getTypeEc()->getLibelle() : '');
        }
        // Heures
        $this->excelWriter->writeCellXYDiff(self::COL_ECTS, $ligne, $diffEc['heuresEctsEc']['ects']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_CM, $ligne, $diffEc['heuresEctsEc']['cmPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TD, $ligne, $diffEc['heuresEctsEc']['tdPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TP, $ligne, $diffEc['heuresEctsEc']['tpPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalPres']);

        //si pas distanciel, griser...
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_CM, $ligne, $diffEc['heuresEctsEc']['cmDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TD, $ligne, $diffEc['heuresEctsEc']['tdDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TP, $ligne, $diffEc['heuresEctsEc']['tpDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_AUTONOMIE, $ligne, $diffEc['heuresEctsEc']['tePres']);

        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalPresDist']);

        $ligne++;
        return $ligne;
    }

    private function afficheEcSupprime(int $ligne, array $diffEc): int
    {
        $this->excelWriter->insertNewRowBefore($ligne);
        $this->excelWriter->writeCellXYDiff(self::COL_NUM_EC, $ligne, $diffEc['code']);
        $this->excelWriter->writeCellXYDiff(self::COL_INTITULE_EC, $ligne, $diffEc['libelle'], ['wrap' => true]);

        // Heures
        $this->excelWriter->writeCellXYDiff(self::COL_ECTS, $ligne, $diffEc['heuresEctsEc']['ects']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_CM, $ligne, $diffEc['heuresEctsEc']['cmPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TD, $ligne, $diffEc['heuresEctsEc']['tdPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TP, $ligne, $diffEc['heuresEctsEc']['tpPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalPres']);

        //si pas distanciel, griser...
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_CM, $ligne, $diffEc['heuresEctsEc']['cmDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TD, $ligne, $diffEc['heuresEctsEc']['tdDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TP, $ligne, $diffEc['heuresEctsEc']['tpDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_AUTONOMIE, $ligne, $diffEc['heuresEctsEc']['tePres']);

        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_TOTAL, $ligne, $diffEc['heuresEctsEc']['sommeEcTotalPresDist']);

        $ligne++;
        return $ligne;
    }

    private function afficheSommeSemestre(int $ligne, StructureSemestre $semestre, $diffSemestre): int
    {
        $this->excelWriter->insertNewRowBefore($ligne);

        $this->excelWriter->mergeCellsCaR(self::COL_UE, $ligne, self::COL_COMPETENCES, $ligne);
        $this->excelWriter->mergeCellsCaR(self::COL_MCCC_CCI, $ligne, self::COL_MCCC_SECONDE_CHANCE_CT, $ligne);
        $this->excelWriter->writeCellXY(self::COL_UE, $ligne, 'Total semestre S' . $semestre->ordre . ' ', ['style' => 'HORIZONTAL_RIGHT']);
        //somme ECTS semestre
        $this->excelWriter->writeCellXYDiff(self::COL_ECTS, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreEcts']);

        //ligne de somme du semestre
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_CM, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreCmPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TD, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTdPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TP, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTpPres']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_PRES_TOTAL, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTotalPres']);

        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_CM, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreCmDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TD, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTdDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TP, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTpDist']);
        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_DIST_TOTAL, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTotalDist']);

        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_TOTAL, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTotalPresDist']);

        $this->excelWriter->writeCellXYDiff(self::COL_HEURES_AUTONOMIE, $ligne, $diffSemestre['heuresEctsSemestre']['sommeSemestreTePres']);
        $this->lignesSemestre[] = $ligne;

        $ligne++;
        return $ligne;
    }

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

    public function exportPdfLicenceMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): Response
    {
        $this->genereExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

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

    public function exportAndSaveExcelLicenceMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        string             $fichier,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string|false
    {
        $rep = $this->genereExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);
        if ($rep === false) {
            return false;
        }
        $this->fileName = $fichier;
        $this->excelWriter->saveFichier($this->fileName, $dir);
        return $this->fileName . '.xlsx';
    }

    public function exportAndSavePdfLicenceMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): string
    {
        $this->genereExcelLicenceMccc($anneeUniversitaire, $parcours, $dateCfvu, $dateConseil, $versionFull);

        $fichier = $this->excelWriter->saveFichier($this->fileName, $dir);

        $request = Gotenberg::libreOffice('http://localhost:3000')
            ->outputFilename($this->fileName)
            ->convert(Stream::path($fichier));

        return Gotenberg::save($request, $dir);
    }

}
