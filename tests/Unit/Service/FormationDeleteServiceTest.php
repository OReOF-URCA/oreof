<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\FormationDeleteAnalysis;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Service\Formation\FormationDeleteService;
use App\Service\Parcours\ParcoursDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class FormationDeleteServiceTest extends TestCase
{
    public function testAnalyzeFormationWithoutParcoursCanDelete(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $parcoursDeleteService = new ParcoursDeleteService($em, new NullLogger(), $security);

        $formation = new Formation(null);
        $formation->setMentionTexte('Formation Sans Parcours');

        $service = new FormationDeleteService($em, new NullLogger(), $security, $parcoursDeleteService);
        $analysis = $service->analyze($formation);

        $this->assertInstanceOf(FormationDeleteAnalysis::class, $analysis);
        $this->assertTrue($analysis->canDelete());
        $this->assertSame(0, $analysis->parcoursCount);
        $this->assertNull($analysis->parcoursDefaut);
        $this->assertEmpty($analysis->blockingReasons);
    }

    public function testAnalyzeFormationWithSingleDefaultParcoursCanDelete(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $parcoursDeleteService = new ParcoursDeleteService($em, new NullLogger(), $security);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle(Parcours::PARCOURS_DEFAUT);
        $formation->getParcours()->add($parcours);

        $service = new FormationDeleteService($em, new NullLogger(), $security, $parcoursDeleteService);
        $analysis = $service->analyze($formation);

        $this->assertTrue($analysis->canDelete());
        $this->assertSame(1, $analysis->parcoursCount);
        $this->assertSame($parcours, $analysis->parcoursDefaut);
        $this->assertEmpty($analysis->blockingReasons);
    }

    public function testAnalyzeFormationWithMultipleParcoursIsBlocked(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $parcoursDeleteService = new ParcoursDeleteService($em, new NullLogger(), $security);

        $formation = new Formation(null);
        $p1 = new Parcours($formation);
        $p1->setLibelle(Parcours::PARCOURS_DEFAUT);
        $formation->getParcours()->add($p1);

        $p2 = new Parcours($formation);
        $p2->setLibelle('Option Informatique');
        $formation->getParcours()->add($p2);

        $service = new FormationDeleteService($em, new NullLogger(), $security, $parcoursDeleteService);
        $analysis = $service->analyze($formation);

        $this->assertFalse($analysis->canDelete());
        $this->assertSame(2, $analysis->parcoursCount);
        $this->assertNotEmpty($analysis->blockingReasons);
        $this->assertStringContainsString('contient 2 parcours', $analysis->blockingReasons[0]);
    }

    public function testAnalyzeFormationWithSingleNonDefaultParcoursIsBlocked(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $parcoursDeleteService = new ParcoursDeleteService($em, new NullLogger(), $security);

        $formation = new Formation(null);
        $p1 = new Parcours($formation);
        $p1->setLibelle('Parcours Spécifique Non Défaut');
        $formation->getParcours()->add($p1);

        $service = new FormationDeleteService($em, new NullLogger(), $security, $parcoursDeleteService);
        $analysis = $service->analyze($formation);

        $this->assertFalse($analysis->canDelete());
        $this->assertSame(1, $analysis->parcoursCount);
        $this->assertNotEmpty($analysis->blockingReasons);
        $this->assertStringContainsString('n\'est pas le parcours par défaut', $analysis->blockingReasons[0]);
    }

    public function testDeleteThrowsWhenBlockedByParcours(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $parcoursDeleteService = new ParcoursDeleteService($em, new NullLogger(), $security);

        $formation = new Formation(null);
        $p1 = new Parcours($formation);
        $p1->setLibelle('Parcours A');
        $formation->getParcours()->add($p1);
        $p2 = new Parcours($formation);
        $p2->setLibelle('Parcours B');
        $formation->getParcours()->add($p2);

        $service = new FormationDeleteService($em, new NullLogger(), $security, $parcoursDeleteService);

        $this->expectException(LogicException::class);
        $service->delete($formation);
    }
}
