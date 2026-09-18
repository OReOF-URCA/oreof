<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\Workflow\ArgumentaireDateType;
use App\Form\Workflow\ArgumentaireType;
use App\Form\Workflow\WorkflowDateType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class DpeWorkflowFormTypeTest extends TestCase
{
    public function testRequiredArgumentaireIsValidatedByTheFormType(): void
    {
        $form = $this->formFactory()->create(ArgumentaireType::class);
        $form->submit(['argumentaire' => '']);

        self::assertFalse($form->isValid());
    }

    public function testOptionalArgumentaireCanBeEmpty(): void
    {
        $form = $this->formFactory()->create(ArgumentaireType::class, null, [
            'argumentaire_required' => false,
        ]);
        $form->submit(['argumentaire' => '']);

        self::assertTrue($form->isValid());
    }

    public function testArgumentaireAndConfiguredDateAreRequired(): void
    {
        $form = $this->formFactory()->create(ArgumentaireDateType::class, null, [
            'date_property' => 'dateCfvu',
        ]);
        $form->submit(['argumentaire' => 'Réserve', 'dateCfvu' => '']);

        self::assertFalse($form->isValid());

        $form = $this->formFactory()->create(ArgumentaireDateType::class, null, [
            'date_property' => 'dateCfvu',
        ]);
        $form->submit(['argumentaire' => 'Réserve', 'dateCfvu' => '2026-09-18']);

        self::assertTrue($form->isValid());
    }

    public function testConfiguredWorkflowDateIsRequired(): void
    {
        $form = $this->formFactory()->create(WorkflowDateType::class, null, [
            'date_property' => 'datePublication',
        ]);
        $form->submit(['datePublication' => '']);

        self::assertFalse($form->isValid());
    }

    private function formFactory(): \Symfony\Component\Form\FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }
}
