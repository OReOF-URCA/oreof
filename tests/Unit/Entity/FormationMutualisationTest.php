<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Annee;
use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Portail;
use PHPUnit\Framework\TestCase;

final class FormationMutualisationTest extends TestCase
{
    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setValue($entity, $id);
    }

    public function testLegacyTroncCommunFallback(): void
    {
        $formation = new Formation();
        $this->setId($formation, 1);

        $p1 = new Parcours($formation);
        $this->setId($p1, 10);
        $p2 = new Parcours($formation);
        $this->setId($p2, 20);
        $formation->addParcour($p1);
        $formation->addParcour($p2);

        // Données historiques : uniquement annees_tronc_commun = [1]
        $formation->setStructureSemestres(['annees_tronc_commun' => [1]]);

        $tcs = $formation->getTroncsCommuns();
        self::assertCount(1, $tcs);
        self::assertSame([1], $tcs[0]['annees']);
        self::assertSame([10, 20], $tcs[0]['parcours']);

        // isAnneeTroncCommun rétrocompatible
        self::assertTrue($formation->isAnneeTroncCommun(1));
        self::assertTrue($formation->isAnneeTroncCommun(1, $p1));
        self::assertTrue($formation->isAnneeTroncCommun(1, $p2));
        self::assertFalse($formation->isAnneeTroncCommun(2));
    }

    public function testMultiTroncCommunWithinFormation(): void
    {
        $formation = new Formation();
        $this->setId($formation, 1);

        $pA = new Parcours($formation);
        $this->setId($pA, 100);
        $pB = new Parcours($formation);
        $this->setId($pB, 200);
        $pC = new Parcours($formation);
        $this->setId($pC, 300);
        $pD = new Parcours($formation);
        $this->setId($pD, 400);

        $formation->addParcour($pA);
        $formation->addParcour($pB);
        $formation->addParcour($pC);
        $formation->addParcour($pD);

        // Groupe 1: Parcours A et B partagent l'année 1
        // Groupe 2: Parcours C et D partagent l'année 1
        $formation->setTroncsCommuns([
            [
                'id' => 'tc_ab',
                'nom' => 'Tronc commun A & B',
                'annees' => [1],
                'parcours' => [100, 200],
            ],
            [
                'id' => 'tc_cd',
                'nom' => 'Tronc commun C & D',
                'annees' => [1],
                'parcours' => [300, 400],
            ],
        ]);

        self::assertTrue($formation->isAnneeTroncCommun(1, $pA));
        self::assertTrue($formation->isAnneeTroncCommun(1, $pB));
        self::assertTrue($formation->isAnneeTroncCommun(1, $pC));
        self::assertTrue($formation->isAnneeTroncCommun(1, $pD));

        // Vérifier les groupes distincts
        $tcA = $formation->getTroncCommunForParcours($pA, 1);
        self::assertNotNull($tcA);
        self::assertSame('tc_ab', $tcA['id']);

        $tcC = $formation->getTroncCommunForParcours($pC, 1);
        self::assertNotNull($tcC);
        self::assertSame('tc_cd', $tcC['id']);

        // Autres parcours du groupe
        $autresA = $formation->getAutresParcoursInTroncCommun($pA, 1);
        self::assertCount(1, $autresA);
        self::assertSame(200, $autresA[0]->getId());

        $autresC = $formation->getAutresParcoursInTroncCommun($pC, 1);
        self::assertCount(1, $autresC);
        self::assertSame(400, $autresC[0]->getId());
    }

    public function testCapaciteDeduplicationWithMultiTcAndPortail(): void
    {
        $campagne = new CampagneCollecte();
        $this->setId($campagne, 1);

        $formation = new Formation($campagne);
        $this->setId($formation, 1);

        $pA = new Parcours($formation);
        $this->setId($pA, 100);
        $pB = new Parcours($formation);
        $this->setId($pB, 200);
        $pC = new Parcours($formation);
        $this->setId($pC, 300);
        $pD = new Parcours($formation); // Indépendant

        $formation->addParcour($pA);
        $formation->addParcour($pB);
        $formation->addParcour($pC);
        $formation->addParcour($pD);

        // A et B en Tronc commun 1 (L1)
        $formation->setTroncsCommuns([
            [
                'id' => 'tc_1',
                'nom' => 'Tronc commun 1',
                'annees' => [1],
                'parcours' => [100, 200],
            ],
        ]);

        // A: L1 cap 50
        $a1_a = new Annee();
        $this->setId($a1_a, 1001);
        $a1_a->setOrdre(1);
        $a1_a->setCapaciteAccueil(50);
        $a1_a->setIsOuvert(true);
        $a1_a->setParcours($pA);
        $pA->addAnnee($a1_a);

        // B: L1 cap 50 (synchronisé avec A, donc dédoublonné)
        $a1_b = new Annee();
        $this->setId($a1_b, 1002);
        $a1_b->setOrdre(1);
        $a1_b->setCapaciteAccueil(50);
        $a1_b->setIsOuvert(true);
        $a1_b->setParcours($pB);
        $pB->addAnnee($a1_b);

        // C: L1 cap 80 dans un Portail inter-mentions
        $portail = new Portail();
        $this->setId($portail, 999);
        $portail->setLibelle('Portail Sciences');
        $portail->setCampagne($campagne);

        $a1_c = new Annee();
        $this->setId($a1_c, 1003);
        $a1_c->setOrdre(1);
        $a1_c->setCapaciteAccueil(80);
        $a1_c->setIsOuvert(true);
        $a1_c->setPortail($portail);
        $a1_c->setParcours($pC);
        $pC->addAnnee($a1_c);

        // D: L1 cap 20 (indépendant, non mutualisé)
        $a1_d = new Annee();
        $this->setId($a1_d, 1004);
        $a1_d->setOrdre(1);
        $a1_d->setCapaciteAccueil(20);
        $a1_d->setIsOuvert(true);
        $a1_d->setParcours($pD);
        $pD->addAnnee($a1_d);

        // Capacité calculée attendue :
        // 50 (TC A&B compté une seule fois) + 80 (Portail compté une fois) + 20 (D indépendant) = 150
        self::assertSame(150, $formation->getCapaciteCalculee());
    }

    public function testPortailMultiFormationsStructure(): void
    {
        $campagne = new CampagneCollecte();
        $this->setId($campagne, 1);

        $f1 = new Formation($campagne);
        $this->setId($f1, 10);
        $p1 = new Parcours($f1);
        $this->setId($p1, 101);
        $p2 = new Parcours($f1);
        $this->setId($p2, 102);
        $f1->addParcour($p1);
        $f1->addParcour($p2);

        $f2 = new Formation($campagne);
        $this->setId($f2, 20);
        $p3 = new Parcours($f2);
        $this->setId($p3, 203);
        $p4 = new Parcours($f2);
        $this->setId($p4, 204);
        $p5 = new Parcours($f2);
        $this->setId($p5, 205);
        $f2->addParcour($p3);
        $f2->addParcour($p4);
        $f2->addParcour($p5);

        $portail = new Portail();
        $this->setId($portail, 1);
        $portail->setLibelle('Portail Math-Info L1');
        $portail->setCampagne($campagne);

        // Formation 1: Parcours 1, Année 1 dans le portail
        $a1_p1 = new Annee();
        $this->setId($a1_p1, 1001);
        $a1_p1->setOrdre(1);
        $a1_p1->setParcours($p1);
        $portail->addAnnee($a1_p1);

        // Formation 2: Parcours 3 et 4, Année 1 dans le portail
        $a1_p3 = new Annee();
        $this->setId($a1_p3, 2003);
        $a1_p3->setOrdre(1);
        $a1_p3->setParcours($p3);
        $portail->addAnnee($a1_p3);

        $a1_p4 = new Annee();
        $this->setId($a1_p4, 2004);
        $a1_p4->setOrdre(1);
        $a1_p4->setParcours($p4);
        $portail->addAnnee($a1_p4);

        $structure = $portail->getFormationsStructure();
        self::assertCount(2, $structure);

        // Formation 1 structure
        $f1Structure = null;
        $f2Structure = null;
        foreach ($structure as $item) {
            if ($item['formation']->getId() === 10) {
                $f1Structure = $item;
            } elseif ($item['formation']->getId() === 20) {
                $f2Structure = $item;
            }
        }

        self::assertNotNull($f1Structure);
        self::assertSame([1], $f1Structure['anneesOrdres']);
        self::assertSame([101], $f1Structure['parcoursIds']);
        self::assertSame([1001], $f1Structure['anneeIds']);

        self::assertNotNull($f2Structure);
        self::assertSame([1], $f2Structure['anneesOrdres']);
        self::assertSame([203, 204], $f2Structure['parcoursIds']);
        self::assertSame([2003, 2004], $f2Structure['anneeIds']);
    }

    public function testBidirectionalAnneePortailRelationship(): void
    {
        $portail = new Portail();
        $this->setId($portail, 55);

        $annee = new Annee();
        $this->setId($annee, 12);

        // Appel direct sur l'Annee
        $annee->setPortail($portail);
        self::assertSame($portail, $annee->getPortail());
        self::assertTrue($portail->getAnnees()->contains($annee));

        // Détachement
        $annee->setPortail(null);
        self::assertNull($annee->getPortail());
        self::assertFalse($portail->getAnnees()->contains($annee));
    }
}

