<?php

declare(strict_types=1);

namespace App\Tests\Unit\Classes;

use App\Classes\ValidationProcess;
use App\Entity\DpeParcours;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ValidationProcessTest extends KernelTestCase
{
    public function testAllTransitionsHaveValidTypeAndMetadata(): void
    {
        self::bootKernel();
        $validationProcess = static::getContainer()->get(ValidationProcess::class);

        $dpeParcoursWorkflow = static::getContainer()->get('workflow.dpeParcours');
        $transitions = $dpeParcoursWorkflow->getDefinition()->getTransitions();

        foreach ($transitions as $transition) {
            $meta = $validationProcess->getMetaFromTransition($transition->getName());
            $this->assertArrayHasKey('type', $meta, sprintf('La transition "%s" doit avoir une clé "type"', $transition->getName()));
            $this->assertNotEmpty($meta['type'], sprintf('Le type de la transition "%s" ne doit pas être vide', $transition->getName()));
            $this->assertIsString($meta['type']);
        }
    }

    public function testOptionsForInitialisationStepContainValidType(): void
    {
        self::bootKernel();
        $validationProcess = static::getContainer()->get(ValidationProcess::class);

        $dpe = new DpeParcours();
        $dpe->setEtatValidation(['initialisation_dpe' => 1]);

        $options = $validationProcess->getOptionsForStep($dpe);

        $this->assertArrayHasKey('initialiser', $options);
        $this->assertArrayHasKey('metadata', $options['initialiser']);
        $this->assertNotEmpty($options['initialiser']['metadata']['type']);
        $this->assertSame('valider', $options['initialiser']['metadata']['type']);
    }
}
