<?php

namespace App\TypeDiplome;

use App\Entity\Parcours;


use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TypeDiplomeHandlerInterface extends TypeDiplomeMcccInterface, StructureInterface, McccInterface, DiplomeExportInterface
{
    public function getStructureCompetences(Parcours $parcours);

    public function getTypeEpreuves(): array;

    public function getTemplateFolder(): string;

    public function getValidator(): ValideParcoursInterface;

    /**
     * @return class-string<\Symfony\Component\Form\FormTypeInterface<mixed>>
     */
    public function getFormationFormType(): string;

    public function getFormationFormTemplate(): string;

    public function getFormationFormOptions(array $context): array;

    public function handleFormationSubmission(
        FormInterface $form,
        Request $request,
        array $context
    ): ?Response;
}
