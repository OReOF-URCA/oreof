<?php
/*
 * Copyright (c) 2026. | David Annebicque | ORéOF  - All Rights Reserved
 * @file /Users/davidannebicque/Sites/oreof/src/TypeDiplome/Handler/DuHandler.php
 * @author davidannebicque
 * @project oreof
 * @lastUpdate 08/12/2025 20:01
 */

namespace App\TypeDiplome\Handler;

use App\DTO\StructureParcours;
use App\DTO\StructureSemestre;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\TypeDiplome\Dto\OptionsCalculStructure;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\ValideParcoursInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[AutoconfigureTag('app.type_diplome_handler', ['code' => 'DU'])]
final class DuHandler implements TypeDiplomeHandlerInterface
{

    public const TEMPLATE_FOLDER = 'licence'; //todo: a remplacer
    public const SOURCE = 'licence'; //todo: a remplacer
    public const TEMPLATE_FORM_MCCC = 'licence.html.twig'; //todo: a remplacer

    public function createFormMccc(ElementConstitutif|FicheMatiere $element): FormInterface
    {

    }

    public function calculStructureParcours(Parcours $parcours, bool $withEcts = true, bool $withBcc = true): StructureParcours
    {
        return StructureParcours::fromEntity($parcours, $withEcts, $withBcc);;
    }

    public function showStructure(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): array
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
        return 'DU';
    }

    public function getTemplateFolder(): string
    {
        return self::TEMPLATE_FOLDER;
    }

    public function calcul(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        // TODO: Implement calcul() method.
    }

    public function calculStructureSemestre(SemestreParcours $semestreParcours, Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureSemestre
    {
        // TODO: Implement calculStructureSemestre() method.
    }

    public function calculVersioning(Parcours $parcours, OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()): StructureParcours
    {
        // TODO: Implement calculVersioning() method.
    }

    public function checkIfMcccValide(ElementConstitutif|FicheMatiere $owner): bool
    {
        // TODO: Implement checkIfMcccValide() method.
    }

    public function getValidator(): ValideParcoursInterface
    {
        // TODO: Implement getValidator() method.
    }
}
