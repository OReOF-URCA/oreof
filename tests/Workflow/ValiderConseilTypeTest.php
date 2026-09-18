<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Form\Workflow\ValiderConseilType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

final class ValiderConseilTypeTest extends KernelTestCase
{
    public function testLaissezPasserAllowsSubmissionWithoutCouncilReport(): void
    {
        self::bootKernel();

        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        self::assertInstanceOf(FormFactoryInterface::class, $formFactory);

        $form = $formFactory->create(ValiderConseilType::class);
        $form->submit([
            'dateConseil' => '2026-09-18',
            'uploadPv' => null,
            'uploadArgumentaire' => null,
            'laissezPasser' => '1',
        ]);

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true, false));
        self::assertTrue($form->getData()->laissezPasser);
    }

    public function testCouncilReportOrLaissezPasserIsRequired(): void
    {
        self::bootKernel();

        /** @var FormFactoryInterface $formFactory */
        $formFactory = self::getContainer()->get(FormFactoryInterface::class);
        $form = $formFactory->create(ValiderConseilType::class);
        $form->submit([
            'dateConseil' => '2026-09-18',
            'uploadPv' => null,
            'uploadArgumentaire' => null,
            'laissezPasser' => false,
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Déposez le PV du conseil ou indiquez un laissez-passer.',
            (string) $form->getErrors(true, false),
        );
    }
}
