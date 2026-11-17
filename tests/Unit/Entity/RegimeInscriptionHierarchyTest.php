<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Annee;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Enums\RegimeInscriptionEnum;
use App\Referentiel\RegimeInscription;
use PHPUnit\Framework\TestCase;

final class RegimeInscriptionHierarchyTest extends TestCase
{
    public function testParcoursAndFormationReconstructFromAnneesWhenConfigured(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);
        $formation->addParcour($parcours);

        $annee1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $annee2 = (new Annee())->setOrdre(2)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI, RegimeInscription::FI_APPRENTISSAGE]);
        $parcours->addAnnee($annee1);
        $parcours->addAnnee($annee2);

        // Parcours reconstructs union of Annees
        $expected = [RegimeInscription::FI, RegimeInscription::FI_APPRENTISSAGE];
        self::assertSame($expected, $parcours->getRegimeInscriptionValues());
        self::assertEquals([RegimeInscriptionEnum::FI, RegimeInscriptionEnum::FI_APPRENTISSAGE], $parcours->getRegimeInscription());
        self::assertTrue($parcours->hasRegimeInscription(RegimeInscriptionEnum::FI));
        self::assertTrue($parcours->hasRegimeInscription(RegimeInscription::FI_APPRENTISSAGE));
        self::assertFalse($parcours->hasRegimeInscription(RegimeInscriptionEnum::FC));
        self::assertTrue($parcours->isAlternance());

        // Formation reconstructs from Parcours
        self::assertSame($expected, $formation->getRegimeInscriptionValues());
        self::assertEquals([RegimeInscriptionEnum::FI, RegimeInscriptionEnum::FI_APPRENTISSAGE], $formation->getRegimeInscription());
        self::assertTrue($formation->hasRegimeInscription(RegimeInscription::FI));
        self::assertTrue($formation->hasRegimeInscription(RegimeInscriptionEnum::FI_APPRENTISSAGE));
        self::assertFalse($formation->hasRegimeInscription(RegimeInscription::FC));
    }

    public function testFormationReconstructsFromMultipleParcours(): void
    {
        $formation = new Formation();

        $parcours1 = (new Parcours($formation))->setLibelle('P1');
        $formation->addParcour($parcours1);
        $a1 = (new Annee())->setOrdre(1)->setParcours($parcours1)->setRegimeInscription([RegimeInscription::FI]);
        $parcours1->addAnnee($a1);

        $parcours2 = (new Parcours($formation))->setLibelle('P2');
        $formation->addParcour($parcours2);
        $a2 = (new Annee())->setOrdre(1)->setParcours($parcours2)->setRegimeInscription([RegimeInscription::FC, RegimeInscription::FC_CONTRAT_PRO]);
        $parcours2->addAnnee($a2);

        self::assertSame([RegimeInscription::FI], $parcours1->getRegimeInscriptionValues());
        self::assertSame([RegimeInscription::FC, RegimeInscription::FC_CONTRAT_PRO], $parcours2->getRegimeInscriptionValues());

        // Formation union of P1 and P2
        self::assertSame(
            [RegimeInscription::FI, RegimeInscription::FC, RegimeInscription::FC_CONTRAT_PRO],
            $formation->getRegimeInscriptionValues()
        );
    }

    public function testTransitionFallbackToParcoursBddWhenAnneesAreNotCompleted(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);
        $formation->addParcour($parcours);

        // Previous campaign: Parcours has values stored in BDD, but Annees have no regimes configured
        $parcours->setRegimeInscription([RegimeInscription::FI, RegimeInscription::FC]);
        $annee1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([]);
        $annee2 = (new Annee())->setOrdre(2)->setParcours($parcours)->setRegimeInscription(null);
        $parcours->addAnnee($annee1);
        $parcours->addAnnee($annee2);

        // Parcours uses BDD fallback
        self::assertSame([RegimeInscription::FI, RegimeInscription::FC], $parcours->getRegimeInscriptionValues());
        self::assertEquals([RegimeInscriptionEnum::FI, RegimeInscriptionEnum::FC], $parcours->getRegimeInscription());

        // Formation uses Parcours BDD fallback
        self::assertSame([RegimeInscription::FI, RegimeInscription::FC], $formation->getRegimeInscriptionValues());

        // Incomplete Annee falls back to Parcours BDD value
        self::assertSame([RegimeInscription::FI, RegimeInscription::FC], $annee1->getRegimeInscriptionValues());
        self::assertSame([RegimeInscription::FI, RegimeInscription::FC], $annee2->getRegimeInscriptionValues());
    }

    public function testTransitionFallbackToFormationBddWhenBothAnneesAndParcoursAreEmpty(): void
    {
        $formation = new Formation();
        $formation->setRegimeInscription([RegimeInscription::FI]);

        $parcours = new Parcours($formation);
        $formation->addParcour($parcours);
        // Parcours has empty BDD
        $parcours->setRegimeInscription([]);

        $annee = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([]);
        $parcours->addAnnee($annee);

        // Annee falls back to Formation BDD
        self::assertSame([RegimeInscription::FI], $annee->getRegimeInscriptionValues());
        // Parcours falls back to Formation BDD
        self::assertSame([RegimeInscription::FI], $parcours->getRegimeInscriptionValues());
        // Formation uses its own BDD
        self::assertSame([RegimeInscription::FI], $formation->getRegimeInscriptionValues());
    }

    public function testAnneeIsOfficialSourceAndOverridesParcoursBdd(): void
    {
        $formation = new Formation();
        $formation->setRegimeInscription([RegimeInscription::FC]); // Old stale formation BDD

        $parcours = new Parcours($formation);
        $formation->addParcour($parcours);
        $parcours->setRegimeInscription([RegimeInscription::FC]); // Old stale parcours BDD

        // Official new campaign configuration on Annee
        $annee = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $parcours->addAnnee($annee);

        // Parcours and Formation reconstruct from Annee (the only official source)
        self::assertSame([RegimeInscription::FI], $parcours->getRegimeInscriptionValues());
        self::assertSame([RegimeInscription::FI], $formation->getRegimeInscriptionValues());
        self::assertSame([RegimeInscription::FI], $annee->getRegimeInscriptionValues());
    }

    public function testPhraseAnneesAlternanceAndDisplayModalitesAlternance(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);

        $a1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $a2 = (new Annee())->setOrdre(2)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI, RegimeInscription::FI_APPRENTISSAGE]);
        $a3 = (new Annee())->setOrdre(3)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI, RegimeInscription::FI_APPRENTISSAGE]);
        $parcours->addAnnee($a1);
        $parcours->addAnnee($a2);
        $parcours->addAnnee($a3);

        self::assertTrue($a1->hasRegimeInscription(RegimeInscription::FI));
        self::assertFalse($a1->isAlternance());
        self::assertTrue($a2->isAlternance());
        self::assertTrue($a3->isAlternance());
        self::assertTrue($parcours->isAlternance());

        self::assertSame("L'alternance est proposée en 2e et 3e année.", $parcours->getPhraseAnneesAlternance());

        // Default display without free text
        self::assertSame("<p>L'alternance est proposée en 2e et 3e année.</p>", $parcours->getDisplayModalitesAlternance());

        // Display with free text appended
        $parcours->setModalitesAlternance('<p>Rythme : 2 semaines CFA / 2 semaines entreprise.</p>');
        self::assertSame(
            "<p>L'alternance est proposée en 2e et 3e année.</p><p>Rythme : 2 semaines CFA / 2 semaines entreprise.</p>",
            $parcours->getDisplayModalitesAlternance()
        );
    }

    public function testPhraseAnneesAlternanceSingleYear(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);

        $a1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $a2 = (new Annee())->setOrdre(2)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $a3 = (new Annee())->setOrdre(3)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI, RegimeInscription::FI_APPRENTISSAGE]);
        $parcours->addAnnee($a1);
        $parcours->addAnnee($a2);
        $parcours->addAnnee($a3);

        self::assertSame("L'alternance est proposée en 3e année.", $parcours->getPhraseAnneesAlternance());
    }

    public function testPhraseAnneesAlternanceAllThreeYears(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);

        $a1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI_APPRENTISSAGE]);
        $a2 = (new Annee())->setOrdre(2)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI_APPRENTISSAGE]);
        $a3 = (new Annee())->setOrdre(3)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI_APPRENTISSAGE]);
        $parcours->addAnnee($a1);
        $parcours->addAnnee($a2);
        $parcours->addAnnee($a3);

        self::assertSame("L'alternance est proposée en 1re, 2e et 3e année.", $parcours->getPhraseAnneesAlternance());
    }

    public function testParcoursNotInAlternance(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);

        $a1 = (new Annee())->setOrdre(1)->setParcours($parcours)->setRegimeInscription([RegimeInscription::FI]);
        $parcours->addAnnee($a1);

        self::assertFalse($parcours->isAlternance());
        self::assertNull($parcours->getPhraseAnneesAlternance());
        self::assertNull($parcours->getDisplayModalitesAlternance());
    }

    public function testParcoursAlternanceTransitionFallback(): void
    {
        $formation = new Formation();
        $parcours = new Parcours($formation);
        $parcours->setRegimeInscription([RegimeInscription::FI_APPRENTISSAGE]);

        // No Annees created (old campaign)
        self::assertTrue($parcours->isAlternance());
        self::assertSame("L'alternance est proposée pour ce parcours.", $parcours->getPhraseAnneesAlternance());
        self::assertSame("<p>L'alternance est proposée pour ce parcours.</p>", $parcours->getDisplayModalitesAlternance());
    }
}
