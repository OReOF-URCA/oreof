<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Parcours;

use App\Entity\BlocCompetence;
use App\Entity\Competence;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\TypeDiplome;
use App\Service\Parcours\ParcoursTabCompletionChecker;
use App\TypeDiplome\Handler\ButHandler;
use App\TypeDiplome\TypeDiplomeHandlerInterface;
use App\TypeDiplome\TypeDiplomeResolver;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ParcoursTabCompetencesTest extends TestCase
{
    public function testButHandlerHasReferentielCompetencesImpose(): void
    {
        $reflection = new \ReflectionClass(ButHandler::class);
        /** @var ButHandler $butHandler */
        $butHandler = $reflection->newInstanceWithoutConstructor();

        self::assertTrue($butHandler->hasReferentielCompetencesImpose());
    }

    public function testCompetencesIssuesReturnsEmptyForParcoursWithReferentielImpose(): void
    {
        $typeDiplome = new TypeDiplome();
        $typeDiplome->setLibelleCourt('BUT');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeDiplome);

        $parcours = new Parcours();
        $formation->addParcour($parcours);

        $handlerMock = $this->createMock(TypeDiplomeHandlerInterface::class);
        $handlerMock->method('hasReferentielCompetencesImpose')->with($parcours)->willReturn(true);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($handlerMock);
        $resolver = new TypeDiplomeResolver($container);

        $checker = new ParcoursTabCompletionChecker($resolver);

        $issues = $checker->getTabIssues($parcours, 'competences');
        self::assertEmpty($issues);
        self::assertTrue($checker->isTabComplete($parcours, 'competences'));
        self::assertSame('green', $checker->computeParcoursTabStatus($parcours, 'competences', true, false));
    }

    public function testCompetencesIssuesReturnsIssuesForDiplomeWithoutReferentielImposeWhenNoBlocs(): void
    {
        $typeDiplome = new TypeDiplome();
        $typeDiplome->setLibelleCourt('LICENCE');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeDiplome);

        $parcours = new Parcours();
        $formation->addParcour($parcours);

        $handlerMock = $this->createMock(TypeDiplomeHandlerInterface::class);
        $handlerMock->method('hasReferentielCompetencesImpose')->with($parcours)->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($handlerMock);
        $resolver = new TypeDiplomeResolver($container);

        $checker = new ParcoursTabCompletionChecker($resolver);

        $issues = $checker->getTabIssues($parcours, 'competences');
        self::assertNotEmpty($issues);
        self::assertFalse($checker->isTabComplete($parcours, 'competences'));
    }

    public function testCompetencesIssuesReturnsEmptyForDiplomeWithoutReferentielImposeWithValidBlocs(): void
    {
        $typeDiplome = new TypeDiplome();
        $typeDiplome->setLibelleCourt('LICENCE');

        $formation = new Formation(null);
        $formation->setTypeDiplome($typeDiplome);

        $parcours = new Parcours();
        $formation->addParcour($parcours);

        $bloc = new BlocCompetence();
        $bloc->setLibelle('BCC 1');
        $competence = new Competence($bloc);
        $competence->setLibelle('Comp 1');
        $bloc->addCompetence($competence);
        $parcours->addBlocCompetence($bloc);

        $handlerMock = $this->createMock(TypeDiplomeHandlerInterface::class);
        $handlerMock->method('hasReferentielCompetencesImpose')->with($parcours)->willReturn(false);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($handlerMock);
        $resolver = new TypeDiplomeResolver($container);

        $checker = new ParcoursTabCompletionChecker($resolver);

        $issues = $checker->getTabIssues($parcours, 'competences');
        self::assertEmpty($issues);
        self::assertTrue($checker->isTabComplete($parcours, 'competences'));
    }
}
