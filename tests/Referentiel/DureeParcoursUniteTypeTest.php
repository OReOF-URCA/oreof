<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Form\Type\DureeParcoursUniteType;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class DureeParcoursUniteTypeTest extends TypeTestCase
{
    private ReferentielManager $referentielManager;

    protected function setUp(): void
    {
        $this->referentielManager = $this->createMock(ReferentielManager::class);
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $type = new DureeParcoursUniteType($this->referentielManager);

        return [
            new PreloadedExtension([$type], []),
        ];
    }

    public function testActiveChoicesAreProvidedAndSubmitted(): void
    {
        $this->referentielManager->method('choices')
            ->with('duree_parcours_unite')
            ->willReturn([
                'heures' => 'Heure(s)',
                'jours' => 'Jour(s)',
                'semaines' => 'Semaine(s)',
                'mois' => 'Mois',
                'semestres' => 'Semestre(s)',
                'annees' => 'Année(s)',
                'trimestres' => 'Trimestre(s)',
            ]);

        $form = $this->factory->create(DureeParcoursUniteType::class);
        $form->submit('trimestres');

        self::assertTrue($form->isSynchronized());
        self::assertSame('trimestres', $form->getData());
    }

    public function testInactiveChoiceIsIncludedWhenAlreadySetOnData(): void
    {
        $this->referentielManager->method('choices')
            ->with('duree_parcours_unite')
            ->willReturn([
                'annees' => 'Année(s)',
            ]);

        $this->referentielManager->method('label')
            ->with('duree_parcours_unite', 'ancienne_unite')
            ->willReturn('Ancienne unité (désactivée)');

        $form = $this->factory->create(DureeParcoursUniteType::class, null, [
            'data' => 'ancienne_unite',
        ]);

        $choices = $form->getConfig()->getOption('choices');
        self::assertArrayHasKey('Ancienne unité (désactivée)', $choices);
        self::assertSame('ancienne_unite', $choices['Ancienne unité (désactivée)']);
    }
}
