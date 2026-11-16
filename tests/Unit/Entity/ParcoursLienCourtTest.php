<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Parcours;
use PHPUnit\Framework\TestCase;

class ParcoursLienCourtTest extends TestCase
{
    public function testGetAndSetLienCourt(): void
    {
        $parcours = new Parcours();
        $this->assertNull($parcours->getLienCourt());

        $url = 'https://www.monmaster.gouv.fr/formation/master-informatique-123';
        $parcours->setLienCourt($url);
        $this->assertSame($url, $parcours->getLienCourt());

        $parcours->setLienCourt(null);
        $this->assertNull($parcours->getLienCourt());
    }

    public function testLienCourtIsPreservedOnClone(): void
    {
        $parcours = new Parcours();
        $url = 'https://parcoursup.fr/formation/456';
        $parcours->setLibelle('Parcours Initial');
        $parcours->setLienCourt($url);

        $cloned = clone $parcours;

        $this->assertSame($url, $cloned->getLienCourt(), 'Le lien court doit être recopié à l\'identique lors du clonage (duplication de campagne/parcours).');
    }
}
