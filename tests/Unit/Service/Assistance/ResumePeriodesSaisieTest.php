<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Enums\CampagneModuleEnum;
use App\Service\Assistance\ResumePeriodesSaisie;
use DateTime;
use PHPUnit\Framework\TestCase;

class ResumePeriodesSaisieTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function statut(CampagneModuleEnum $module, bool $ouvert, ?string $fermeture = null, ?string $ouverture = null, string $message = 'message'): array
    {
        return [
            'module' => $module,
            'isOpen' => $ouvert,
            'closingDate' => $fermeture !== null ? new DateTime($fermeture) : null,
            'nextOpeningDate' => $ouverture !== null ? new DateTime($ouverture) : null,
            'message' => $message,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tous(bool $ouvert, ?string $fermeture = null, ?string $ouverture = null): array
    {
        return array_map(fn (CampagneModuleEnum $m) => $this->statut($m, $ouvert, $fermeture, $ouverture), CampagneModuleEnum::cases());
    }

    public function testToutOuvertMemeDateTientEnUneLigne(): void
    {
        $resume = (new ResumePeriodesSaisie())->resumer($this->tous(true, '2026-12-18'));

        self::assertSame('ouvertes', $resume['etat']);
        self::assertSame('Toutes les saisies sont ouvertes jusqu\'au 18/12/2026.', $resume['resume']);
        self::assertSame([], $resume['details']);
        self::assertTrue($resume['active']);
    }

    public function testToutOuvertDatesDifferentesDonneLaPremiereFermeture(): void
    {
        $statuts = $this->tous(true, '2026-12-18');
        $statuts[3] = $this->statut(CampagneModuleEnum::MCCC, true, '2026-12-01');

        $resume = (new ResumePeriodesSaisie())->resumer($statuts);

        self::assertSame('Toutes les saisies sont ouvertes (première fermeture le 01/12/2026).', $resume['resume']);
    }

    public function testModuleFermeSeulEstDetaille(): void
    {
        $statuts = $this->tous(true, '2026-12-18');
        $statuts[3] = $this->statut(CampagneModuleEnum::MCCC, false, message: 'Période de saisie terminée pour ce module (consultation seule).');

        $resume = (new ResumePeriodesSaisie())->resumer($statuts);

        self::assertSame('mixte', $resume['etat']);
        self::assertSame('Saisies ouvertes jusqu\'au 18/12/2026, sauf :', $resume['resume']);
        self::assertCount(1, $resume['details']);
        self::assertSame(CampagneModuleEnum::MCCC, $resume['details'][0]['module']);
    }

    public function testToutFermeAvecOuvertureProchaine(): void
    {
        $resume = (new ResumePeriodesSaisie())->resumer($this->tous(false, ouverture: '2027-01-05'));

        self::assertSame('fermees', $resume['etat']);
        self::assertSame('Toutes les saisies sont fermées (prochaine ouverture le 05/01/2027).', $resume['resume']);
        self::assertTrue($resume['active'], 'Une ouverture à venir garde la campagne pertinente');
    }

    public function testToutFermeSansOuvertureEstInactive(): void
    {
        $resume = (new ResumePeriodesSaisie())->resumer($this->tous(false));

        self::assertSame('Toutes les saisies sont fermées.', $resume['resume']);
        self::assertFalse($resume['active']);
    }
}
