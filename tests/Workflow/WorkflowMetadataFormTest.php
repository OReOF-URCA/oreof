<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\DTO\Workflow\FieldMetaDto;
use App\DTO\Workflow\ModalFormMetaDto;
use App\Form\Workflow\ValiderConseilType;
use App\Workflow\Form\MetaDrivenFormFactory;
use App\Workflow\Form\MetaFormOptionsFilter;
use App\Workflow\Form\MetaFormTypeResolver;
use App\Workflow\Metadata\WorkflowMetaMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

final class WorkflowMetadataFormTest extends TestCase
{
    public function testValidationViewAndStepAreMapped(): void
    {
        $meta = (new WorkflowMetaMapper())->fromArray([
            'validation' => [
                'step' => 'soumis_conseil',
                'view' => 'parcours_v2/process/_validation_apply.html.twig',
            ],
            'form' => [
                'type' => ValiderConseilType::class,
                'rules' => [
                    [
                        'type' => 'at_least_one',
                        'fields' => ['uploadPv', 'laissezPasser'],
                        'message' => 'PV ou laissez-passer requis.',
                    ],
                ],
                'fields' => [],
            ],
        ]);

        self::assertSame('soumis_conseil', $meta->validationStep);
        self::assertSame('parcours_v2/process/_validation_apply.html.twig', $meta->viewTemplate);
        self::assertSame(ValiderConseilType::class, $meta->form?->formType);
        self::assertSame('at_least_one', $meta->form?->rules[0]['type']);
    }

    public function testAtLeastOneRuleIsAppliedToGeneratedForm(): void
    {
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        $factory = new MetaDrivenFormFactory(
            $formFactory,
            new MetaFormTypeResolver(),
            new MetaFormOptionsFilter(),
        );

        $meta = new ModalFormMetaDto(
            title: 'Conseil',
            submitLabel: 'Valider',
            formId: 'modal_form',
            fields: [
                new FieldMetaDto('pvReference', 'text', false, null, null, []),
                new FieldMetaDto('laissezPasser', 'checkbox', false, null, null, []),
            ],
            rules: [[
                'type' => 'at_least_one',
                'fields' => ['pvReference', 'laissezPasser'],
                'message' => 'PV ou laissez-passer requis.',
            ]],
        );

        $invalidForm = $factory->create($meta, 'valider_conseil');
        $invalidForm->submit(['pvReference' => '', 'laissezPasser' => false]);
        self::assertFalse($invalidForm->isValid());

        $validForm = $factory->create($meta, 'valider_conseil');
        $validForm->submit(['pvReference' => '', 'laissezPasser' => true]);
        self::assertTrue($validForm->isValid());
    }
}
