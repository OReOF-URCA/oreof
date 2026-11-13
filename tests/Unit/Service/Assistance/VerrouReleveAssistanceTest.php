<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Assistance;

use App\Service\Assistance\VerrouReleveAssistance;
use PHPUnit\Framework\TestCase;

class VerrouReleveAssistanceTest extends TestCase
{
    public function testUneSecondeReleveSimultaneeEstRefusee(): void
    {
        $fichier = sys_get_temp_dir() . '/verrou-assistance-' . uniqid('', true);
        $premier = new VerrouReleveAssistance($fichier);
        $second = new VerrouReleveAssistance($fichier);

        self::assertTrue($premier->acquerir());
        self::assertFalse($second->acquerir());

        $premier->liberer();
        self::assertTrue($second->acquerir());
        $second->liberer();
        @unlink($fichier);
    }
}
