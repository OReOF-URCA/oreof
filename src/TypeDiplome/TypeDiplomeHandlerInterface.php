<?php

namespace App\TypeDiplome;

use App\DTO\StructureParcours;
use App\Entity\Parcours;


use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TypeDiplomeHandlerInterface extends TypeDiplomeMcccInterface, StructureInterface, McccInterface, DiplomeExportInterface
{
    public const TEMPLATE_FOLDER = '';
    public const TEMPLATE_FORM_MCCC = '';

    public function calculStructureParcours(Parcours $parcours): StructureParcours;

    public function getStructureCompetences(Parcours $parcours): array;

    public function getDisplayMccc(array $mcccs, string $typeMccc = ''): array;

    public function getTypeEpreuves(): array;

    public function getTemplateFolder(): string;

    public function getValidator(): ValideParcoursInterface;

    public function getFormationFormType(): string;

    public function getFormationFormTemplate(): string;

    public function getFormationFormOptions(array $context): array;

    public function handleFormationSubmission(
        FormInterface $form,
        Request $request,
        array $context
    ): ?Response;
}
