<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Formation;

use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\RythmeFormation;
use App\Entity\User;
use App\Repository\ComposanteRepository;
use App\Repository\RythmeFormationRepository;
use App\Repository\UserRepository;
use App\Repository\VilleRepository;
use App\Service\Formation\FormationFieldUpdater;
use PHPUnit\Framework\TestCase;

class FormationFieldUpdaterTest extends TestCase
{
    private FormationFieldUpdater $updater;
    private UserRepository $userRepo;
    private VilleRepository $villeRepo;
    private RythmeFormationRepository $rythmeRepo;
    private ComposanteRepository $composanteRepo;

    public function testApplyRythmeFormationForPresentationTab(): void
    {
        $rythme = new RythmeFormation();
        $rythme->setLibelle('Alternance');

        $this->rythmeRepo->method('find')
            ->with(2)
            ->willReturn($rythme);

        $formation = new Formation();
        $this->assertNull($formation->getRythmeFormation());

        $this->updater->applyForTab($formation, 'presentation', 'formation_step2[rythmeFormation]', '2');
        $this->assertSame($rythme, $formation->getRythmeFormation());

        // Sync to mono-parcours default parcours if hasParcours == false
        $parcours = new Parcours();
        $formation->setHasParcours(false);
        $formation->addParcour($parcours);

        $this->updater->applyForTab($formation, 'presentation', 'formation_step2[rythmeFormation]', '2');
        $this->assertSame($rythme, $parcours->getRythmeFormation());

        $this->updater->applyForTab($formation, 'presentation', 'formation_step2[rythmeFormationTexte]', '<p>Alternance 2 semaines</p>');
        $this->assertSame('<p>Alternance 2 semaines</p>', $formation->getRythmeFormationTexte());
        $this->assertSame('<p>Alternance 2 semaines</p>', $parcours->getRythmeFormationTexte());
    }

    public function testApplySigleAndResponsablesForLocalisationTab(): void
    {
        $user1 = new User();
        $user2 = new User();

        $this->userRepo->method('find')
            ->willReturnCallback(fn($id) => match ($id) {
                10 => $user1,
                20 => $user2,
                default => null,
            });

        $formation = new Formation();

        $this->updater->applyForTab($formation, 'localisation', 'formation_step1[sigle]', 'INFO-PRO');
        $this->assertSame('INFO-PRO', $formation->getSigle());

        $this->updater->applyForTab($formation, 'localisation', 'formation_step1[responsableMention]', '10');
        $this->assertSame($user1, $formation->getResponsableMention());

        $this->updater->applyForTab($formation, 'localisation', 'formation_step1[coResponsable]', '20');
        $this->assertSame($user2, $formation->getCoResponsable());
    }

    public function testApplyRegimeInscriptionWithJsonArray(): void
    {
        $formation = new Formation();
        $formation->setHasParcours(false);
        $parcours = new Parcours();
        $formation->addParcour($parcours);

        $json = json_encode(['Formation Initiale', 'Formation Initiale en apprentissage']);

        $this->updater->applyForTab($formation, 'localisation', 'formation_step1[regimeInscription][]', $json);
        $this->assertSame(['Formation Initiale', 'Formation Initiale en apprentissage'], $formation->getRegimeInscriptionValues());
        $this->assertSame(['Formation Initiale', 'Formation Initiale en apprentissage'], $parcours->getRegimeInscriptionValues());

        $this->updater->applyForTab($formation, 'localisation', 'formation_step1[modalitesAlternance]', '<p>2 semaines CFA / 2 semaines entreprise</p>');
        $this->assertSame('<p>2 semaines CFA / 2 semaines entreprise</p>', $formation->getModalitesAlternance());
        $this->assertSame('<p>2 semaines CFA / 2 semaines entreprise</p>', $parcours->getModalitesAlternance());
    }

    protected function setUp(): void
    {
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->villeRepo = $this->createMock(VilleRepository::class);
        $this->rythmeRepo = $this->createMock(RythmeFormationRepository::class);
        $this->composanteRepo = $this->createMock(ComposanteRepository::class);

        $this->updater = new FormationFieldUpdater(
            $this->userRepo,
            $this->villeRepo,
            $this->rythmeRepo,
            $this->composanteRepo,
        );
    }
}
