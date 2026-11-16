<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\Parcours;
use App\Form\ParcoursStep7Type;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

class ParcoursStep7TypeTest extends TypeTestCase
{
    public function testSubmitLienCourt(): void
    {
        $parcours = new Parcours();
        $formData = [
            'codeRNCP' => 'RNCP12345',
            'lienCourt' => 'https://parcoursup.fr/formation/test',
        ];

        $form = $this->factory->create(ParcoursStep7Type::class, $parcours);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isValid());
        $this->assertSame('RNCP12345', $parcours->getCodeRNCP());
        $this->assertSame('https://parcoursup.fr/formation/test', $parcours->getLienCourt());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidator();

        return [
            new ValidatorExtension($validator),
        ];
    }
}
