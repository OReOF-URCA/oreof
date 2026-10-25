<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Entity\Referentiel;
use App\Entity\ReferentielValeur;
use App\Form\Type\ModaliteEnseignementType;
use App\Referentiel\ReferentielManager;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class ModaliteEnseignementTypeTest extends TypeTestCase
{
    private ReferentielManager $referentielManager;

    protected function setUp(): void
    {
        $this->referentielManager = $this->createMock(ReferentielManager::class);
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $type = new ModaliteEnseignementType($this->referentielManager);

        return [
            new PreloadedExtension([$type], []),
        ];
    }

    public function testActiveChoicesAreProvidedAndSubmitted(): void
    {
        $this->referentielManager->method('choices')
            ->with('modalite_enseignement')
            ->willReturn([
                '0' => 'Présentielle',
                '1' => 'Hybride',
                '2' => 'Distancielle',
                'classe_virtuelle' => 'Classe virtuelle',
            ]);

        $form = $this->factory->create(ModaliteEnseignementType::class);
        $form->submit('classe_virtuelle');

        self::assertTrue($form->isSynchronized());
        self::assertSame('classe_virtuelle', $form->getData());
    }

    public function testInactiveChoiceIsIncludedWhenAlreadySetOnData(): void
    {
        $this->referentielManager->method('choices')
            ->with('modalite_enseignement')
            ->willReturn([
                '0' => 'Présentielle',
            ]);

        $this->referentielManager->method('label')
            ->with('modalite_enseignement', 'ancienne_modalite')
            ->willReturn('Ancienne modalité (désactivée)');

        $form = $this->factory->create(ModaliteEnseignementType::class, null, [
            'data' => 'ancienne_modalite',
        ]);

        $choices = $form->getConfig()->getOption('choices');
        self::assertArrayHasKey('Ancienne modalité (désactivée)', $choices);
        self::assertSame('ancienne_modalite', $choices['Ancienne modalité (désactivée)']);
    }
}
