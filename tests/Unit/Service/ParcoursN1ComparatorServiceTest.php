<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Comparison\AnomalyItem;
use App\DTO\Comparison\EcDiff;
use App\DTO\Comparison\FicheMatiereDiff;
use App\DTO\Comparison\MutualisationDiff;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\FicheMatiereMutualisable;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Service\Parcours\ParcoursN1ComparatorService;
use PHPUnit\Framework\TestCase;

final class ParcoursN1ComparatorServiceTest extends TestCase
{
    private ParcoursN1ComparatorService $service;

    protected function setUp(): void
    {
        $this->service = new ParcoursN1ComparatorService();
    }

    public function testResolveParcoursN1Direct(): void
    {
        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $this->assertSame($parcoursN1, $this->service->resolveParcoursN1($parcoursN));
    }

    public function testResolveParcoursN1ViaFormationFallback(): void
    {
        $formationN1 = new Formation();
        $parcoursN1 = new Parcours();
        $parcoursN1->setSigle('INFO-DACS');
        $parcoursN1->setLibelle('Parcours DACS');
        $formationN1->addParcour($parcoursN1);

        $formationN = new Formation();
        $formationN->setFormationOrigineCopie($formationN1);

        $parcoursN = new Parcours();
        $parcoursN->setSigle('INFO-DACS');
        $parcoursN->setLibelle('Parcours DACS');
        $formationN->addParcour($parcoursN);

        $resolved = $this->service->resolveParcoursN1($parcoursN);
        $this->assertSame($parcoursN1, $resolved);
    }

