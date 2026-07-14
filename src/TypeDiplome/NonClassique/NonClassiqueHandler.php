<?php

namespace App\TypeDiplome\NonClassique;

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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;


final class NonClassiqueHandler implements TypeDiplomeHandlerInterface
{
    public const TEMPLATE_FOLDER = 'non_classique';
    public const SOURCE = 'non_classique';
    public const TEMPLATE_FORM_MCCC = 'non_classique.html.twig';

    public function supports(string $type): bool
    {
        return false;
    }

    public function showStructure(
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): array
    {
        return [
            'parcours' => $parcours,
            'dureeParcours' => $parcours->getDureeParcours(),
            'dureeParcoursUnite' => $parcours->getDureeParcoursUnite(),
        ];
    }

    public function getStructureCompetences(Parcours $parcours): array
    {
        return [];
    }

    public function getTypeEpreuves(): array
    {
        return [];
    }

    public function getLibelleCourt(): string
    {
        return 'NON_CLASSIQUE';
    }

    public function getMcccs(ElementConstitutif|FicheMatiere $elementConstitutif): array|Collection
    {
        return [];
    }

    public function saveMcccs(ElementConstitutif|FicheMatiere $elementConstitutif, InputBag $request): void
    {
        // No MCCC structure for non-classique parcours
    }

    public function clearMcccs(ElementConstitutif|FicheMatiere $objet): void
    {
        // No MCCC structure for non-classique parcours
    }

    public function exportExcelMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function exportExcelVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): StreamedResponse
    {
        return new StreamedResponse();
    }

    public function exportExcelAndSaveVersionMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, string $dir, string $fichier, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null): string
    {
        return '';
    }

    public function exportPdfMccc(CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): Response
    {
        return new Response();
    }

    public function exportAndSaveExcelMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        return '';
    }

    public function exportAndSavePdfMccc(string $dir, CampagneCollecte $anneeUniversitaire, Parcours $parcours, ?DateTimeInterface $dateCfvu = null, ?DateTimeInterface $dateConseil = null, bool $versionFull = true): string
    {
        return '';
    }

    public function checkIfMcccValide(FicheMatiere|ElementConstitutif $owner): bool
    {
        return true;
    }

    public function calcul(
        Parcours $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureParcours
    {
        return $this->calculStructureParcours(
            $parcours,
            $optionsCalculStructure->withEcts,
            $optionsCalculStructure->withBcc
        );
    }

    public function calculStructureParcours(
        Parcours $parcours,
        bool     $withEcts = true,
        bool     $withBcc = true
    ): StructureParcours
    {
        $structure = new StructureParcours();
        // No semester structure — just store duration info for display
        $structure->dureeParcours = $parcours->getDureeParcours();
        $structure->dureeParcoursUnite = $parcours->getDureeParcoursUnite();
        return $structure;
    }

    public function calculVersioning(
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureParcours
    {
        return $this->calculStructureParcours(
            $parcours,
            $optionsCalculStructure->withEcts,
            $optionsCalculStructure->withBcc
        );
    }

    public function calculStructureSemestre(
        SemestreParcours       $semestreParcours,
        Parcours               $parcours,
        OptionsCalculStructure $optionsCalculStructure = new OptionsCalculStructure()
    ): StructureSemestre
    {
        return new StructureSemestre();
    }

    public function createFormMccc(ElementConstitutif|FicheMatiere $element): FormInterface
    {
        throw new \LogicException('No MCCC form for non-classique parcours.');
    }

    public function getTemplateFolder(): string
    {
        return self::TEMPLATE_FOLDER;
    }

    public function getValidator(): ValideParcoursInterface
    {
        throw new \LogicException('No validator available for non-classique parcours.');
    }

}
