<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;
use App\Entity\ChangeRf;
use DateTime;
use PHPUnit\Framework\TestCase;

class ChangeRfTest extends TestCase
{
    public function testAnneeUniversitaireCalculationsWithDatePriseFonction(): void
    {
        $changeRf = new ChangeRf();

        // 1er septembre 2025 => Année 2025-2026
        $changeRf->setDatePriseFonction(new DateTime('2025-09-01'));
        $this->assertSame(2025, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2025-2026', $changeRf->getAnneeUniversitaireLibelle());

        // 15 octobre 2025 => Année 2025-2026
        $changeRf->setDatePriseFonction(new DateTime('2025-10-15'));
        $this->assertSame(2025, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2025-2026', $changeRf->getAnneeUniversitaireLibelle());

        // 15 février 2026 => Année 2025-2026
        $changeRf->setDatePriseFonction(new DateTime('2026-02-15'));
        $this->assertSame(2025, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2025-2026', $changeRf->getAnneeUniversitaireLibelle());

        // 31 août 2026 => Année 2025-2026
        $changeRf->setDatePriseFonction(new DateTime('2026-08-31'));
        $this->assertSame(2025, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2025-2026', $changeRf->getAnneeUniversitaireLibelle());

        // 1er septembre 2026 => Année 2026-2027
        $changeRf->setDatePriseFonction(new DateTime('2026-09-01'));
        $this->assertSame(2026, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2026-2027', $changeRf->getAnneeUniversitaireLibelle());
    }

    public function testAnneeUniversitaireCalculationsFallbackCampagne(): void
    {
        $changeRf = new ChangeRf();
        $annee = new AnneeUniversitaire();
        $annee->setAnnee(2024);

        $campagne = new CampagneCollecte();
        $campagne->setAnneeUniversitaire($annee);

        $changeRf->setCampagneCollecte($campagne);

        $this->assertSame(2024, $changeRf->getAnneeUniversitaireDebut());
        $this->assertSame('2024-2025', $changeRf->getAnneeUniversitaireLibelle());
    }
}
