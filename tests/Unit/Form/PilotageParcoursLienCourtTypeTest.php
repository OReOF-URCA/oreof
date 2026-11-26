<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Parcours;
use App\Form\Admin\PilotageParcoursLienCourtType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class PilotageParcoursLienCourtTypeTest extends TypeTestCase
{
    public function testSubmitValidLienCourt(): void
    {
        $parcours = new Parcours();
        $formData = [
            'lienCourt' => 'https://monmaster.gouv.fr/formation/987',
        ];

        $form = $this->factory->create(PilotageParcoursLienCourtType::class, $parcours);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('https://monmaster.gouv.fr/formation/987', $parcours->getLienCourt());
    }

    public function testSubmitEmptyLienCourt(): void
    {
        $parcours = new Parcours();
        $parcours->setLienCourt('https://old-link.fr');
        $formData = [
            'lienCourt' => '',
        ];

        $form = $this->factory->create(PilotageParcoursLienCourtType::class, $parcours);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertNull($parcours->getLienCourt());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }
}
