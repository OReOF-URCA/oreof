<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Validation;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmission;
use App\Entity\PlateformeAdmissionParametre;
use App\Entity\TypeDiplome;
use App\Entity\TypeDiplomePlateformeAdmission;
use App\Service\Validation\OffreValidationService;
use PHPUnit\Framework\TestCase;

final class OffreValidationServiceTest extends TestCase
{
    private CampagneCollecte $campagne;
    private TypeDiplome $typeDiplome;
    private PlateformeAdmission $plateforme;
    private Parcours $parcours;
    private Annee $annee2;

    protected function setUp(): void
    {
        $this->campagne = new CampagneCollecte();

        $this->typeDiplome = new TypeDiplome();
        $this->typeDiplome->setLibelle('Diplôme d\'ingénieur');
        $this->setId($this->typeDiplome, 13);

        $this->plateforme = new PlateformeAdmission();
        $this->plateforme->setLibelle('eCandidat');
        $this->plateforme->setActive(true);
        $this->setId($this->plateforme, 2);

        $formation = new Formation($this->campagne);
        $formation->setTypeDiplome($this->typeDiplome);
        $this->parcours = new Parcours($formation);
        $this->parcours->setLibelle('Matériaux et Mécanique');

        $this->annee2 = new Annee();
        $this->annee2->setOrdre(2);
        $this->annee2->setIsOuvert(true);
        $this->annee2->setParcours($this->parcours);
        $this->setId($this->annee2, 200);
    }

    public function testCapaciteOptionnelleSurLAnneeMalgreUneAutreConfigurationQuiNeCouvrePasLAnnee(): void
    {
        // Configuration affichée sur la page « Configurer » pour l'année 2 : capacité optionnelle.
        $tpaAffichee = $this->tpa([2, 3], []);
        // Doublon qui ne propose pas la plateforme en année 2 mais y déclare la capacité obligatoire.
        $tpaDoublon = $this->tpa([1], [2]);

        foreach ([[$tpaAffichee, $tpaDoublon], [$tpaDoublon, $tpaAffichee]] as $tpas) {
            self::assertSame([], $this->anomalies($tpas, [$this->paramInactifSansCapacite()]));
        }
    }

    public function testPlateformeDesactiveeIgnoree(): void
    {
        $this->plateforme->setActive(false);

        self::assertSame([], $this->anomalies([$this->tpa([2], [2])], [$this->paramInactifSansCapacite()]));
        self::assertSame([], $this->anomalies([$this->tpa([2], [2])], []));
    }

    public function testCapaciteObligatoireInactiveSansCapaciteSignalee(): void
    {
        $anomalies = $this->anomalies([$this->tpa([2], [2])], [$this->paramInactifSansCapacite()]);

        self::assertCount(1, $anomalies);
        self::assertStringContainsString('(Année 2) : plateforme eCandidat inactive et sans capacité', $anomalies[0]);
    }

    public function testCapaciteObligatoireSansParametreSignalee(): void
    {
        $anomalies = $this->anomalies([$this->tpa([2], [2])], []);

        self::assertCount(1, $anomalies);
        self::assertStringContainsString('(Année 2) : plateforme eCandidat inactive et sans capacité', $anomalies[0]);
    }

    /**
     * @param list<TypeDiplomePlateformeAdmission> $tpas
     * @param list<PlateformeAdmissionParametre> $params
     * @return array<int, string>
     */
    private function anomalies(array $tpas, array $params): array
    {
        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($this->parcours);
        $dpeParcours->setCampagneCollecte($this->campagne);

        return (new OffreValidationService())->getAnomaliesParcours(
            $this->parcours,
            $this->campagne,
            $dpeParcours,
            [200 => $params],
            [$this->annee2],
            [13 => $tpas]
        );
    }

    /**
     * @param list<int> $annees
     * @param list<int> $anneesCapaciteRequise
     */
    private function tpa(array $annees, array $anneesCapaciteRequise): TypeDiplomePlateformeAdmission
    {
        $tpa = new TypeDiplomePlateformeAdmission();
        $tpa->setTypeDiplome($this->typeDiplome);
        $tpa->setPlateforme($this->plateforme);
        $tpa->setCampagne($this->campagne);
        $tpa->setAnnees($annees);
        $tpa->setAnneesCapaciteRequise($anneesCapaciteRequise);

        return $tpa;
    }

    private function paramInactifSansCapacite(): PlateformeAdmissionParametre
    {
        $param = new PlateformeAdmissionParametre();
        $param->setAnnee($this->annee2);
        $param->setPlateforme($this->plateforme);
        $param->setCampagne($this->campagne);
        $param->setActive(false);

        return $param;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
