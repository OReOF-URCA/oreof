<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Form\Type\NiveauLangueType;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class NiveauLangueTypeTest extends TypeTestCase
{
    private ReferentielManager $referentielManager;

    protected function setUp(): void
    {
        $this->referentielManager = $this->createMock(ReferentielManager::class);
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $type = new NiveauLangueType($this->referentielManager);

        return [
            new PreloadedExtension([$type], []),
        ];
    }

    public function testActiveChoicesAreProvidedAndSubmitted(): void
    {
        $this->referentielManager->method('choices')
            ->with('niveau_langue')
            ->willReturn([
                'A1' => 'A1',
                'A2' => 'A2',
                'B1' => 'B1',
                'B2' => 'B2',
                'C1' => 'C1',
                'C2' => 'C2',
                'B2+' => 'B2 avancé',
            ]);

        $form = $this->factory->create(NiveauLangueType::class);
        $form->submit('B2+');

        self::assertTrue($form->isSynchronized());
        self::assertSame('B2+', $form->getData());
    }

    public function testInactiveChoiceIsIncludedWhenAlreadySetOnData(): void
    {
        $this->referentielManager->method('choices')
            ->with('niveau_langue')
            ->willReturn([
                'B2' => 'B2',
            ]);

        $this->referentielManager->method('label')
            ->with('niveau_langue', 'ancienne_langue')
            ->willReturn('Ancien niveau (désactivé)');

        $form = $this->factory->create(NiveauLangueType::class, null, [
            'data' => 'ancienne_langue',
        ]);

        $choices = $form->getConfig()->getOption('choices');
        self::assertArrayHasKey('Ancien niveau (désactivé)', $choices);
        self::assertSame('ancienne_langue', $choices['Ancien niveau (désactivé)']);
    }
}
