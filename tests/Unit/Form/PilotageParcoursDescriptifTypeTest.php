<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Parcours;
use App\Form\Admin\PilotageParcoursDescriptifType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class PilotageParcoursDescriptifTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $parcours = new Parcours();
        $parcours->setLibelle('Parcours Informatique');

        $formData = [
            'descriptifHautPageAutomatique' => 'Parcours non ouvert pour l\'année 2027-2028',
            'descriptifHautPage' => 'Information spécifique de rentrée universitaire',
        ];

        $form = $this->factory->create(PilotageParcoursDescriptifType::class, $parcours);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('Parcours non ouvert pour l\'année 2027-2028', $parcours->getDescriptifHautPageAutomatique());
        $this->assertSame('Information spécifique de rentrée universitaire', $parcours->getDescriptifHautPage());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }
}
