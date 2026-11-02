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
use App\Entity\TypeDiplome;
use App\Entity\Ville;
use App\Enums\TypeModificationDpeEnum;
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

    public function testParcoursupExclutParcoursFermeEtPlateformeInactive(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $formation = $this->licence(1, 'Psychologie');

        [$dpeOuvert, $l1Ouvert] = $this->parcoursL1($formation, 10, 'Psychologie', 150);
        [$dpeFerme, $l1Ferme] = $this->parcoursL1($formation, 11, 'LAS', 20);
        $dpeFerme->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
        [$dpeInactif, $l1Inactif] = $this->parcoursL1($formation, 12, 'Préprofessionnalisation', 30);
        $paramInactif = (new PlateformeAdmissionParametre())->setPlateforme($psup)->setCampagne($campagne)->setActive(false);

        $rows = $this->buildParcoursupRows(
            [$dpeOuvert, $dpeFerme, $dpeInactif],
            [10 => [$l1Ouvert], 11 => [$l1Ferme], 12 => [$l1Inactif]],
            [$l1Inactif->getId() => [$paramInactif]],
            $psup,
            $campagne
        );

        self::assertCount(1, $rows);
        self::assertSame('Psychologie', (string) $rows[0]['parcours']);
        self::assertSame(150, $rows[0]['capacite_n']);
    }

    public function testParcoursupRegroupeLAnneeEnTroncCommun(): void
    {
        $campagne = new CampagneCollecte();
        $psup = $this->psup();
        $reims = $this->licence(1, 'Histoire');
        $reims->setAnneeTroncCommun(1, true);

        $dpes = [];
        $annees = [];
        foreach ([10 => 'Archéologie', 11 => 'Histoire', 12 => 'Géographie'] as $id => $libelle) {
            [$dpes[], $annees[$id]] = $this->parcoursL1($reims, $id, $libelle, 200);
            $annees[$id] = [$annees[$id]];
        }

        $rows = $this->buildParcoursupRows($dpes, $annees, [], $psup, $campagne);

        self::assertCount(1, $rows);
        self::assertSame('Tronc commun : Archéologie / Histoire / Géographie', $rows[0]['parcours']);
        self::assertSame(200, $rows[0]['capacite_n']);
    }

    private function psup(): PlateformeAdmission
    {
        $psup = (new PlateformeAdmission())->setCode('parcoursup')->setLibelle('Parcoursup');
        $this->setId($psup, 1);

        return $psup;
    }

    private function licence(int $id, string $mention): Formation
    {
        $formation = (new Formation(null))
            ->setMentionTexte($mention)
            ->setTypeDiplome((new TypeDiplome())->setLibelleCourt('L'));
        $this->setId($formation, $id);

        return $formation;
    }

    /**
     * @return array{DpeParcours, Annee}
     */
    private function parcoursL1(Formation $formation, int $id, string $libelle, int $capacite): array
    {
        $parcours = new Parcours($formation);
        $parcours->setLibelle($libelle);
        $this->setId($parcours, $id);
        $formation->addParcour($parcours);

        $annee = (new Annee())->setOrdre(1)->setIsOuvert(true)->setCapaciteAccueil($capacite)->setParcours($parcours);
        $this->setId($annee, $id * 100);

        return [(new DpeParcours())->setParcours($parcours), $annee];
    }

    private function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }

    /**
     * @param list<DpeParcours> $dpes
     * @param array<int, list<Annee>> $annees
     * @param array<int, list<PlateformeAdmissionParametre>> $params
     * @return list<array<string, mixed>>
     */
    private function buildParcoursupRows(array $dpes, array $annees, array $params, PlateformeAdmission $psup, CampagneCollecte $campagne): array
    {
        $method = new \ReflectionMethod($this->service, 'buildParcoursupRows');

        return $method->invoke($this->service, $dpes, $annees, [], $params, $psup, $campagne, null, false, [], [], []);
    }
}
