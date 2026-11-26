<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Parcours;
use PHPUnit\Framework\TestCase;

final class ParcoursLogoTest extends TestCase
{
    public function testAddAndGetLogosNormalized(): void
    {
        $parcours = new Parcours();
        $this->assertSame([], $parcours->getLogosNormalized());

        // Test string format (legacy)
        $parcours->setLogo(['logo1.png', 'logo2.png']);
        $normalized = $parcours->getLogosNormalized();
        $this->assertCount(2, $normalized);
        $this->assertSame(['filename' => 'logo1.png', 'url' => null], $normalized[0]);
        $this->assertSame(['filename' => 'logo2.png', 'url' => null], $normalized[1]);

        // Test addLogo with URL
        $parcours->addLogo('logo3.png', 'https://example.com');
        $normalized = $parcours->getLogosNormalized();
        $this->assertCount(3, $normalized);
        $this->assertSame(['filename' => 'logo3.png', 'url' => 'https://example.com'], $normalized[2]);

        // Test updateLogoUrl
        $parcours->updateLogoUrl('logo1.png', 'https://partner.org');
        $normalized = $parcours->getLogosNormalized();
        $this->assertSame('https://partner.org', $normalized[0]['url']);

        // Test removeLogo
        $parcours->removeLogo('logo2.png');
        $normalized = $parcours->getLogosNormalized();
        $this->assertCount(2, $normalized);
        $this->assertSame('logo1.png', $normalized[0]['filename']);
        $this->assertSame('logo3.png', $normalized[1]['filename']);
    }
}
