<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\PlateformeAdmissionParametre;
use App\Entity\Ville;
use App\Service\Offre\OffreAnnexesExportService;
use PHPUnit\Framework\TestCase;

final class OffreAnnexesExportServiceTest extends TestCase
{
    private OffreAnnexesExportService $service;

    protected function setUp(): void
    {
        // Les helpers testés n'utilisent aucune dépendance (SpreadsheetRenderer est final, non doublable).
        $this->service = (new \ReflectionClass(OffreAnnexesExportService::class))->newInstanceWithoutConstructor();
    }

    private function ville(string $libelle): Ville
    {
        return (new Ville())->setLibelle($libelle);
    }

    public function testResolveLieuPrivilegieLaLocalisationDuParcours(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Reims'));
        $parcours = new Parcours($formation);
        $parcours->setLocalisation($this->ville('Troyes'));

        self::assertSame('Troyes', $this->service->resolveLieu($parcours));
    }

    public function testResolveLieuUtiliseLAncienChampVilleSansLocalisation(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Reims'));
        $parcours = new Parcours($formation);
        $parcours->setVille($this->ville('Charleville-Mézières'));

        self::assertSame('Charleville-Mézières', $this->service->resolveLieu($parcours));
    }

    public function testResolveLieuRetombeSurLaMention(): void
    {
        $formation = new Formation(null);
        $formation->addLocalisationMention($this->ville('Châlons-en-Champagne'));

        self::assertSame('Châlons-en-Champagne', $this->service->resolveLieu(new Parcours($formation)));
    }

    public function testResolveLieuParDefautReims(): void
    {
        self::assertSame('Reims', $this->service->resolveLieu(new Parcours(new Formation(null))));
    }

    public function testIntegrationPlateformeSuitLaCaseQuandElleExiste(): void
    {
        $param = (new PlateformeAdmissionParametre())->setActive(false);

        self::assertFalse($this->service->isIntegrationPlateforme($param, true));
        self::assertTrue($this->service->isIntegrationPlateforme($param->setActive(true), true));
    }

    public function testIntegrationPlateformeImpossibleSiAnneeFermee(): void
    {
        $param = (new PlateformeAdmissionParametre())->setActive(true);

        self::assertFalse($this->service->isIntegrationPlateforme($param, false));
    }

    public function testIntegrationPlateformeSansParametreSuitLOuverture(): void
    {
        self::assertTrue($this->service->isIntegrationPlateforme(null, true));
        self::assertFalse($this->service->isIntegrationPlateforme(null, false));
    }
}
