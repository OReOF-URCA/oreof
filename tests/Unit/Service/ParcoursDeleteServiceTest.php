<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\ParcoursDeleteAnalysis;
use App\Entity\Adresse;
use App\Entity\Contact;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Semestre;
use App\Entity\SemestreMutualisable;
use App\Service\Parcours\ParcoursDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;

final class ParcoursDeleteServiceTest extends TestCase
{
    public function testAnalyzeParcoursWithoutDependencies(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Isolé');

        $service = new ParcoursDeleteService($em, new NullLogger(), $security);
        $analysis = $service->analyze($parcours);

        $this->assertInstanceOf(ParcoursDeleteAnalysis::class, $analysis);
        $this->assertFalse($analysis->hasSharedDependencies());
        $this->assertSame(0, $analysis->getSharedDependenciesCount());
    }

    public function testAnalyzeParcoursWithSharedSemestre(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $smRepo = $this->createMock(EntityRepository::class);
        $generalRepo = $this->createMock(EntityRepository::class);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours avec mutualisation');

        $semestre = new Semestre();
        $semestre->setOrdre(1);

        $sm = new SemestreMutualisable();
        $sm->setParcours($parcours);
        $sm->setSemestre($semestre);

        // Simuler un autre semestre raccroché à ce SemestreMutualisable
        $otherSemestre = new Semestre();
        $sm->getSemestres()->add($otherSemestre);

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($smRepo, $generalRepo) {
            if ($class === SemestreMutualisable::class) {
                return $smRepo;
            }
            return $generalRepo;
        });

        $smRepo->method('findBy')->willReturn([$sm]);
        $generalRepo->method('findBy')->willReturn([]);
        $generalRepo->method('count')->willReturn(0);

        $service = new ParcoursDeleteService($em, new NullLogger(), $security);
        $analysis = $service->analyze($parcours);

        $this->assertTrue($analysis->hasSharedDependencies());
        $this->assertGreaterThan(0, $analysis->getSharedDependenciesCount());

        $types = array_column($analysis->sharedDependencies, 'type');
        $this->assertContains('semestres_mutualises', $types);
    }

    public function testDeleteWithoutForceThrowsWhenSharedDependenciesExist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $smRepo = $this->createMock(EntityRepository::class);
        $generalRepo = $this->createMock(EntityRepository::class);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours Bloquant');

        $sm = new SemestreMutualisable();
        $sm->setParcours($parcours);
        $sm->getSemestres()->add(new Semestre());

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($smRepo, $generalRepo) {
            if ($class === SemestreMutualisable::class) {
                return $smRepo;
            }
            return $generalRepo;
        });

        $smRepo->method('findBy')->willReturn([$sm]);
        $generalRepo->method('findBy')->willReturn([]);
        $generalRepo->method('count')->willReturn(0);

        $service = new ParcoursDeleteService($em, new NullLogger(), $security);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('dépendances partagées ont été détectées');

        $service->delete($parcours, force: false);
    }

    public function testDeleteWithForceProceedsWhenSharedDependenciesExist(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $repo = $this->createMock(EntityRepository::class);
        $em->method('getRepository')->willReturn($repo);
        $repo->method('findBy')->willReturn([]);
        $repo->method('count')->willReturn(0);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours A Supprimer');

        $em->expects($this->atLeastOnce())->method('remove');
        $em->expects($this->once())->method('flush');

        $service = new ParcoursDeleteService($em, new NullLogger(), $security);
        $service->delete($parcours, force: true);
    }

    public function testDeleteDetachesContactsAndClonedAdresses(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);

        $formation = new Formation(null);
        $parcours = new Parcours($formation);
        $parcours->setLibelle('Parcours avec contacts');

        $originalAdresse = new Adresse();
        $originalAdresse->setVille('Reims');

        $clonedAdresse = new Adresse();
        $clonedAdresse->setVille('Reims Copie');
        $clonedAdresse->setAdresseOrigineCopie($originalAdresse);

        $childClonedAdresse = new Adresse();
        $childClonedAdresse->setVille('Reims Enfant');
        $childClonedAdresse->setAdresseOrigineCopie($clonedAdresse);

        $contact = new Contact();
        $contact->setNom('Dupont');
        $contact->setAdresse($clonedAdresse);
        $parcours->getContacts()->add($contact);

        $adresseRepo = $this->createMock(EntityRepository::class);
        $generalRepo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')->willReturnCallback(function (string $class) use ($adresseRepo, $generalRepo) {
            if ($class === Adresse::class) {
                return $adresseRepo;
            }
            return $generalRepo;
        });

        $adresseRepo->method('findBy')->willReturnCallback(function (array $criteria) use ($childClonedAdresse) {
            if (isset($criteria['adresseOrigineCopie'])) {
                return [$childClonedAdresse];
            }
            return [];
        });
        $generalRepo->method('findBy')->willReturn([]);
        $generalRepo->method('count')->willReturn(0);

        $em->expects($this->atLeastOnce())->method('remove');
        $em->expects($this->once())->method('flush');

        $service = new ParcoursDeleteService($em, new NullLogger(), $security);
        $service->delete($parcours, force: true);

        // Assert that links were broken/detached
        $this->assertNull($clonedAdresse->getAdresseOrigineCopie());
        $this->assertNull($childClonedAdresse->getAdresseOrigineCopie());
    }
}
