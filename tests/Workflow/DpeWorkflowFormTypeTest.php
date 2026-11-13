<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\CampagneCollecte;
use App\Entity\TimelineDate;
use App\Enums\TimelineDateFlagEnum;
use App\Form\ChangeRfValidationType;
use App\Form\Type\CfvuDateType;
use App\Form\Workflow\ArgumentaireDateType;
use App\Form\Workflow\ArgumentaireType;
use App\Form\Workflow\WorkflowDateType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
        self::assertSame(
            DateType::class,
            $form->get('datePublication')->getConfig()->getType()->getInnerType()::class,
        );
    }

    public function testCfvuDateTypeIsUsedForCfvuWorkflowFields(): void
    {
        $wfDateForm = $this->formFactory()->create(WorkflowDateType::class, null, [
            'date_property' => 'dateConseil',
            'date_label' => 'Date de la CFVU',
        ]);
        self::assertSame(
            CfvuDateType::class,
            $wfDateForm->get('dateConseil')->getConfig()->getType()->getInnerType()::class,
        );

        $argDateForm = $this->formFactory()->create(ArgumentaireDateType::class, null, [
            'date_property' => 'dateCfvu',
        ]);
        self::assertSame(
            CfvuDateType::class,
            $argDateForm->get('dateCfvu')->getConfig()->getType()->getInnerType()::class,
        );

        $changeRfCfvuForm = $this->formFactory()->create(ChangeRfValidationType::class, null, [
            'meta' => ['hasDate' => true],
            'transition' => 'valider_cfvu_avec_pv',
        ]);
        self::assertSame(
            CfvuDateType::class,
            $changeRfCfvuForm->get('date')->getConfig()->getType()->getInnerType()::class,
        );

        $changeRfConseilForm = $this->formFactory()->create(ChangeRfValidationType::class, null, [
            'meta' => ['hasDate' => true],
            'transition' => 'valider_conseil',
        ]);
        self::assertSame(
            DateType::class,
            $changeRfConseilForm->get('date')->getConfig()->getType()->getInnerType()::class,
        );
    }

    public function testCampagneCollecteReturnsAllCfvuTimelineDates(): void
    {
        $campagne = new CampagneCollecte();

        $cfvu1 = (new TimelineDate())
            ->setLibelle('CFVU 1')
            ->setDate(new \DateTime('2026-05-15'))
            ->setFlag(TimelineDateFlagEnum::CFVU);
        $other = (new TimelineDate())
            ->setLibelle('Transmission SES')
            ->setDate(new \DateTime('2026-04-10'))
            ->setFlag(TimelineDateFlagEnum::TRANSMISSION_SES);
        $cfvu2 = (new TimelineDate())
            ->setLibelle('CFVU 2')
            ->setDate(new \DateTime('2026-06-20'))
            ->setFlag(TimelineDateFlagEnum::CFVU);

        $campagne->addTimelineDate($cfvu1);
        $campagne->addTimelineDate($other);
        $campagne->addTimelineDate($cfvu2);

        self::assertSame([$cfvu1, $cfvu2], $campagne->getCfvuTimelineDates());
    }

    private function formFactory(): \Symfony\Component\Form\FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();
    }
}
