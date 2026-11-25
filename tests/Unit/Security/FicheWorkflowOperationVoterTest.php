<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Composante;
use App\Entity\FicheMatiere;
use App\Entity\Formation;
use App\Entity\Parcours;
use App\Entity\User;
use App\Security\Voter\FicheWorkflowOperationVoter;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class FicheWorkflowOperationVoterTest extends TestCase
{
    private Security&MockObject $security;
    private FicheWorkflowOperationVoter $voter;

    public function testSupportsOnlyExpectedSubjectAndAttribute(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $fiche = new FicheMatiere();
        $validSubject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, $validSubject, ['OTHER_ATTR'])
        );

        $wrongWorkflowSubject = $this->createSubject($fiche, 'rouvrir_fiche_matiere', 'other_workflow');
        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, $wrongWorkflowSubject, [FicheWorkflowOperationVoter::APPLY])
        );

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $this->voter->vote($token, new \stdClass(), [FicheWorkflowOperationVoter::APPLY])
        );
    }

    private function createSubject(FicheMatiere $fiche, string $transitionName, string $workflowName = 'fiche'): OperationAuthorizationSubject
    {
        return new OperationAuthorizationSubject(
            $fiche,
            new WorkflowOperation($workflowName, $transitionName),
            OperationContext::empty(),
        );
    }

    public function testGrantsAccessToAdmin(): void
    {
        $fiche = new FicheMatiere();
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_ADMIN']);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    public function testDeniesUnauthenticatedUser(): void
    {
        $fiche = new FicheMatiere();
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([]);
        $token->method('getUser')->willReturn(null);
        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    public function testReopenAllowsAuthor(): void
    {
        $author = new User();
        $this->setUserId($author, 10);

        $fiche = new FicheMatiere();
        $fiche->setResponsableFicheMatiere($author);
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_USER']);
        $token->method('getUser')->willReturn($author);
        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    private function setUserId(User $user, int $id): void
    {
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);
    }

    public function testReopenAllowsRpAndCoRp(): void
    {
        $rp = new User();
        $this->setUserId($rp, 20);
        $corp = new User();
        $this->setUserId($corp, 21);

        $parcours = new Parcours(null);
        $parcours->setRespParcours($rp);
        $parcours->setCoResponsable($corp);

        $fiche = new FicheMatiere();
        $fiche->setParcours($parcours);
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $tokenRp = $this->createMock(TokenInterface::class);
        $tokenRp->method('getRoleNames')->willReturn(['ROLE_USER']);
        $tokenRp->method('getUser')->willReturn($rp);

        $tokenCoRp = $this->createMock(TokenInterface::class);
        $tokenCoRp->method('getRoleNames')->willReturn(['ROLE_USER']);
        $tokenCoRp->method('getUser')->willReturn($corp);

        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenRp, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenCoRp, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    public function testReopenAllowsRfAndCoRf(): void
    {
        $rf = new User();
        $this->setUserId($rf, 30);
        $corf = new User();
        $this->setUserId($corf, 31);

        $formation = new Formation(null);
        $formation->setResponsableMention($rf);
        $formation->setCoResponsable($corf);

        $parcours = new Parcours(null);
        $parcours->setFormation($formation);

        $fiche = new FicheMatiere();
        $fiche->setParcours($parcours);
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere_b');

        $tokenRf = $this->createMock(TokenInterface::class);
        $tokenRf->method('getRoleNames')->willReturn(['ROLE_USER']);
        $tokenRf->method('getUser')->willReturn($rf);

        $tokenCoRf = $this->createMock(TokenInterface::class);
        $tokenCoRf->method('getRoleNames')->willReturn(['ROLE_USER']);
        $tokenCoRf->method('getUser')->willReturn($corf);

        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenRf, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($tokenCoRf, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    public function testReopenDeniesUserWithoutRoleOnParcoursPorteur(): void
    {
        $author = new User();
        $this->setUserId($author, 1);
        $otherUser = new User();
        $this->setUserId($otherUser, 99);

        $composante = new Composante();
        $formation = new Formation(null);
        $formation->setComposantePorteuse($composante);

        $parcours = new Parcours(null);
        $parcours->setFormation($formation);

        $fiche = new FicheMatiere();
        $fiche->setResponsableFicheMatiere($author);
        $fiche->setParcours($parcours);
        $subject = $this->createSubject($fiche, 'rouvrir_fiche_matiere');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_USER']);
        $token->method('getUser')->willReturn($otherUser);

        $this->security->method('isGranted')->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $this->voter->vote($token, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    public function testSesTransitionDelegatesToEtablissementManage(): void
    {
        $user = new User();
        $this->setUserId($user, 50);

        $fiche = new FicheMatiere();
        $subject = $this->createSubject($fiche, 'valider_fiche_ses');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn(['ROLE_USER']);
        $token->method('getUser')->willReturn($user);

        $this->security->method('isGranted')->willReturnCallback(function (string $attribute, mixed $subject): bool {
            return 'MANAGE' === $attribute;
        });

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->voter->vote($token, $subject, [FicheWorkflowOperationVoter::APPLY])
        );
    }

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->voter = new FicheWorkflowOperationVoter($this->security);
    }
}
