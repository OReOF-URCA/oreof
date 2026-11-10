<?php

namespace App\Tests\Unit\Classes\Process;

use App\Classes\Process\ChangeRfProcess;
use App\Entity\AnneeUniversitaire;
use App\Entity\CampagneCollecte;
use App\Entity\Formation;
use App\Repository\FormationRepository;
use App\Repository\ProfilRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChangeRfProcessTest extends TestCase
{
    public function testGetLigneeFormationsStopsAtAnneeMin(): void
    {
        $profilRepository = $this->createStub(ProfilRepository::class);
        $formationRepository = $this->createStub(FormationRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $translator = $this->createStub(TranslatorInterface::class);
        $workflow = $this->createStub(WorkflowInterface::class);

        $process = new ChangeRfProcess(
            $profilRepository,
            $formationRepository,
            $entityManager,
            $eventDispatcher,
            $translator,
            $workflow,
        );

        // Année 2023
        $annee2023 = new AnneeUniversitaire();
        $annee2023->setAnnee(2023);
        $campagne2023 = new CampagneCollecte();
        $campagne2023->setAnneeUniversitaire($annee2023);

        $f2023 = new Formation();
        $f2023->setDpe($campagne2023);
        $this->setId($f2023, 101);

        // Année 2024
        $annee2024 = new AnneeUniversitaire();
        $annee2024->setAnnee(2024);
        $campagne2024 = new CampagneCollecte();
        $campagne2024->setAnneeUniversitaire($annee2024);

        $f2024 = new Formation();
        $f2024->setDpe($campagne2024);
        $f2024->setFormationOrigineCopie($f2023);
        $this->setId($f2024, 102);

        // Année 2025 (formation courante)
        $annee2025 = new AnneeUniversitaire();
        $annee2025->setAnnee(2025);
        $campagne2025 = new CampagneCollecte();
        $campagne2025->setAnneeUniversitaire($annee2025);

        $f2025 = new Formation();
        $f2025->setDpe($campagne2025);
        $f2025->setFormationOrigineCopie($f2024);
        $this->setId($f2025, 103);

        $formationRepository->method('findBy')->willReturn([]);

        // Si anneeMin = 2025 : ne doit pas remonter à 2024 ni 2023
        $lignee2025 = $process->getLigneeFormations($f2025, 2025);
        $this->assertCount(1, $lignee2025);
        $this->assertSame(103, $lignee2025[0]->getId());

        // Si anneeMin = 2024 : remonte à 2024 mais s'arrête avant 2023
        $lignee2024 = $process->getLigneeFormations($f2025, 2024);
        $this->assertCount(2, $lignee2024);
        $ids = array_map(fn(Formation $f) => $f->getId(), $lignee2024);
        $this->assertContains(103, $ids);
        $this->assertContains(102, $ids);
        $this->assertNotContains(101, $ids);

        // Si anneeMin = null : remonte tout l'historique
        $ligneeAll = $process->getLigneeFormations($f2025, null);
        $this->assertCount(3, $ligneeAll);
    }

    private function setId(Formation $formation, int $id): void
    {
        $reflection = new \ReflectionProperty(Formation::class, 'id');
        $reflection->setValue($formation, $id);
    }
}
