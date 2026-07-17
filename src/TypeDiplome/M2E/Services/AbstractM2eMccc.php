<?php
/*
 * Copyright (c) 2024. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Export/AbstractM2EMcc.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 19/05/2024 20:35
 */

namespace App\TypeDiplome\M2E\Services;

use App\Classes\Excel\ExcelWriter;
use App\DTO\StructureEc;
use App\Entity\Mccc;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AbstractM2eMccc
{

    public const FICHIER_MCCC_XSLX = 'Annexe_MCCC_M2E.xlsx';

    // Pages
    public const PAGE_MODELE = 'modele';
    public const PAGE_REF_COMPETENCES = 'ref. compétences';
    // Cellules
    public const CEL_TYPE_FORMATION = 'J5';
    public const CEL_INTITULE_FORMATION = 'J6';
    public const CEL_INTITULE_PARCOURS = 'J7';
    public const CEL_ANNEE_ETUDE = 'J9';
    public const CEL_COMPOSANTE = 'J11';
    public const CEL_SITE_FORMATION = 'J13';
    public const CEL_ANNEE_UNIVERSITAIRE = 'A3';
    public const CEL_RESPONSABLE_MENTION = 'E25';
    public const CEL_RESPONSABLE_PARCOURS = 'E26';
    public const CEL_REGIME_FI = 'C7';
    public const CEL_REGIME_FC = 'C9';
    public const CEL_REGIME_FI_APPRENTISSAGE = 'C11';
    public const CEL_REGIME_FC_CONTRAT_PRO = 'C13';

    public const CEL_DATE_CFVU = 'Y26';
    public const CEL_DATE_CONSEIL = 'Y25';

    //Colonnes sur Modèles

    public const COL_UE = 1;
    public const COL_INTITULE_UE = 2;
    public const COL_NUM_EC = 3;
    public const COL_INTITULE_EC = 4;
    public const COL_INTITULE_EC_EN = 5;
    public const COL_RESP_EC = 6;
    public const COL_LANGUE_EC = 8;
    public const COL_SUPPORT_ANGLAIS = 9;
    public const COL_TYPE_EC = 7;
    public const COL_ECTS = 10;
    public const COL_HEURES_PRES_CM = 11;
    public const COL_HEURES_PRES_TD = 12;
    public const COL_HEURES_PRES_TP = 13;
    public const COL_HEURES_PRES_TOTAL = 14;

    public const COL_HEURES_DIST_CM = 15;
    public const COL_HEURES_DIST_TD = 16;
    public const COL_HEURES_DIST_TP = 17;
    public const COL_HEURES_DIST_TOTAL = 18;
    public const COL_HEURES_AUTONOMIE = 20;
    public const COL_HEURES_TOTAL = 19;
    public const COL_MCCC_CCI = 21;
    public const COL_MCCC_CC = 22;
    public const COL_MCCC_CT = 23;
    public const COL_MCCC_SECONDE_CHANCE_CC_SANS_TP = 24;
    public const COL_MCCC_SECONDE_CHANCE_CC_AVEC_TP = 25;
    public const COL_MCCC_SECONDE_CHANCE_CC_SUP_10 = 26;
    public const COL_MCCC_SECONDE_CHANCE_CT = 27;
    public const COL_FIRST_UE = 28;

    public const COL_DETAIL_TYPE_EPREUVES = "A28";

    protected array $typeEpreuves = [];
    protected bool $versionFull = true;
    protected string $fileName;
    protected array $lignesSemestre = [];
    protected array $lignesEcColorees = [];

    protected string $dir;


    public function __construct(
        protected ExcelWriter $excelWriter,
    )
    {
    }

    public function getMcccs(StructureEc|array $structureEc, string $typeMccc): array
    {
        if ($this->typeEpreuves === []) {
            $this->getTypeEpreuves();
        }

        if (is_array($structureEc)) {
            $mcccs = $structureEc;
        } else {
            $mcccs = $structureEc->mcccs;
        }

        $tabMcccs = [];

        if ($typeMccc === 'cci') {
            foreach ($mcccs as $mccc) {
                $tabMcccs[$mccc->getNumeroSession()] = $mccc;
            }
        } else {
            foreach ($mcccs as $mccc) {
                if ($mccc->isSecondeChance()) {
                    $tabMcccs[3]['chance'] = $mccc;
                } elseif ($mccc->isControleContinu() === true && $mccc->isExamenTerminal() === false) {
                    $tabMcccs[$mccc->getNumeroSession()]['cc'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                } elseif ($mccc->isControleContinu() === false && $mccc->isExamenTerminal() === true) {
                    $tabMcccs[$mccc->getNumeroSession()]['et'][$mccc->getNumeroEpreuve() ?? 1] = $mccc;
                }
            }
        }

        return $tabMcccs;
    }

    protected function getTypeEpreuves(): void
    {
        $epreuves = $this->typeEpreuveRepository->findAll();
        foreach ($epreuves as $epreuve) {
            $this->typeEpreuves[$epreuve->getId()] = $epreuve;
        }
    }

    protected function updateIfNotFull(): void
    {
        if ($this->versionFull === false) {
            //décalage des données de formation
            $this->excelWriter->unMergeCells('G5:I5');
            $this->excelWriter->unMergeCells('G6:I6');
            $this->excelWriter->unMergeCells('G7:I7');
            $this->excelWriter->unMergeCells('G9:I9');
            $this->excelWriter->unMergeCells('G11:I11');
            $this->excelWriter->unMergeCells('G13:I13');

            $this->excelWriter->unMergeCells('J5:L5');
            $this->excelWriter->unMergeCells('J6:V6');
            $this->excelWriter->unMergeCells('J7:V7');
            $this->excelWriter->unMergeCells('J9:L9');
            $this->excelWriter->unMergeCells('J11:L11');
            $this->excelWriter->unMergeCells('J13:L13');

            //  $this->excelWriter->copyFromCellToCell('G5', 'M5');
            $this->excelWriter->copyFromCellToCell('J5', 'S5');
            //  $this->excelWriter->copyFromCellToCell('G6', 'M6');
            $this->excelWriter->copyFromCellToCell('J6', 'S6');
            //  $this->excelWriter->copyFromCellToCell('G7', 'M7');
            $this->excelWriter->copyFromCellToCell('J7', 'S7');
            //  $this->excelWriter->copyFromCellToCell('G9', 'M9');
            $this->excelWriter->copyFromCellToCell('J9', 'S9');
            // $this->excelWriter->copyFromCellToCell('G11', 'M11');
            $this->excelWriter->copyFromCellToCell('J11', 'S11');
            //  $this->excelWriter->copyFromCellToCell('G13', 'M13');
            $this->excelWriter->copyFromCellToCell('J13', 'S13');

            $this->excelWriter->unMergeCells('D15:M16');
            foreach ($this->lignesSemestre as $ligneSemestre) {
                $ligneSemestre--;
                $this->excelWriter->unMergeCells('B' . $ligneSemestre . ':L' . $ligneSemestre);
            }
            //suppression des colonnes
            $this->excelWriter->removeColumn('F', 5);
            $this->excelWriter->mergeCells('D15:H16');
            $this->excelWriter->mergeCells('G5:L5');
            $this->excelWriter->mergeCells('G6:L6');
            $this->excelWriter->mergeCells('G7:L7');
            $this->excelWriter->mergeCells('G9:L9');
            $this->excelWriter->mergeCells('G11:L11');
            $this->excelWriter->mergeCells('G13:L13');

            $this->excelWriter->writeCellName('G5', 'Type de formation :');
            $this->excelWriter->writeCellName('G6', 'Intitulé de la mention (Spécialité pour les BUT) :');
            $this->excelWriter->writeCellName('G7', 'Intitulé du parcours (si existant) :');
            $this->excelWriter->writeCellName('G9', 'Année d\'études :');
            $this->excelWriter->writeCellName('G11', 'Composante :');
            $this->excelWriter->writeCellName('G13', 'Site de formation :');

            $style = ['alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ]];

            $this->excelWriter->cellStyle('G5', $style);
            $this->excelWriter->cellStyle('G6', $style);
            $this->excelWriter->cellStyle('G7', $style);
            $this->excelWriter->cellStyle('G9', $style);
            $this->excelWriter->cellStyle('G11', $style);
            $this->excelWriter->cellStyle('G13', $style);

            $this->excelWriter->mergeCells('N5:S5');
            $this->excelWriter->mergeCells('N6:Y6');
            $this->excelWriter->mergeCells('N7:Y7');
            $this->excelWriter->mergeCells('N9:S9');
            $this->excelWriter->mergeCells('N11:S11');
            $this->excelWriter->mergeCells('N13:S13');

            foreach ($this->lignesSemestre as $ligneSemestre) {
                $ligneSemestre--;
                $this->excelWriter->mergeCells('B' . $ligneSemestre . ':G' . $ligneSemestre);
            }
        }
    }
}
