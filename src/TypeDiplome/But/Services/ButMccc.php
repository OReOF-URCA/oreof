<?php
/*
 * Copyright (c) 2023. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Export/butMcccMccc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 24/05/2023 16:14
 */

namespace App\TypeDiplome\But\Services;

use App\Classes\Excel\ExcelWriter;
use App\Entity\CampagneCollecte;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Repository\FicheMatiereRepository;
use App\Utils\Tools;
use DateTimeInterface;
use Gotenberg\Gotenberg;
use Gotenberg\Stream;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class ButMccc extends AbstractButMccc
{
    //todo: ajouter un watermark sur le doc ou une mention que la mention est définitive ou pas.
    //todo: gérer la date de vote


    private string $fileName;
    private Parcours $parcours;

    private string $dir;


    public function __construct(
        KernelInterface                  $kernel,
        protected ClientInterface        $client,
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
    ): void {
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

        //todo: gérer la date de publication et un "marquage" sur le document si pré-CFVU
        $formation = $parcours->getFormation();
        $this->parcours = $parcours;

        if (null === $formation) {
            throw new \Exception('La formation n\'existe pas');
        }

        $this->excelWriter->createFromTemplate('Annexe_MCCC_BUT.xlsx');

        // Prépare le modèle avant de dupliquer
        $modele = $this->excelWriter->getSheetByName(self::PAGE_MODELE);

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
        $modele->setCellValue(self::CEL_INTITULE_FORMATION, $formation->getDisplay());

        if ($formation->isHasParcours() === false) {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $formation->getLocalisationMention()[0]?->getLibelle());
        } else {
            $modele->setCellValue(self::CEL_SITE_FORMATION, $parcours->getLocalisation()?->getLibelle());
            $modele->setCellValue(self::CEL_INTITULE_PARCOURS, $parcours->getDisplay());
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

        $index = 1;

        //recopie du modèle sur chaque année, puis remplissage
        foreach ($tabSemestres as $i => $semestres) {
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


                    foreach ($ue->getElementConstitutifs() as $ec) {
                        $fiche = $ec->getFicheMatiere();
                        if ($fiche !== null) {
                            if ($fiche->getTypeMatiere() === FicheMatiere::TYPE_MATIERE_RESSOURCE && $fiche->getSigle() !== null) {
                                $tabFichesRessources[$fiche->getSigle()] = $ec;
                                $tabFichesUes[$fiche->getSigle()][$ue->getId()] = $ec->getEcts();
                            }

                            if ($fiche->getTypeMatiere() === FicheMatiere::TYPE_MATIERE_SAE && $fiche->getSigle() !== null) {
                                $tabFichesSaes[$fiche->getSigle()] = $ec;
                                $tabFichesUes[$fiche->getSigle()][$ue->getId()] = $ec->getEcts();
                            }
                        }
                    }


                    $tabColUes[$ue->getId()] = $colUe;
                    $this->excelWriter->writeCellXY($colUe, 18, 'BC' . $ue->getOrdre(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY($colUe, 26, $ue->getEcts(), ['style' => 'HORIZONTAL_CENTER']);
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
                    $fiche = $ec->getFicheMatiere();
                    $this->excelWriter->insertNewRowBefore($ligne);
                    $this->excelWriter->writeCellXY(self::COL_CODE_ELEMENT, $ligne, '', ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CODE_EC, $ligne, $fiche->getSigle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_INTITULE, $ligne, $fiche->getLibelle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_VOL_ETUDIANT, $ligne, $fiche->getVolumeEtudiant(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CM, $ligne, $fiche->getVolumeCmPresentiel() === 0.0 ? '' : $fiche->getVolumeCmPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_TD, $ligne, $fiche->getVolumeTdPresentiel() === 0.0 ? '' : $fiche->getVolumeTdPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_TP, $ligne, $fiche->getVolumeTpPresentiel() === 0.0 ? '' : $fiche->getVolumeTpPresentiel(), ['style' => 'HORIZONTAL_CENTER']);

                    //MCCC
                    $this->writeMccc($fiche, $tabColonnes, $ligne);
                    $this->writeAcUe($fiche, $ligne, $tabColUes, $tabFichesUes);


                    $ligne++;
                }

                $finRessource = $ligne - 1;

                $this->excelWriter->insertNewRowBefore($ligne);
                $ligne++;
                $debutSae = $ligne;

                foreach ($tabFichesSaes as $ec) {
                    $fiche = $ec->getFicheMatiere();
                    $this->excelWriter->insertNewRowBefore($ligne);
                    $this->excelWriter->writeCellXY(self::COL_CODE_ELEMENT, $ligne, '', ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CODE_EC, $ligne, $fiche->getSigle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_INTITULE, $ligne, $fiche->getLibelle(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_VOL_ETUDIANT, $ligne, $fiche->getVolumeEtudiant(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_CM, $ligne, $fiche->getVolumeCmPresentiel() === 0.0 ? '' : $fiche->getVolumeCmPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_TD, $ligne, $fiche->getVolumeTdPresentiel() === 0.0 ? '' : $fiche->getVolumeTdPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_TP, $ligne, $fiche->getVolumeTpPresentiel() === 0.0 ? '' : $fiche->getVolumeTpPresentiel(), ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY(self::COL_HEURE_AUTONOMIE, $ligne, $fiche->getVolumeTe() === 0.0 ? '' : $fiche->getVolumeTe());

                    //MCCC
                    $this->writeMccc($fiche, $tabColonnes, $ligne);
                    $this->writeAcUe($fiche, $ligne, $tabColUes, $tabFichesUes);

                    $ligne++;
                }

                $this->excelWriter->colorCells('L' . $debutSae . ':W' . ($ligne - 1), 'FFCCCCCC');
                $this->excelWriter->colorCells('X23:AK' . $finRessource, 'FFCCCCCC');
                $this->excelWriter->colorCells('H23:H' . $finRessource, 'FFCCCCCC');
                $a = $ligne + 4;
                $b = $ligne + 3;
                foreach ($tabColUes as $colUe) {
                    $lettreCol = Coordinate::stringFromColumnIndex($colUe);
                    //pour chaque colonne d'uE on met à jour la somme des ECTS dans la formule
                    $this->excelWriter->writeCellXY($colUe, $ligne + 3, '=SUM(' . Coordinate::stringFromColumnIndex($colUe) . '23:' . $lettreCol . ($ligne - 1) . ')', ['style' => 'HORIZONTAL_CENTER']);
                    $this->excelWriter->writeCellXY($colUe, $ligne + 4, '=SUM(' . $lettreCol . $debutSae . ':' . $lettreCol . ($ligne - 1) . ')', ['style' => 'HORIZONTAL_CENTER']);

                    $this->excelWriter->writeCellXY($colUe, $ligne + 5, '=' . $lettreCol . $a . '/' . $lettreCol . $b, ['style' => 'HORIZONTAL_CENTER']);
                }
            }

            //suppression de la ligne modèle 18
            $this->excelWriter->removeRow(23);
        }

        //supprimer la feuille de modèle
        $this->excelWriter->removeSheetByIndex(0);
        $this->excelWriter->setActiveSheetIndex(0);
        $this->excelWriter->setSelectedCells('A1');

        $this->fileName = Tools::FileName('MCCC - ' . $anneeUniversitaire->getLibelle() . ' - ' . $formation->getTypeDiplome()?->getLibelleCourt() . ' ' . $formation->getSigle(). ' ' . $parcours->getSigle(), 40);
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

        $request = Gotenberg::libreOffice('http://localhost:3000')
            ->convert(Stream::path($fichier));

        $reponse = $this->client->sendRequest($request);

        // retourner une réponse avec le contenu du PDF
        return new Response($reponse->getBody()->getContents(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->fileName . '.pdf"',
        ]);
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

    private function writeMccc(mixed $fiche, array $tabColonnes, int $ligne): void
    {
        $mcccs = $fiche->getMcccs();
        foreach ($mcccs as $mccc) {
            if ($mccc->getLibelle() !== '' && array_key_exists($mccc->getLibelle(), $tabColonnes)) {
                $this->excelWriter->writeCellXY(
                    //convertir chiffre en lettre excel
                    Coordinate::columnIndexFromString($tabColonnes[$mccc->getLibelle()]['pourcentage']),
                    $ligne,
                    $mccc->getPourcentage() === 0.0 ? '' : $mccc->getPourcentage() . '%',
                    ['style' => 'HORIZONTAL_CENTER']
                );
                $this->excelWriter->writeCellXY(
                    Coordinate::columnIndexFromString($tabColonnes[$mccc->getLibelle()]['nombre']),
                    $ligne,
                    $mccc->getNbEpreuves() === 0 ? '' : $mccc->getNbEpreuves(),
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
                    $this->excelWriter->writeCellXY(
                        $tabColUes[$ueId],
                        $ligne,
                        $ects === 0.0 ? '' : $ects,
                        ['style' => 'HORIZONTAL_CENTER']
                    );
                }
            }
        }
    }
}
