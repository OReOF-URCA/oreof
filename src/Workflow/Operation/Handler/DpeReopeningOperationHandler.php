<?php

declare(strict_types=1);

namespace App\Workflow\Operation\Handler;

use App\Entity\DpeDemande;
use App\Entity\DpeParcours;
use App\Entity\User;
use App\Enums\EtatDpeEnum;
use App\Enums\TypeModificationDpeEnum;
use Dannebicque\WorkflowOperationsBundle\Contract\OperationHandlerInterface;
use Dannebicque\WorkflowOperationsBundle\Model\OperationContext;
use Dannebicque\WorkflowOperationsBundle\Model\WorkflowOperation;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies the ORéOF domain mutations required when reopening a DPE.
 *
 * The generic executor remains responsible for applying the workflow transition.
 */
final readonly class DpeReopeningOperationHandler implements OperationHandlerInterface
{
    private const SUPPORTED_TRANSITIONS = [
        'reouvrir_sans_cfvu',
        'reouvrir_avant_publie',
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function supports(object $subject, WorkflowOperation $operation): bool
    {
        return $subject instanceof DpeParcours
            && 'dpeParcours' === $operation->workflowName
            && in_array($operation->transitionName, self::SUPPORTED_TRANSITIONS, true);
    }

    public function handle(
        object $subject,
        WorkflowOperation $operation,
        OperationContext $context,
    ): void {
        if (!$subject instanceof DpeParcours) {
            throw new \InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                DpeParcours::class,
                get_debug_type($subject),
            ));
        }

        if (!$context->actor instanceof User) {
            throw new \DomainException('Un utilisateur ORéOF est requis pour réouvrir le DPE.');
        }

        $parcours = $subject->getParcours();
        $formation = $parcours?->getFormation();
        if (null === $parcours || null === $formation) {
            throw new \DomainException('Le parcours ou sa formation est introuvable.');
        }

        $argumentaire = trim((string) ($context->input['argumentaire'] ?? ''));
        $sansCfvu = 'reouvrir_sans_cfvu' === $operation->transitionName;
        $niveauModification = $sansCfvu
            ? TypeModificationDpeEnum::MODIFICATION_TEXTE
            : TypeModificationDpeEnum::MODIFICATION_MCCC_TEXTE;

        $demande = new DpeDemande();
        $demande->setFormation($formation);
        $demande->setCampagneCollecte($subject->getCampagneCollecte());
        $demande->setParcours($parcours);
        $demande->setAuteur($context->actor);
        $demande->setEtatDemande(
            $sansCfvu
                ? EtatDpeEnum::en_cours_redaction_ss_cfvu
                : EtatDpeEnum::en_cours_redaction
        );
        $demande->setNiveauDemande('P');
        $demande->setArgumentaireDemande($argumentaire);
        $demande->setNiveauModification($niveauModification);

        $subject->setEtatReconduction($niveauModification);
        $this->entityManager->persist($demande);
    }
}
