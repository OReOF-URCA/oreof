<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/DiplomeExportInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 29/05/2025 11:41
 */

namespace App\TypeDiplome;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface DiplomeExportInterface
{
    public function exportExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse;

    public function exportExcelVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): StreamedResponse;

    public function exportExcelAndSaveVersionMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        string             $fichier,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null
    ): string;

    public function exportPdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): Response;

    public function exportAndSaveExcelMccc(
        string             $dir,
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string;

    public function exportAndSavePdfMccc(
        string             $dir,
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string;
}
