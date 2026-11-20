<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\DpeParcours;
use App\Security\Voter\DpeWorkflowOperationVoter;
use Dannebicque\WorkflowOperationsBundle\Model\OperationAuthorizationSubject;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DpeWorkflowOperationVoterTest extends TestCase
{
    /**
     * Profil « Responsable Formation » : manage parcours/formation,
     * show composante/établissement.
     *
     * @param array<string, bool> $droits route => MANAGE accordé
     */
    private function vote(string $transition, array $droits): int
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static function (string $attr, mixed $subject = null) use ($droits): bool {
                if ('ROLE_ADMIN' === $attr) {
                    return false;
                }

                return 'MANAGE' === $attr && ($droits[$subject['route']] ?? false);
            }
        );

        $operation = new WorkflowOperation('dpeParcours', $transition);
        $subject = new OperationAuthorizationSubject(new DpeParcours(), $operation, OperationContext::empty());

        return (new DpeWorkflowOperationVoter($security))->vote(
            $this->createMock(TokenInterface::class),
            $subject,
            [DpeWorkflowOperationVoter::APPLY],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function transitionsSes(): iterable
    {
        foreach ([
            'valider_central', 'reserver_central', 'valider_publication_directe', 'refuser_revoir_cfvu',
            'valider_cfvu', 'valider_publication', 'publier', 'valider_reserve_cfvu',
            'valider_transmettre_ouverture_sans_cfvu', 'valider_non_ouverture_ses',
        ] as $t) {
            yield $t => [$t];
        }
    }

    #[DataProvider('transitionsSes')]
    public function testResponsableFormationNePeutPasAgirCoteSes(string $transition): void
    {
        $droits = ['app_formation' => true, 'app_parcours' => true];
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($transition, $droits));
    }

    #[DataProvider('transitionsSes')]
    public function testGestionnaireEtablissementPeutAgirCoteSes(string $transition): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($transition, ['app_etablissement' => true]));
    }

    public function testResponsableFormationNePeutPasValiderCoteComposante(): void
    {
        $droits = ['app_formation' => true, 'app_parcours' => true];
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('valider_dpe_composante', $droits));
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote('reserver_dpe_composante', $droits));
    }

    public function testResponsableFormationPeutSoumettreEtValiderCoteRf(): void
    {
        $droits = ['app_formation' => true];
        foreach (['autoriser', 'valider_rf', 'reserver_rf', 'reouvrir_mccc'] as $t) {
            self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($t, $droits), $t);
        }
    }

    public function testGestionnaireComposantePeutValiderCoteComposante(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $this->vote('valider_dpe_composante', ['app_composante' => true]),
        );
    }
}