    public function testCompareDetectsDetachedFicheMatiereOnEc(): void
    {
        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('BUT Info N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('BUT Info N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        // Fiche en N-1
        $ficheN1 = new FicheMatiere();
        $ficheN1->setSigle('R1.01');
        $ficheN1->setLibelle('Initiation Algo');
        $ficheN1->setTypeMatiere('ressource');
        $ficheN1->setVolumeCmPresentiel(20.0);
        $ficheN1->setVolumeTdPresentiel(30.0);
        $parcoursN1->addFicheMatiere($ficheN1);

        // Structure N-1 avec un EC rattaché à la fiche
        $semN1 = new Semestre();
        $semN1->setOrdre(1);
        $spN1 = new SemestreParcours($semN1, $parcoursN1);
        $spN1->setOrdre(1);
        $spN1->setOuvert(true);
        $parcoursN1->addSemestreParcour($spN1);

        $ueN1 = new Ue();
        $ueN1->setOrdre(1);
        $ueN1->setLibelle('UE 1.1');
        $semN1->addUe($ueN1);

        $ecN1 = new ElementConstitutif();
        $ecN1->setCode('R1.01');
        $ecN1->setLibelle('Initiation Algo');
        $ecN1->setFicheMatiere($ficheN1);
        $ecN1->setEcts(4.0);
        $ueN1->addElementConstitutif($ecN1);

        // Structure N : l'EC a perdu sa fiche matière (sync cassée)
        $semN = new Semestre();
        $semN->setOrdre(1);
        $spN = new SemestreParcours($semN, $parcoursN);
        $spN->setOrdre(1);
        $spN->setOuvert(true);
        $parcoursN->addSemestreParcour($spN);

        $ueN = new Ue();
        $ueN->setOrdre(1);
        $ueN->setLibelle('UE 1.1');
        $semN->addUe($ueN);

        $ecN = new ElementConstitutif();
        $ecN->setCode('R1.01');
        $ecN->setLibelle('Initiation Algo');
        $ecN->setEcOrigineCopie($ecN1);
        $ecN->setFicheMatiere(null); // PERDUE !
        $ecN->setEcts(4.0);
        $ueN->addElementConstitutif($ecN);

        $result = $this->service->compare($parcoursN, $parcoursN1);

        $this->assertTrue($result->hasDifferences());
        $this->assertGreaterThan(0, $result->countErrors());

        $hasEcError = false;
        foreach ($result->anomalies as $a) {
            if ($a->category === 'ec_attachement' && $a->isError()) {
                $hasEcError = true;
                $this->assertStringContainsString('a perdu sa fiche matière en N', $a->message);
            }
        }
        $this->assertTrue($hasEcError, 'Une anomalie critique sur le détachement de la fiche matière doit être présente.');
    }

    public function testCompareDetectsLostMutualisation(): void
    {
        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('Parcours A N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('Parcours A N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        $ficheMutu = new FicheMatiere();
        $ficheMutu->setSigle('R2.05');
        $ficheMutu->setLibelle('Gestion de projet');

        $mutu = new FicheMatiereMutualisable();
        $mutu->setFicheMatiere($ficheMutu);
        $mutu->setParcours($parcoursN1);
        $parcoursN1->addFicheMatiereParcour($mutu);

        // En N, aucune mutualisation enregistrée
        $result = $this->service->compare($parcoursN, $parcoursN1);

        $this->assertSame(1, $result->countMutualisationsDifferences());
        $this->assertSame(MutualisationDiff::STATUS_LOST_IN_N, $result->mutualisations[0]->status);
    }

    public function testCompareCalculatesTotalsAndHoursDiff(): void
    {
        $parcoursN1 = new Parcours();
        $parcoursN = new Parcours();
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        // Fiche N-1 (20h CM)
        $fN1 = new FicheMatiere();
        $fN1->setSigle('R1.01');
        $fN1->setVolumeCmPresentiel(20.0);
        $parcoursN1->addFicheMatiere($fN1);

        // Fiche N (30h CM -> modifiée)
        $fN = new FicheMatiere();
        $fN->setSigle('R1.01');
        $fN->setVolumeCmPresentiel(30.0);
        $fN->setFicheMatiereOrigineCopie($fN1);
        $parcoursN->addFicheMatiere($fN);

        $result = $this->service->compare($parcoursN, $parcoursN1);

        $this->assertSame(1, $result->countFichesDifferences());
        $this->assertFalse($result->fiches[0]->isIdentical());
        $this->assertSame(FicheMatiereDiff::STATUS_MODIFIED, $result->fiches[0]->status);
        $this->assertTrue($result->fiches[0]->differences['cmPres']->isDiff);
        $this->assertEquals(20.0, $result->fiches[0]->differences['cmPres']->valN1);
        $this->assertEquals(30.0, $result->fiches[0]->differences['cmPres']->valN);
    }

    public function testCompareCollectsFichesFromMaquetteEcsAndMutualisations(): void
    {
        $parcoursN1 = new Parcours();
        $parcoursN1->setLibelle('Parcours B N-1');

        $parcoursN = new Parcours();
        $parcoursN->setLibelle('Parcours B N');
        $parcoursN->setParcoursOrigineCopie($parcoursN1);

        // Fiche 1 : portée par un EC de tronc commun (pas dans getFicheMatieres() du parcours !)
        $ficheEc = new FicheMatiere();
        $ficheEc->setSigle('R1.01');
        $ficheEc->setLibelle('Algo');

        $semN = new Semestre();
        $spN = new SemestreParcours($semN, $parcoursN);
        $spN->setOuvert(true);
        $parcoursN->addSemestreParcour($spN);

        $ueN = new Ue();
        $semN->addUe($ueN);
        $ecN = new ElementConstitutif();
        $ecN->setCode('R1.01');
        $ecN->setFicheMatiere($ficheEc);
        $ueN->addElementConstitutif($ecN);

        // Fiche 2 : mutualisée
        $ficheMutu = new FicheMatiere();
        $ficheMutu->setSigle('R1.02');
        $ficheMutu->setLibelle('Web');

        $mutu = new FicheMatiereMutualisable();
        $mutu->setFicheMatiere($ficheMutu);
        $mutu->setParcours($parcoursN);
        $parcoursN->addFicheMatiereParcour($mutu);

        // Le parcours n'a AUCUNE fiche directe dans getFicheMatieres()
        $this->assertCount(0, $parcoursN->getFicheMatieres());

        $result = $this->service->compare($parcoursN, $parcoursN1, scope: 'parcours');

        $this->assertSame(2, count($result->fiches));
        $this->assertSame(2, $result->totalFichesParcours);

        $sigles = array_map(fn($f) => $f->sigle, $result->fiches);
        $this->assertContains('R1.01', $sigles);
        $this->assertContains('R1.02', $sigles);

        $sources = [];
        foreach ($result->fiches as $f) {
            $sources[$f->sigle] = $f->scopeSource;
        }
        $this->assertSame('maquette', $sources['R1.01']);
        $this->assertSame('mutualisee', $sources['R1.02']);
    }

    public function testCompareWithScopeFormationCollectsAllFormationFiches(): void
    {
        $formationN = new Formation();
        $parcours1 = new Parcours();
        $parcours1->setLibelle('Parcours 1');
        $parcours2 = new Parcours();
        $parcours2->setLibelle('Parcours 2');
        $formationN->addParcour($parcours1);
        $formationN->addParcour($parcours2);

        $parcours1_N1 = new Parcours();
        $parcours1_N1->setLibelle('Parcours 1 N-1');
        $parcours1->setParcoursOrigineCopie($parcours1_N1);

        // Fiche sur parcours 2 uniquement
        $ficheP2 = new FicheMatiere();
        $ficheP2->setSigle('R3.01');
        $ficheP2->setLibelle('Spécialité P2');
        $parcours2->addFicheMatiere($ficheP2);

        // Comparaison avec scope 'parcours' : la fiche P2 n'est pas incluse
        $resParcours = $this->service->compare($parcours1, $parcours1_N1, scope: 'parcours');
        $this->assertSame(0, count($resParcours->fiches));
        $this->assertSame(1, $resParcours->totalFichesFormation);

        // Comparaison avec scope 'formation' : la fiche P2 est incluse
        $resFormation = $this->service->compare($parcours1, $parcours1_N1, scope: 'formation');
        $this->assertSame(1, count($resFormation->fiches));
        $this->assertSame('formation', $resFormation->fiches[0]->scopeSource);
        $this->assertSame('R3.01', $resFormation->fiches[0]->sigle);
    }

    public function testCompareDetectsFicheDetachedFromParcours(): void
    {
        $formationN1 = new Formation();
        $parcoursN1 = new Parcours();
        $formationN1->addParcour($parcoursN1);

        $formationN = new Formation();
        $formationN->setFormationOrigineCopie($formationN1);
        $parcoursN = new Parcours();
        $parcoursN->setParcoursOrigineCopie($parcoursN1);
        $parcoursAutre = new Parcours();
        $formationN->addParcour($parcoursN);
        $formationN->addParcour($parcoursAutre);

        // Fiche en N-1 portée par le parcours
        $ficheN1 = new FicheMatiere();
        $ficheN1->setSigle('R1.05');
        $ficheN1->setLibelle('BDD');
        $parcoursN1->addFicheMatiere($ficheN1);

        // En N, la fiche existe dans la formation mais est portée UNIQUEMENT par parcoursAutre
        // (pas dans parcoursN, ni dans ses ECs, ni dans ses mutualisations)
        $ficheN = new FicheMatiere();
        $ficheN->setSigle('R1.05');
        $ficheN->setLibelle('BDD');
        $ficheN->setFicheMatiereOrigineCopie($ficheN1);
        $parcoursAutre->addFicheMatiere($ficheN);

        $result = $this->service->compare($parcoursN, $parcoursN1, scope: 'parcours');

        $this->assertSame(1, count($result->fiches));
        $diff = $result->fiches[0];
        $this->assertTrue($diff->isDetachedFromParcours);
        $this->assertNotNull($diff->ficheN); // Existe dans la formation
        $this->assertSame($ficheN, $diff->ficheN);
        $this->assertSame($ficheN1, $diff->ficheN1);
    }
}
