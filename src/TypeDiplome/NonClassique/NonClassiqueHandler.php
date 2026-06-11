<?php

namespace App\TypeDiplome\NonClassique;

use App\DTO\StructureParcours;
use App\Entity\CampagneCollecte;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\StructureInterface;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
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

    public function calculStructureParcours(
        Parcours $parcours,
        bool $withEcts = true,
        bool $withBcc = true
    ): StructureParcours {
        $structure = new StructureParcours();
        // No semester structure — just store duration info for display
        $structure->dureeParcours = $parcours->getDureeParcours();
        $structure->dureeParcoursUnite = $parcours->getDureeParcoursUnite();
        return $structure;
    }

    public function showStructure(
        Parcours $parcours,
        \App\TypeDiplome\Dto\OptionsCalculStructure $optionsCalculStructure = new \App\TypeDiplome\Dto\OptionsCalculStructure()
    ): array {
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
        bool $withEcts = true,
        bool $withBcc = true,
        bool $dataFromFicheMatiere = false
    ): StructureParcours {
        return $this->calculStructureParcours($parcours, $withEcts, $withBcc);
    }

    public function calculVersioning(Parcours $parcours): StructureParcours
    {
        return $this->calculStructureParcours($parcours);
    }

}