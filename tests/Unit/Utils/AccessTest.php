<?php

declare(strict_types=1);

namespace App\Tests\Unit\Utils;

use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\TypeModificationDpeEnum;
use App\Utils\Access;
use PHPUnit\Framework\TestCase;

class AccessTest extends TestCase
{
    public function testDpeParcoursInTaciteReconductionIsOuvertButNotYetEditable(): void
    {
        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatValidation(['tacite_reconduction' => 1]);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $this->assertTrue(Access::isOuvert($dpeParcours));
        $this->assertFalse(Access::isEditable($dpeParcours));
    }

    public function testEnCoursRedactionSsCfvuLocksCfvuTabsButOpensTextTabs(): void
    {
        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatValidation(['en_cours_redaction_ss_cfvu' => 1]);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $this->assertTrue(Access::isEditable($dpeParcours));

        // CFVU should be false in en_cours_redaction_ss_cfvu
        $this->assertFalse(Access::isAccessible($dpeParcours, 'cfvu'));
        // Texte should be true
        $this->assertTrue(Access::isAccessible($dpeParcours, 'texte'));

        // Tab editability
        $this->assertTrue(Access::isTabEditable($dpeParcours, 'descriptif'));
        $this->assertTrue(Access::isTabEditable($dpeParcours, 'presentation'));
        $this->assertTrue(Access::isTabEditable($dpeParcours, 'admission'));
        $this->assertTrue(Access::isTabEditable($dpeParcours, 'et_apres'));

        // Maquette / CFVU tabs should NOT be editable
        $this->assertFalse(Access::isTabEditable($dpeParcours, 'maquette'));
        $this->assertFalse(Access::isTabEditable($dpeParcours, 'semestre'));
        $this->assertFalse(Access::isTabEditable($dpeParcours, 'annee'));
        $this->assertFalse(Access::isTabEditable($dpeParcours, 'competences'));
    }

    public function testFormationWithNullEtatReconductionIsOuvert(): void
    {
        $formation = new Formation(null);
        $formation->setEtatReconduction(null);

        $this->assertTrue(Access::isOuvert($formation));
    }

    public function testFormationWithClosedEtatReconductionIsNotOuvert(): void
    {
        $formation = new Formation(null);
        $formation->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);

        $this->assertFalse(Access::isOuvert($formation));
    }
}
