<?php

namespace App\Tests\Unit\Security;

use App\Entity\Composante;
use App\Entity\DpeParcours;
use App\Entity\Etablissement;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\Profil;
use App\Entity\User;
use App\Entity\UserProfil;
use App\Enums\CentreGestionEnum;
use App\Enums\PermissionEnum;
use App\Enums\TypeModificationDpeEnum;
use App\Repository\ProfilDroitsRepository;
use App\Repository\UserProfilRepository;
use App\Security\Voter\RessourceVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class RessourceVoterTest extends TestCase
{
    private WorkflowInterface&MockObject $dpeParcoursWorkflow;
    private WorkflowInterface&MockObject $ficheWorkflow;
    private Security&MockObject $security;
    private UserProfilRepository&MockObject $userProfilRepository;
    private ProfilDroitsRepository&MockObject $profilDroitsRepository;
    private RessourceVoter $voter;

    protected function setUp(): void
    {
        $this->dpeParcoursWorkflow = $this->createMock(WorkflowInterface::class);
        $this->ficheWorkflow = $this->createMock(WorkflowInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->userProfilRepository = $this->createMock(UserProfilRepository::class);
        $this->profilDroitsRepository = $this->createMock(ProfilDroitsRepository::class);

        $this->voter = new RessourceVoter(
            $this->dpeParcoursWorkflow,
            $this->ficheWorkflow,
            $this->security,
            $this->userProfilRepository,
            $this->profilDroitsRepository
        );
    }

    public function testSupportsEntityDirectlyAndStringAndLegacyArray(): void
    {
        $parcours = new Parcours(null);
        $formation = new Formation(null);
        $fiche = new FicheMatiere();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        // Anon user -> ACCESS_DENIED
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $parcours, ['EDIT']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $formation, ['SHOW']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $fiche, ['EDIT']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'composante', ['SHOW']));
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, ['route' => 'app_parcours', 'subject' => $parcours], ['EDIT']));

        // Unsupported attribute -> ACCESS_ABSTAIN
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, $parcours, ['UNKNOWN_ATTR']));
    }

    public function testAdminCanAccessGenericString(): void
    {
        $user = new User();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'composante', ['SHOW']));
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'formation', ['EDIT']));
    }

    public function testAdminCanEditFormationWhenOpen(): void
    {
        $user = new User();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(true);

        $formation = new Formation(null);
        $formation->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $formation, ['EDIT']));

        $closedFormation = new Formation(null);
        $closedFormation->setEtatReconduction(TypeModificationDpeEnum::NON_OUVERTURE);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $closedFormation, ['EDIT']));
    }

    public function testUserWithParcoursProfileCanEditHisParcoursDuringRedaction(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $parcours = new Parcours(null);
        $dpeParcours = new DpeParcours();
        $dpeParcours->setParcours($parcours);
        $dpeParcours->setEtatReconduction(TypeModificationDpeEnum::MODIFICATION);
        $parcours->addDpeParcour($dpeParcours);

        $profil = new Profil();
        $profil->setCentre(CentreGestionEnum::CENTRE_GESTION_PARCOURS);

        $userProfil = new UserProfil();
        $userProfil->setUser($user);
        $userProfil->setProfil($profil);
        $userProfil->setParcours($parcours);

        $this->userProfilRepository->method('findBy')->with(['user' => $user])->willReturn([$userProfil]);

        $this->profilDroitsRepository->method('hasDroit')
            ->with($profil, 'edit', 'app_parcours')
            ->willReturn(true);

        $this->dpeParcoursWorkflow->method('can')
            ->willReturnCallback(fn($subject, $transition) => $transition === 'valider_parcours');

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $parcours, ['EDIT']));
    }

    public function testUserCannotEditParcoursFromAnotherScope(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(42);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $this->security->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);

        $parcoursA = new Parcours(null);
        $parcoursB = new Parcours(null);

        $profil = new Profil();
        $profil->setCentre(CentreGestionEnum::CENTRE_GESTION_PARCOURS);

        $userProfil = new UserProfil();
        $userProfil->setUser($user);
        $userProfil->setProfil($profil);
        $userProfil->setParcours($parcoursA);

        $this->userProfilRepository->method('findBy')->with(['user' => $user])->willReturn([$userProfil]);

        $this->profilDroitsRepository->method('hasDroit')->willReturn(true);

        // Accessing parcours B should be denied
        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $parcoursB, ['EDIT']));
    }
}
