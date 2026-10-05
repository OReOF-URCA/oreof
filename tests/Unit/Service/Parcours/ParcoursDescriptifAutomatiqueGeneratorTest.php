<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Parcours;

use App\Entity\Annee;
use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;
use App\Entity\DpeParcours;
use App\Entity\Parcours;
use App\Entity\SemestreParcours;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\DpeParcoursRepository;
use App\Service\Parcours\ParcoursDescriptifAutomatiqueGenerator;
use PHPUnit\Framework\TestCase;

final class ParcoursDescriptifAutomatiqueGeneratorTest extends TestCase
{
    private DpeParcoursRepository $dpeParcoursRepository;
    private ParcoursDescriptifAutomatiqueGenerator $generator;

    protected function setUp(): void
    {
        $this->dpeParcoursRepository = $this->createMock(DpeParcoursRepository::class);
        $this->generator = new ParcoursDescriptifAutomatiqueGenerator($this->dpeParcoursRepository);
    }

    public function testParcoursNonOuvert(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();
        $anneeUniv = new AnneeUniversitaire();
        $anneeUniv->setLibelle('2026-2027');
        $campagne->setAnneeUniversitaire($anneeUniv);

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertSame("Parcours non ouvert pour l'année 2026-2027", $result);
        $this->assertSame("Parcours non ouvert pour l'année 2026-2027", $parcours->getDescriptifHautPageAutomatique());
    }

    public function testParcoursOuvertAvecTousSemestresOuverts(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $sp1 = (new SemestreParcours(null, $parcours))->setOrdre(1)->setOuvert(true);
        $sp2 = (new SemestreParcours(null, $parcours))->setOrdre(2)->setOuvert(true);
        $parcours->addSemestreParcour($sp1);
        $parcours->addSemestreParcour($sp2);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertNull($result);
        $this->assertNull($parcours->getDescriptifHautPageAutomatique());
    }

    public function testParcoursOuvertAvecUnSemestreFerme(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $sp1 = (new SemestreParcours(null, $parcours))->setOrdre(1)->setOuvert(false);
        $sp2 = (new SemestreParcours(null, $parcours))->setOrdre(2)->setOuvert(true);
        $parcours->addSemestreParcour($sp1);
        $parcours->addSemestreParcour($sp2);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertSame("Le semestre 1 n'est pas ouvert.", $result);
        $this->assertSame("Le semestre 1 n'est pas ouvert.", $parcours->getDescriptifHautPageAutomatique());
    }

    public function testParcoursOuvertAvecPlusieursSemestresFermes(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $sp1 = (new SemestreParcours(null, $parcours))->setOrdre(1)->setOuvert(false);
        $sp2 = (new SemestreParcours(null, $parcours))->setOrdre(2)->setOuvert(false);
        $sp3 = (new SemestreParcours(null, $parcours))->setOrdre(3)->setOuvert(true);
        $sp4 = (new SemestreParcours(null, $parcours))->setOrdre(4)->setOuvert(true);
        $parcours->addSemestreParcour($sp1);
        $parcours->addSemestreParcour($sp2);
        $parcours->addSemestreParcour($sp3);
        $parcours->addSemestreParcour($sp4);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertSame("Les semestres 1, 2 ne sont pas ouverts.", $result);
    }

    public function testParcoursAvecAnneeFermee(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::OUVERT);

        $annee1 = (new Annee())->setOrdre(1)->setIsOuvert(false);
        $annee2 = (new Annee())->setOrdre(2)->setIsOuvert(true);
        $parcours->addAnnee($annee1);
        $parcours->addAnnee($annee2);

        $sp1 = (new SemestreParcours(null, $parcours))->setOrdre(1)->setOuvert(true);
        $sp2 = (new SemestreParcours(null, $parcours))->setOrdre(2)->setOuvert(true);
        $sp3 = (new SemestreParcours(null, $parcours))->setOrdre(3)->setOuvert(true);
        $sp4 = (new SemestreParcours(null, $parcours))->setOrdre(4)->setOuvert(true);
        $parcours->addSemestreParcour($sp1);
        $parcours->addSemestreParcour($sp2);
        $parcours->addSemestreParcour($sp3);
        $parcours->addSemestreParcour($sp4);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertSame("Les semestres 1, 2 ne sont pas ouverts.", $result);
    }

    public function testParcoursFermetureDefinitive(): void
    {
        $parcours = new Parcours(null);
        $campagne = new CampagneCollecte();

        $dpeParcours = new DpeParcours();
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::FERMETURE_DEFINITIVE);

        $result = $this->generator->generateForParcours($parcours, $campagne, $dpeParcours);

        $this->assertSame("Parcours en fermeture définitive", $result);
        $this->assertSame("Parcours en fermeture définitive", $parcours->getDescriptifHautPageAutomatique());
    }

    public function testGenerateForFormationScansAllParcours(): void
    {
        $formation = new \App\Entity\Formation(null);
        $campagne = new CampagneCollecte();
        $anneeUniv = new AnneeUniversitaire();
        $anneeUniv->setLibelle('2026-2027');
        $campagne->setAnneeUniversitaire($anneeUniv);

        // Parcours 1: Fermeture définitive
        $p1 = new Parcours($formation);
        $dpe1 = (new DpeParcours())->setCampagneCollecte($campagne)->setEtatReconduction(TypeModificationDpeEnum::FERMETURE_DEFINITIVE);
        $p1->addDpeParcour($dpe1);
        $formation->addParcour($p1);

        // Parcours 2: Non ouverture
        $p2 = new Parcours($formation);
        $dpe2 = (new DpeParcours())->setCampagneCollecte($campagne)->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
        $p2->addDpeParcour($dpe2);
        $formation->addParcour($p2);

        // Parcours 3: Ouvert avec semestre 2 fermé
        $p3 = new Parcours($formation);
        $dpe3 = (new DpeParcours())->setCampagneCollecte($campagne)->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        $p3->addDpeParcour($dpe3);
        $sp3_1 = (new SemestreParcours(null, $p3))->setOrdre(1)->setOuvert(true);
        $sp3_2 = (new SemestreParcours(null, $p3))->setOrdre(2)->setOuvert(false);
        $p3->addSemestreParcour($sp3_1);
        $p3->addSemestreParcour($sp3_2);
        $formation->addParcour($p3);

        // Parcours 4: Ouvert normal
        $p4 = new Parcours($formation);
        $dpe4 = (new DpeParcours())->setCampagneCollecte($campagne)->setEtatReconduction(TypeModificationDpeEnum::OUVERT);
        $p4->addDpeParcour($dpe4);
        $sp4_1 = (new SemestreParcours(null, $p4))->setOrdre(1)->setOuvert(true);
        $p4->addSemestreParcour($sp4_1);
        $formation->addParcour($p4);

        $this->generator->generateForFormation($formation, $campagne);

        $this->assertSame("Parcours en fermeture définitive", $p1->getDescriptifHautPageAutomatique());
        $this->assertSame("Parcours non ouvert pour l'année 2026-2027", $p2->getDescriptifHautPageAutomatique());
        $this->assertSame("Le semestre 2 n'est pas ouvert.", $p3->getDescriptifHautPageAutomatique());
        $this->assertNull($p4->getDescriptifHautPageAutomatique());
    }
}
