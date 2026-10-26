<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Form\Type\NiveauFormationType;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class NiveauFormationTypeTest extends TypeTestCase
{
    private ReferentielManager $referentielManager;

    protected function setUp(): void
    {
        $this->referentielManager = $this->createMock(ReferentielManager::class);
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $type = new NiveauFormationType($this->referentielManager);

        return [
            new PreloadedExtension([$type], []),
        ];
    }

    public function testActiveChoicesAreProvidedAndSubmitted(): void
    {
        $this->referentielManager->method('choices')
            ->with('niveau_formation')
            ->willReturn([
                '16' => 'Niveau 6 (bac +3)',
                '17' => 'Niveau 7 (bac +5)',
                'custom_niv' => 'Niveau personnalisé',
            ]);

        $form = $this->factory->create(NiveauFormationType::class);
        $form->submit('custom_niv');

        self::assertTrue($form->isSynchronized());
        self::assertSame('custom_niv', $form->getData());
    }

    public function testInactiveChoiceIsIncludedWhenAlreadySetOnData(): void
    {
        $this->referentielManager->method('choices')
            ->with('niveau_formation')
            ->willReturn([
                '16' => 'Niveau 6 (bac +3)',
            ]);

        $this->referentielManager->method('label')
            ->with('niveau_formation', 'ancien_niveau')
            ->willReturn('Ancien niveau (désactivé)');

        $form = $this->factory->create(NiveauFormationType::class, null, [
            'data' => 'ancien_niveau',
        ]);

        $choices = $form->getConfig()->getOption('choices');
        self::assertArrayHasKey('Ancien niveau (désactivé)', $choices);
        self::assertSame('ancien_niveau', $choices['Ancien niveau (désactivé)']);
    }
}
