<?php

declare(strict_types=1);

namespace App\Tests\Referentiel;

use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Parcours;
use App\Enums\ModaliteEnseignementEnum;
use App\Referentiel\ModaliteEnseignement;
use PHPUnit\Framework\TestCase;

final class ModaliteEnseignementTest extends TestCase
{
    public function testEveryLegacyKeyRemainsStable(): void
    {
        foreach (ModaliteEnseignementEnum::cases() as $case) {
            self::assertSame((string) $case->value, ModaliteEnseignement::key($case));
            self::assertSame($case, ModaliteEnseignement::legacyCase($case->value));
            self::assertSame($case, ModaliteEnseignement::legacyCase((string) $case->value));
        }
    }

    public function testCustomKeysAreNotCoercedToLegacyCases(): void
    {
        self::assertSame('hybride_flexible', ModaliteEnseignement::key('hybride_flexible'));
        self::assertNull(ModaliteEnseignement::legacyCase('hybride_flexible'));
        self::assertNull(ModaliteEnseignement::legacyCase('2extra'));
        self::assertNull(ModaliteEnseignement::legacyCase(null));
    }

    public function testParcoursLegacyAndCustomModaliteHandling(): void
    {
        $parcours = new Parcours();

        // 1. Setting legacy enum synchronizes both properties
        $parcours->setModalitesEnseignement(ModaliteEnseignementEnum::PRESENTIELLE);
        self::assertSame(ModaliteEnseignementEnum::PRESENTIELLE, $parcours->getModalitesEnseignement());
        self::assertSame('0', $parcours->getModalitesEnseignementValue());

        // 2. Setting canonical key for historical value synchronizes legacy enum
        $parcours->setModalitesEnseignementValue('1');
        self::assertSame(ModaliteEnseignementEnum::HYBRIDE, $parcours->getModalitesEnseignement());
        self::assertSame('1', $parcours->getModalitesEnseignementValue());

        // 3. Setting custom key leaves legacy getter null
        $parcours->setModalitesEnseignementValue('classe_virtuelle');
        self::assertSame('classe_virtuelle', $parcours->getModalitesEnseignementValue());
        self::assertNull($parcours->getModalitesEnseignement());

        // 4. Calling legacy setter with null does NOT wipe existing custom value
        $parcours->setModalitesEnseignement(null);
        self::assertSame('classe_virtuelle', $parcours->getModalitesEnseignementValue());

        // 5. Calling canonical setter with null clears the value
        $parcours->setModalitesEnseignementValue(null);
        self::assertNull($parcours->getModalitesEnseignementValue());
        self::assertNull($parcours->getModalitesEnseignement());
    }

    public function testFicheMatiereLegacyAndCustomModaliteHandling(): void
    {
        $ficheMatiere = new FicheMatiere();

        // 1. Default state
        $ficheMatiere->setModaliteEnseignement(ModaliteEnseignementEnum::PRESENTIELLE);
        self::assertSame(ModaliteEnseignementEnum::PRESENTIELLE, $ficheMatiere->getModaliteEnseignement());
        self::assertSame('0', $ficheMatiere->getModaliteEnseignementValue());

        // 2. Setting custom key leaves legacy getter null
        $ficheMatiere->setModaliteEnseignementValue('distanciel_synchrone');
        self::assertSame('distanciel_synchrone', $ficheMatiere->getModaliteEnseignementValue());
        self::assertNull($ficheMatiere->getModaliteEnseignement());

        // 3. Calling legacy setter with null does NOT wipe existing custom value
        $ficheMatiere->setModaliteEnseignement(null);
        self::assertSame('distanciel_synchrone', $ficheMatiere->getModaliteEnseignementValue());

        // 4. Calling canonical setter with null clears the value
        $ficheMatiere->setModaliteEnseignementValue(null);
        self::assertNull($ficheMatiere->getModaliteEnseignementValue());
        self::assertSame(ModaliteEnseignementEnum::NON_DEFINI, $ficheMatiere->getModaliteEnseignement());
    }

    public function testElementConstitutifLegacyAndCustomModaliteHandling(): void
    {
        $ec = new ElementConstitutif();

        // 1. Setting legacy enum
        $ec->setModaliteEnseignement(ModaliteEnseignementEnum::DISTANCIELLE);
        self::assertSame(ModaliteEnseignementEnum::DISTANCIELLE, $ec->getModaliteEnseignement());
        self::assertSame('2', $ec->getModaliteEnseignementValue());

        // 2. Setting custom key leaves legacy getter null
        $ec->setModaliteEnseignementValue('comodal');
        self::assertSame('comodal', $ec->getModaliteEnseignementValue());
        self::assertNull($ec->getModaliteEnseignement());

        // 3. Calling legacy setter with null does NOT wipe existing custom value
        $ec->setModaliteEnseignement(null);
        self::assertSame('comodal', $ec->getModaliteEnseignementValue());

        // 4. Calling canonical setter with null clears the value
        $ec->setModaliteEnseignementValue(null);
        self::assertNull($ec->getModaliteEnseignementValue());
        self::assertNull($ec->getModaliteEnseignement());
    }
}
