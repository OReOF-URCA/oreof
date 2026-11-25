<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;
use App\Security\Voter\FicheMatiereVoter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class FicheMatiereVoterTest extends TestCase
{
    private Security&MockObject $security;
    private FicheMatiereVoter $voter;

    public function testSupportsExpectedAttributes(): void
    {
        $fiche = new FicheMatiere();
        $token = $this->createMock(TokenInterface::class);

        // Non-FicheMatiere subject
        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, new \stdClass(), ['SHOW'])
        );

        // Unsupported attribute
        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, $fiche, ['DELETE'])
        );
    }

    public function testDeniesUnauthenticatedUser(): void
    {
        $fiche = new FicheMatiere();
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $fiche, ['SHOW'])
        );
        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $fiche, ['EDIT'])
        );
    }

    public function testAllowsAnyAuthenticatedUserToView(): void
    {
        $fiche = new FicheMatiere();
        $fiche->setEtatFiche(['publie' => 1]); // Non-modifiable
        $user = new User();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Non-admin, non-author, non-RF/RP
        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $fiche, ['SHOW'])
        );
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $fiche, ['VIEW'])
        );
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $fiche, [FicheMatiereVoter::VIEW])
        );
    }

    public function testEditDeniesWhenFicheNotModifiable(): void
    {
        $fiche = new FicheMatiere();
        $fiche->setEtatFiche(['publie' => 1]); // Non-modifiable
        $user = new User();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $fiche, ['EDIT'])
        );
    }

    public function testEditAllowsAuthor(): void
    {
        $user = new User();
        $this->setUserId($user, 42);
        $fiche = new FicheMatiere();
        $fiche->setEtatFiche(['en_cours_redaction' => 1]);
        $fiche->setResponsableFicheMatiere($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $fiche, ['EDIT'])
        );
    }

    private function setUserId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
    }

    public function testEditAllowsParcoursRespAndFormationResp(): void
    {
        $rp = new User();
        $this->setUserId($rp, 10);
        $rf = new User();
        $this->setUserId($rf, 20);

        $formation = new Formation(null);
        $formation->setResponsableMention($rf);

        $parcours = new Parcours(null);
        $parcours->setFormation($formation);
        $parcours->setRespParcours($rp);

        $fiche = new FicheMatiere();
        $fiche->setEtatFiche(['en_cours_redaction' => 1]);
        $fiche->setParcours($parcours);

        $tokenRp = $this->createMock(TokenInterface::class);
        $tokenRp->method('getUser')->willReturn($rp);

        $tokenRf = $this->createMock(TokenInterface::class);
        $tokenRf->method('getUser')->willReturn($rf);

        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenRp, $fiche, ['EDIT'])
        );

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenRf, $fiche, ['EDIT'])
        );
    }

    public function testEditDeniesUserWhoIsNotAuthorOrResp(): void
    {
        $author = new User();
        $this->setUserId($author, 1);
        $otherUser = new User();
        $this->setUserId($otherUser, 2);

        $parcours = new Parcours(null);
        $fiche = new FicheMatiere();
        $fiche->setEtatFiche(['en_cours_redaction' => 1]);
        $fiche->setResponsableFicheMatiere($author);
        $fiche->setParcours($parcours);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($otherUser);

        $this->security->method('isGranted')->willReturnCallback(function (string $attribute, mixed $subject = null): bool {
            return false;
        });

        // Can view
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $fiche, ['SHOW'])
        );

        // Cannot edit
        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $fiche, ['EDIT'])
        );
    }

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter = new FicheMatiereVoter($this->security);
    }
}
