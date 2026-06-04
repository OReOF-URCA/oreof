<?php
/*
 * Copyright (c) 2025. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Daeu/DaeuHandler.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 28/05/2025 15:26
 */

namespace App\TypeDiplome\Cmi;

use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CmiHandler implements TypeDiplomeHandlerInterface
{

    public const TEMPLATE_FOLDER = 'licence'; //todo: a remplacer
    public const SOURCE = 'licence'; //todo: a remplacer
    public const TEMPLATE_FORM_MCCC = 'licence.html.twig'; //todo: a remplacer

    public function supports(string $type): bool
    {
        return $type === 'CMI';
    }

    public function calculStructureParcours(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): \App\DTO\StructureParcours
    {
        return new \App\DTO\StructureParcours();
    }

    public function showStructure(Parcours $parcours): array
    {
        // TODO: Implement showStructure() method.
        return [];
    }

    public function getStructureCompetences(Parcours $parcours)
    {
        // TODO: Implement getStructureCompetences() method.
    }

    public function exportExcelMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        // TODO: Implement exportExcelMccc() method.
        return new StreamedResponse();
    }

    public function exportExcelVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        // TODO: Implement exportExcelVersionMccc() method.
        return new StreamedResponse();
    }

    public function exportExcelAndSaveVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, string $dir, string $fichier, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null): string
    {
        // TODO: Implement exportExcelAndSaveVersionMccc() method.
        return '';
    }

    public function exportPdfMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): Response
    {
        // TODO: Implement exportPdfMccc() method.
        return new Response();
    }

    public function exportAndSaveExcelMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        // TODO: Implement exportAndSaveExcelMccc() method.
        return '';
    }

    public function exportAndSavePdfMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        // TODO: Implement exportAndSavePdfMccc() method.
        return '';
    }

    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void
    {
        // TODO: Implement clearMcccs() method.
    }

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection
    {
        // TODO: Implement getMcccs() method.
        return [];
    }

    public function saveMcccs(ElementConstitutif|FicheMatiere $elementConstitutif, InputBag $request): void
    {
        // TODO: Implement saveMcccs() method.
    }

    public function getTypeEpreuves(): array
    {
        // TODO: Implement getTypeEpreuves() method.
        return [];
    }

    public function getLibelleCourt(): string
    {
        return 'CMI';
    }
}
