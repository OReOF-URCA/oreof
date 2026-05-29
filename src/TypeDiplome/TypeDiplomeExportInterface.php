<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/TypeDiplomeExportInterface.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 10/03/2026 15:56
 */

namespace App\TypeDiplome;

use App\Entity\CampagneCollecte;
use App\Entity\Parcours;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface TypeDiplomeExportInterface
{
    public function exportExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): StreamedResponse|false;

    public function genereExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): bool;

    public function exportPdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): Response;

    public function exportAndSaveExcelMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        string             $fichier,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true
    ): string|false;

    public function exportAndSavePdfMccc(
        CampagneCollecte   $anneeUniversitaire,
        Parcours           $parcours,
        string             $dir,
        ?DateTimeInterface $dateCfvu = null,
        ?DateTimeInterface $dateConseil = null,
        bool               $versionFull = true,
    ): string;
}
