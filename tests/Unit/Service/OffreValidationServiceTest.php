<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\PlateformeAdmissionParametre;
use App\Service\Validation\OffreValidationService;
use PHPUnit\Framework\TestCase;

final class OffreValidationServiceTest extends TestCase
{
    public function testMonMasterSignaleUneCapaciteGlobaleDifferenteDuDetail(): void
    {
        $anomalies = $this->anomaliesMonMaster(20, 20, 4);

        self::assertCount(1, $anomalies);
        self::assertStringContainsString('capacité globale MonMaster (20) différente de classique + alternance + spécifique (24)', $anomalies[0]);
    }

    public function testMonMasterAccepteUneCapaciteGlobaleEgaleAuDetail(): void
    {
        self::assertSame([], $this->anomaliesMonMaster(24, 20, 4));
    }

    public function testMonMasterSansDetailNeSignaleRien(): void
    {
        self::assertSame([], $this->anomaliesMonMaster(24, null, null));
    }

    /**
     * @return array<int, string>
     */
    private function anomaliesMonMaster(?int $globale, ?int $fi, ?int $specifique): array
    {
        $campagne = new CampagneCollecte();
        $parcours = new Parcours((new Formation(null))->setMentionTexte('Psychologie'));
        $parcours->setLibelle('Psychologie clinique');

        $annee = (new Annee())->setOrdre(1)->setIsOuvert(true)->setCapaciteAccueil(0)->setParcours($parcours);
        (new \ReflectionProperty($annee, 'id'))->setValue($annee, 1);

        $mm = (new PlateformeAdmission())->setCode('monmaster')->setLibelle('MonMaster');
        $param = (new PlateformeAdmissionParametre())
            ->setPlateforme($mm)
            ->setCampagne($campagne)
            ->setActive(true)
            ->setCapaciteGlobale($globale)
            ->setCapaciteFi($fi)
            ->setCapaciteSpecifique($specifique);

        $service = (new \ReflectionClass(OffreValidationService::class))->newInstanceWithoutConstructor();

        return $service->getAnomaliesParcours(
            $parcours,
            $campagne,
            (new DpeParcours())->setParcours($parcours),
            [1 => [$param]],
            [$annee],
            []
        );
    }
}
