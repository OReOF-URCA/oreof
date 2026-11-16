<?php

declare(strict_types=1);

namespace App\Tests\Unit\TypeDiplome;

use App\Entity\ButApprentissageCritique;
use App\Entity\ButCompetence;
use App\Entity\ButNiveau;
use App\Entity\ElementConstitutif;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreParcours;
use App\Entity\Ue;
use App\Repository\ButCompetenceRepository;
use App\TypeDiplome\Diplomes\But\Services\CalculBccCroiseBut;
use PHPUnit\Framework\TestCase;

class CalculBccCroiseButTest extends TestCase
{
    public function testCalculBccCroiseButCalculatesMatrixAndCoverage(): void
    {
        $formation = new Formation(null);
        $formation->setMentionTexte('BUT Informatique');

        $parcours = new Parcours();
        $parcours->setLibelle('Déploiement');
        $formation->addParcour($parcours);

        // Compétence 1 avec Niveau BUT1 et 2 AC
        $comp = new ButCompetence();
        $comp->setNumero(1);
        $comp->setNomCourt('Réaliser');
        $comp->setLibelle('Réaliser un développement d\'application');
        $comp->setFormation($formation);

        // Reflection to set ID
        $reflComp = new \ReflectionProperty(ButCompetence::class, 'id');
        $reflComp->setValue($comp, 10);

        $niveau1 = new ButNiveau();
        $niveau1->setAnnee('BUT1');
        $niveau1->setCompetence($comp);
        $comp->addButNiveau($niveau1);

        $ac1 = new ButApprentissageCritique();
        $ac1->setCode('AC11.01');
        $ac1->setLibelle('Élaborer une application informatique');
        $ac1->setNiveau($niveau1);
        $reflAc = new \ReflectionProperty(ButApprentissageCritique::class, 'id');
        $reflAc->setValue($ac1, 101);
        $niveau1->addButApprentissageCritique($ac1);

        $ac2 = new ButApprentissageCritique();
        $ac2->setCode('AC11.02');
        $ac2->setLibelle('Tester une application');
        $ac2->setNiveau($niveau1);
        $reflAc->setValue($ac2, 102);
        $niveau1->addButApprentissageCritique($ac2);

        // Semestre 1 avec UE 1 et 1 FicheMatiere / EC
        $semestre = new Semestre();
        $semestre->setOrdre(1);

        $sp = new SemestreParcours($semestre, $parcours);
        $sp->setOrdre(1);
        $parcours->addSemestreParcour($sp);

        $ue = new Ue();
        $ue->setOrdre(1);
        $ue->setSemestre($semestre);
        $semestre->addUe($ue);

        $fiche = new FicheMatiere();
        $fiche->setSigle('R1.01');
        $fiche->setLibelle('Initiation au développement');
        $reflFiche = new \ReflectionProperty(FicheMatiere::class, 'id');
        $reflFiche->setValue($fiche, 201);
        $fiche->addApprentissagesCritique($ac1); // Mobilise AC 101

        $ec = new ElementConstitutif();
        $ec->setCode('R1.01');
        $ec->setUe($ue);
        $ec->setFicheMatiere($fiche);
        $ue->addElementConstitutif($ec);

        $repoMock = $this->createMock(ButCompetenceRepository::class);
        $repoMock->expects($this->once())
            ->method('findBy')
            ->with(['formation' => $formation])
            ->willReturn([$comp]);

        $service = new CalculBccCroiseBut($repoMock);
        $data = $service->calcul($parcours);

        $this->assertArrayHasKey('competences', $data);
        $this->assertArrayHasKey('niveaux', $data);
        $this->assertArrayHasKey('tabFichesMatieres', $data);
        $this->assertArrayHasKey('apprentissagesCritiques', $data);
        $this->assertArrayHasKey('stats', $data);

        $stats = $data['stats'];
        $this->assertSame(1, $stats['totalCompetences']);
        $this->assertSame(4, $stats['totalAc']); // 2 ACs counted for S1 and S2
        $this->assertSame(1, $stats['totalFiches']);
        $this->assertSame(1, $stats['totalMobilisations']);
        $this->assertSame(25, $stats['tauxCouverture']);
    }
}
